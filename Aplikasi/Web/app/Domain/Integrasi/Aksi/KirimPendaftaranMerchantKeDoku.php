<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\Enum\StatusPendaftaranMerchant;
use App\Domain\Integrasi\Merchant\GalatPartnerDoku;
use App\Domain\Integrasi\Merchant\KlienPartnerDoku;
use App\Domain\Integrasi\Merchant\PembentukBadanPendaftaranDoku;
use App\Domain\Integrasi\Merchant\PenyimpanBerkasKyc;
use App\Domain\Integrasi\Model\PendaftaranMerchantPembayaran;
use App\Domain\Referensi\Enum\JenisReferensiBank;
use App\Domain\Referensi\Kueri\ReferensiBankAktif;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Mengirim pendaftaran berstatus `Dikirim` ke DOKU Partner API: Generate Token, Upload File untuk KTP + swafoto + foto
 * tempat usaha, lalu Business Registration. Konteks tenant harus sudah diatur pemanggil (tugas antrean).
 *
 * - Idempoten dan dapat dilanjutkan: id berkas disimpan segera setelah tiap unggahan dan berkas lokalnya langsung
 *   dihapus; bila `IdBisnisDoku` sudah ada, DOKU tidak didaftari ulang.
 * - Galat 4xx pasti = `Gagal` dengan pesan tersaring (berkas lokal yang tersisa dihapus, data diperbaiki lalu simpan
 *   ulang). Galat tidak pasti (jaringan, 5xx, kredensial partner, batas laju) = tetap `Dikirim` dan mengembalikan
 *   true supaya pemanggil mengulang; `Menyerah()` mengembalikan ke `Draf` bila percobaan habis.
 * - Satu pengiriman per tenant sekaligus (kunci cache). Rahasia, NIK, dan nomor rekening tidak masuk pesan galat.
 */
final class KirimPendaftaranMerchantKeDoku
{
    public const AKSI_AUDIT = 'merchant-pembayaran.terkirim';

    private const DETIK_KUNCI = 180;

    /** @var list<array{0: string, 1: string, 2: string, 3: ?string, 4: string}> */
    private const BERKAS = [
        ['PathKtp', 'IdFileKtp', 'DOCUMENT', 'KTP', 'ktp'],
        ['PathSwafoto', 'IdFileSwafoto', 'OWNER_LIVENESS', null, 'swafoto'],
        ['PathBuktiUsaha', 'IdFileBuktiUsaha', 'PHOTO_PROOF', null, 'bukti-usaha'],
    ];

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly KlienPartnerDoku $klien,
        private readonly PenyimpanBerkasKyc $berkas,
        private readonly ReferensiBankAktif $bank,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @return bool true = galat tidak pasti, pemanggil sebaiknya mengulang nanti
     */
    public function Jalankan(): bool
    {
        $idTenant = $this->konteks->Wajib();

        try {
            return Cache::lock("pendaftaran-merchant:{$idTenant}", self::DETIK_KUNCI)->block(5, fn (): bool => $this->Proses());
        } catch (LockTimeoutException) {
            return true;
        }
    }

    /** Percobaan habis: kembali ke `Draf` (foto yang belum terunggah tetap tersimpan sampai kedaluwarsa) agar tenant bisa mengirim lagi. */
    public function Menyerah(): void
    {
        $pendaftaran = PendaftaranMerchantPembayaran::query()->where('Status', StatusPendaftaranMerchant::Dikirim->value)->first();

        if ($pendaftaran !== null && ! $pendaftaran->CekSudahTerdaftar()) {
            $pendaftaran->update(['Status' => StatusPendaftaranMerchant::Draf, 'PesanGalat' => 'DOKU belum bisa dihubungi, jadi pengiriman belum berhasil. Coba kirim lagi sebentar lagi.']);
        }
    }

    private function Proses(): bool
    {
        $pendaftaran = PendaftaranMerchantPembayaran::query()->where('Status', StatusPendaftaranMerchant::Dikirim->value)->first();

        if ($pendaftaran === null || $pendaftaran->CekSudahTerdaftar()) {
            return false;
        }

        if (! $this->klien->CekAktif()) {
            $pendaftaran->update(['Status' => StatusPendaftaranMerchant::Draf, 'PesanGalat' => 'Layanan aktivasi QRIS belum tersedia. Hubungi dukungan Payoung.']);

            return false;
        }

        try {
            $this->UnggahBerkas($pendaftaran);
            $this->DaftarkanBisnis($pendaftaran);

            return false;
        } catch (GalatPartnerDoku $galat) {
            $pesan = $this->Saring($galat->getMessage(), $pendaftaran);

            if ($galat->tidakPasti) {
                $pendaftaran->update(['PesanGalat' => $pesan]);

                return true;
            }

            $this->HapusSisaBerkas($pendaftaran);
            $pendaftaran->update(['Status' => StatusPendaftaranMerchant::Gagal, 'PesanGalat' => $pesan]);
            $this->audit->Catat(self::AKSI_AUDIT, $pendaftaran, ['Status' => 'Dikirim'], ['Status' => 'Gagal', 'PesanGalat' => $pesan]);

            return false;
        }
    }

    private function UnggahBerkas(PendaftaranMerchantPembayaran $pendaftaran): void
    {
        $token = null;

        foreach (self::BERKAS as [$kolomPath, $kolomId, $kategori, $kode, $nama]) {
            if ($pendaftaran->{$kolomId} !== null) {
                continue;
            }

            $path = $pendaftaran->{$kolomPath};
            $isi = $path === null ? null : $this->berkas->Baca($path, $nama);

            if ($isi === null) {
                throw new GalatPartnerDoku('Salah satu foto sudah kedaluwarsa atau rusak. Simpan ulang data dan unggah fotonya lagi.');
            }

            $token ??= $this->klien->AmbilToken();
            $id = $this->klien->UnggahBerkas($token, $kategori, $kode, $isi['Isi'], $isi['Nama']);

            // Kemajuan disimpan dulu, baru berkas lokal dihapus: foto tidak menunggu lebih lama dari yang perlu.
            $pendaftaran->update([$kolomId => $id]);
            $this->berkas->Hapus($path);
            $pendaftaran->update([$kolomPath => null]);
        }
    }

    private function DaftarkanBisnis(PendaftaranMerchantPembayaran $pendaftaran): void
    {
        $bank = null;

        foreach ($this->bank->Ambil([JenisReferensiBank::Bank]) as $kandidat) {
            if ($kandidat['Id'] === $pendaftaran->IdReferensiBank) {
                $bank = ['Kode' => $kandidat['Kode'], 'Nama' => $kandidat['Nama']];
            }
        }

        if ($bank === null) {
            throw new GalatPartnerDoku('Bank yang dipilih sudah tidak tersedia. Simpan ulang data dan pilih bank lagi.');
        }

        $badan = PembentukBadanPendaftaranDoku::Bentuk(
            $pendaftaran,
            ['Ktp' => (string) $pendaftaran->IdFileKtp, 'Swafoto' => (string) $pendaftaran->IdFileSwafoto, 'BuktiUsaha' => (string) $pendaftaran->IdFileBuktiUsaha],
            route('webhook.pembayaran.doku-partner', ['ref' => $pendaftaran->TokenCallback]),
            $bank,
        );
        $hasil = $this->klien->DaftarkanBisnis($badan);
        $status = $hasil->AmbilStatusPendaftaran();

        $pendaftaran->update([
            'IdBisnisDoku' => $hasil->idBisnis,
            'IdBrandDoku' => $hasil->idBrand,
            'KunciBersama' => $hasil->kunciBersama,
            'StatusDoku' => $hasil->statusBisnis === '' ? null : $hasil->statusBisnis,
            'Status' => $status,
            'PesanGalat' => null,
            'AlasanPenolakan' => $status === StatusPendaftaranMerchant::Ditolak ? ($hasil->alasanPenolakan ?? 'DOKU menolak pendaftaran ini.') : null,
            'DikirimPada' => now(),
            'DiperiksaPada' => now(),
            'DisetujuiPada' => $status === StatusPendaftaranMerchant::Aktif ? now() : null,
        ]);
        $this->HapusSisaBerkas($pendaftaran);

        $this->audit->Catat(self::AKSI_AUDIT, $pendaftaran, ['Status' => 'Dikirim'], ['Status' => $status->value, 'IdBisnisDoku' => $hasil->idBisnis]);
    }

    private function HapusSisaBerkas(PendaftaranMerchantPembayaran $pendaftaran): void
    {
        foreach (self::BERKAS as [$kolomPath]) {
            $this->berkas->Hapus($pendaftaran->{$kolomPath});
        }

        $pendaftaran->update(['PathKtp' => null, 'PathSwafoto' => null, 'PathBuktiUsaha' => null, 'BerkasDiunggahPada' => null]);
    }

    /** Pesan dari DOKU bisa memantulkan isian; NIK dan nomor rekening dibuang sebelum disimpan. */
    private function Saring(string $pesan, PendaftaranMerchantPembayaran $pendaftaran): string
    {
        $rahasia = array_filter([(string) $pendaftaran->Nik, (string) $pendaftaran->NomorRekening], static fn (string $s): bool => $s !== '');

        return mb_substr(str_replace($rahasia, '••••', $pesan), 0, 300);
    }
}
