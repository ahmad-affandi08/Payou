<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Tagihan\Kueri\DaftarTagihanPlatform;
use App\Domain\Pengelola\Tagihan\Surel\PembayaranLanggananDitolak;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Keuangan/Super Admin menolak bukti transfer dengan alasan wajib (P-08 langkah 3), misal dana belum masuk, jumlah
 * kurang, atau bukti tidak terbaca. Tagihan tetap terbuka sehingga Owner bisa mengunggah bukti baru. Owner diberi
 * tahu lewat email. Idempoten: pembayaran yang sudah diverifikasi tidak bisa ditolak lagi.
 */
final class TolakPembayaranLangganan
{
    public function __construct(
        private readonly DaftarTagihanPlatform $daftar,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $verifikator, string $uuidPembayaran, string $alasan): PembayaranLangganan
    {
        $awal = $this->daftar->CariPembayaran($uuidPembayaran)
            ?? throw new PelanggaranAturanBisnis('PembayaranTidakDitemukan', 'Pembayaran tidak ditemukan.');

        [$pembayaran, $tagihan] = DB::transaction(function () use ($verifikator, $awal, $alasan): array {
            $tagihan = DaftarTagihanPlatform::KueriTagihan()->whereKey($awal->IdTagihanLangganan)->lockForUpdate()->firstOrFail();
            $pembayaran = DaftarTagihanPlatform::KueriPembayaran()->whereKey($awal->Id)->lockForUpdate()->firstOrFail();

            if ($pembayaran->Status !== StatusPembayaranLangganan::Menunggu) {
                throw new PelanggaranAturanBisnis('SudahDiverifikasi', "Pembayaran ini sudah {$pembayaran->Status->AmbilLabel()}.");
            }

            $pembayaran->update([
                'Status' => StatusPembayaranLangganan::Ditolak,
                'IdPenggunaPengelolaVerifikator' => $verifikator->Id,
                'DiverifikasiPada' => now(),
                'AlasanTolak' => $alasan,
            ]);

            $this->audit->Catat(
                'tagihan.pembayaran.tolak',
                $pembayaran,
                nilaiLama: ['Status' => StatusPembayaranLangganan::Menunggu->value],
                nilaiBaru: ['Status' => StatusPembayaranLangganan::Ditolak->value, 'NomorTagihan' => $tagihan->Nomor],
                alasan: $alasan,
                idPelaku: $verifikator->Id,
                idTenant: $tagihan->IdTenant,
            );

            return [$pembayaran, $tagihan];
        });

        $this->KirimPemberitahuan($pembayaran, $tagihan, $alasan);

        return $pembayaran;
    }

    private function KirimPemberitahuan(PembayaranLangganan $pembayaran, TagihanLangganan $tagihan, string $alasan): void
    {
        if ($pembayaran->EmailPemberitahuan === null) {
            return;
        }

        try {
            Mail::to($pembayaran->EmailPemberitahuan)->queue(new PembayaranLanggananDitolak(
                nama: $pembayaran->NamaPemberitahuan ?? 'Pemilik usaha',
                nomorTagihan: $tagihan->Nomor,
                total: $tagihan->AmbilTotal()->FormatRupiah(),
                alasan: $alasan,
            ));
        } catch (Throwable $galat) {
            Log::warning('Email penolakan pembayaran langganan gagal dikirim.', ['IdTenant' => $tagihan->IdTenant, 'Nomor' => $tagihan->Nomor, 'Galat' => $galat->getMessage()]);
        }
    }
}
