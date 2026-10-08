<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Enum\StatusPendaftaranMerchant;
use App\Domain\Integrasi\Merchant\KlienPartnerDoku;
use App\Domain\Integrasi\Model\PendaftaranMerchantPembayaran;
use App\Domain\Integrasi\Tugas\KirimPendaftaranMerchantTugas;
use Illuminate\Support\Facades\DB;

/**
 * Tenant mengajukan pendaftaran merchant yang sudah lengkap: status jadi `Dikirim`, lalu unggah berkas dan registrasi
 * ke DOKU dikerjakan oleh `KirimPendaftaranMerchantTugas` (antrean).
 *
 * Kenapa antrean, bukan sinkron: satu pengiriman = 5 panggilan HTTP berurutan (token, 3 unggahan, registrasi) yang
 * bisa memakan puluhan detik dan gagal di tengah jalan. Tugas antrean dapat diulang otomatis dengan aman karena
 * `KirimPendaftaranMerchantKeDoku` mencatat kemajuan (id berkas, id bisnis) sehingga langkah yang sudah berhasil tidak
 * diulang. Tenant langsung melihat status `Dikirim` dan halaman menyegarkan diri.
 */
final class AjukanPendaftaranMerchant
{
    public const AKSI_AUDIT = 'merchant-pembayaran.ajukan';

    public function __construct(
        private readonly KlienPartnerDoku $klien,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(): PendaftaranMerchantPembayaran
    {
        if (! $this->klien->CekAktif()) {
            throw new PelanggaranAturanBisnis('LayananBelumTersedia', 'Aktivasi QRIS otomatis belum tersedia. Hubungi dukungan Payoung.', statusHttp: 409);
        }

        $pendaftaran = DB::transaction(function (): PendaftaranMerchantPembayaran {
            $pendaftaran = PendaftaranMerchantPembayaran::query()->where('Penyedia', PendaftaranMerchantPembayaran::PENYEDIA_DOKU)->lockForUpdate()->first();

            if ($pendaftaran === null) {
                throw new PelanggaranAturanBisnis('PendaftaranBelumDiisi', 'Isi data pendaftaran dulu sebelum mengirim.', statusHttp: 409);
            }

            if (! $pendaftaran->Status->CekBisaDiubah() || $pendaftaran->Status !== StatusPendaftaranMerchant::Draf) {
                throw new PelanggaranAturanBisnis('PendaftaranTidakBisaDikirim', 'Pendaftaran ini sedang diproses atau sudah selesai. Untuk mengirim ulang, simpan data baru dulu.', statusHttp: 409);
            }

            if (! $this->CekIsianLengkap($pendaftaran)) {
                throw new PelanggaranAturanBisnis('IsianBelumLengkap', 'Data pemilik, usaha, dan rekening belum lengkap.', statusHttp: 409);
            }

            if (! $pendaftaran->CekBerkasLengkap() && ! $pendaftaran->CekBerkasTerunggah()) {
                throw new PelanggaranAturanBisnis('BerkasBelumLengkap', 'Foto KTP, foto selfie, dan foto tempat usaha harus diunggah semua.', statusHttp: 409);
            }

            $pendaftaran->update(['Status' => StatusPendaftaranMerchant::Dikirim, 'PesanGalat' => null]);
            $this->audit->Catat(self::AKSI_AUDIT, $pendaftaran, ['Status' => 'Draf'], ['Status' => 'Dikirim']);

            return $pendaftaran;
        });

        KirimPendaftaranMerchantTugas::dispatch($pendaftaran->IdTenant, $pendaftaran->Id)->afterCommit();

        return $pendaftaran;
    }

    private function CekIsianLengkap(PendaftaranMerchantPembayaran $pendaftaran): bool
    {
        foreach (['NamaPemilik', 'Nik', 'Email', 'NomorHp', 'NamaUsaha', 'AlamatUsaha', 'IdReferensiBank', 'NamaPemilikRekening', 'NomorRekening'] as $kolom) {
            if (blank($pendaftaran->{$kolom})) {
                return false;
            }
        }

        return true;
    }
}
