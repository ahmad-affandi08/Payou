<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Whatsapp\PembuatPengirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Penjualan\Enum\JenisPesanKeluar;
use App\Domain\Penjualan\Enum\KanalPesanKeluar;
use App\Domain\Penjualan\Enum\StatusPesanKeluar;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PesanKeluar;
use App\Domain\Penjualan\Tugas\KirimStrukDigitalTugas;
use App\Domain\Tenant\Kueri\PengaturanStrukTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * K3: kasir mengirim struk digital (`/s/{kodeStruk}`) ke WhatsApp atau email pelanggan dari POS. Penjualan harus sudah
 * tersinkron (404 `PenjualanBelumTersinkron`), struk digital tenant aktif (409 `StrukDigitalNonaktif`), kanal siap
 * (409 `WhatsappBelumAktif`: penyedia P-05 tidak aktif atau fitur `integrasi.whatsapp` tidak dimiliki;
 * 409 `EmailBelumAktif`: kanal email ditutup sejak D-33 — notifikasi tenant hanya lewat WhatsApp; nilai `Email` tetap
 * diterima validasi supaya aplikasi kasir lama mendapat pesan yang jelas), tujuan sah (422 `TujuanTidakValid`), paling banyak
 * `MAKSIMAL_PER_PENJUALAN` kiriman per penjualan (429 `BatasKirimStrukTercapai`). Baris `PesanKeluar` dibuat lalu
 * `KirimStrukDigitalTugas` diantrekan setelah commit (aturan #10). Idempoten menurut `Uuid` perangkat: kiriman ulang
 * mengembalikan status terkini tanpa mengantrekan lagi.
 */
final class AntrekanKirimStrukDigital
{
    public const MAKSIMAL_PER_PENJUALAN = 5;

    public const POLA_NOMOR = '/^628\d{7,12}$/';

    public function __construct(
        private readonly PengaturanStrukTenant $pengaturanStruk,
        private readonly PemeriksaFiturTenant $fitur,
        private readonly PembuatPengirimWhatsapp $whatsapp,
        private readonly OutletPenjualan $outletPenjualan,
    ) {}

    /**
     * @return array{PesanKeluar: PesanKeluar, Baru: bool}
     */
    public function Jalankan(int $idTenant, int $idOutlet, int $idPerangkat, string $uuidPenjualan, string $uuid, KanalPesanKeluar $kanal, string $tujuan): array
    {
        $ada = PesanKeluar::query()->where('Uuid', $uuid)->first();

        if ($ada !== null) {
            return ['PesanKeluar' => $ada, 'Baru' => false];
        }

        $tujuan = self::RapikanTujuan($kanal, $tujuan)
            ?? throw new PelanggaranAturanBisnis('TujuanTidakValid', $kanal === KanalPesanKeluar::Whatsapp
                ? 'Nomor WhatsApp tidak valid. Pakai nomor HP Indonesia, misal 0812 3456 7890.'
                : 'Alamat email tidak valid.', 'Tujuan');

        if (! Penjualan::query()->where('Uuid', $uuidPenjualan)->exists()) {
            throw new PelanggaranAturanBisnis('PenjualanBelumTersinkron', 'Penjualan belum tersinkron ke server. Sinkronkan dulu, lalu kirim ulang struk.', 'Umum', 404);
        }

        if (! $this->pengaturanStruk->Ambil($idTenant, $this->outletPenjualan->Ambil($idOutlet, $idPerangkat)?->idOutlet)->tampilkanStrukDigital) {
            throw new PelanggaranAturanBisnis('StrukDigitalNonaktif', 'Struk digital dimatikan di pengaturan struk.', 'Umum', 409);
        }

        $this->PastikanKanalAktif($idTenant, $kanal);

        try {
            return DB::transaction(function () use ($idTenant, $idOutlet, $idPerangkat, $uuidPenjualan, $uuid, $kanal, $tujuan): array {
                $penjualan = Penjualan::query()->where('Uuid', $uuidPenjualan)->lockForUpdate()->firstOrFail();
                $jumlah = PesanKeluar::query()
                    ->where('Jenis', JenisPesanKeluar::StrukDigital->value)
                    ->where('IdReferensi', $penjualan->Id)
                    ->count();

                if ($jumlah >= self::MAKSIMAL_PER_PENJUALAN) {
                    throw new PelanggaranAturanBisnis('BatasKirimStrukTercapai', 'Struk penjualan ini sudah dikirim '.self::MAKSIMAL_PER_PENJUALAN.' kali. Tunjukkan QR struk digital kepada pelanggan.', 'Umum', 429);
                }

                $pesan = PesanKeluar::query()->create([
                    'Uuid' => $uuid,
                    'IdOutlet' => $idOutlet,
                    'IdPerangkat' => $idPerangkat,
                    'Kanal' => $kanal,
                    'Jenis' => JenisPesanKeluar::StrukDigital,
                    'IdReferensi' => $penjualan->Id,
                    'Tujuan' => $tujuan,
                    'Status' => StatusPesanKeluar::Diantrekan,
                    'Percobaan' => 0,
                ]);

                KirimStrukDigitalTugas::dispatch($idTenant, $pesan->Id)->afterCommit();

                return ['PesanKeluar' => $pesan, 'Baru' => true];
            });
        } catch (UniqueConstraintViolationException) {
            // Dua kiriman Uuid sama bersamaan: yang kalah mengembalikan baris pemenang.
            return ['PesanKeluar' => PesanKeluar::query()->where('Uuid', $uuid)->firstOrFail(), 'Baru' => false];
        }
    }

    /** Nomor HP Indonesia → `628…`; email → huruf kecil. Null bila tidak sah. */
    public static function RapikanTujuan(KanalPesanKeluar $kanal, string $tujuan): ?string
    {
        $tujuan = trim($tujuan);

        if ($kanal === KanalPesanKeluar::Whatsapp) {
            $nomor = PesanWhatsapp::RapikanNomor($tujuan);

            return preg_match(self::POLA_NOMOR, $nomor) === 1 ? $nomor : null;
        }

        $email = mb_strtolower($tujuan);

        return mb_strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    public function CekKanalAktif(int $idTenant, KanalPesanKeluar $kanal): bool
    {
        return match ($kanal) {
            KanalPesanKeluar::Whatsapp => $this->whatsapp->AmbilAktif() !== null && $this->fitur->CekAktif($idTenant, PemeriksaFiturTenant::KUNCI_WHATSAPP),
            // D-33 (PRD v4.05): notifikasi tenant ke pelanggan hanya lewat WhatsApp.
            KanalPesanKeluar::Email => false,
        };
    }

    private function PastikanKanalAktif(int $idTenant, KanalPesanKeluar $kanal): void
    {
        if ($this->CekKanalAktif($idTenant, $kanal)) {
            return;
        }

        throw $kanal === KanalPesanKeluar::Whatsapp
            ? new PelanggaranAturanBisnis('WhatsappBelumAktif', 'Kirim struk lewat WhatsApp belum aktif untuk usaha ini.', 'Kanal', 409)
            : new PelanggaranAturanBisnis('EmailBelumAktif', 'Kirim struk lewat email tidak tersedia. Kirim lewat WhatsApp atau tunjukkan QR struk digital.', 'Kanal', 409);
    }
}
