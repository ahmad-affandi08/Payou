<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Billing\GerbangBillingPlatform;
use App\Domain\Integrasi\Billing\HasilSnap;
use App\Domain\Integrasi\Billing\NomorPesananBilling;
use App\Domain\Integrasi\GerbangPembayaran\GalatGerbang;
use App\Domain\Tenant\Enum\MetodePembayaranLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owner membayar tagihan langganan lewat gerbang pembayaran platform (P-08 langkah 3, BR-P08.11).
 *
 * Alurnya dua tahap dan sengaja tidak dibungkus satu transaksi: baris pembayaran dibuat & di-commit lebih dulu,
 * baru transaksi Snap dibuat di Midtrans. Kalau panggilan HTTP ikut di dalam transaksi, kunci baris `Langganan`
 * ditahan selama jaringan lambat — dan yang lebih berbahaya, transaksi bisa dibatalkan **setelah** Midtrans
 * menerimanya, sehingga ada tagihan di gerbang yang tidak punya pasangan baris pembayaran dan uang tenant masuk
 * tanpa bisa dicocokkan.
 *
 * Tagihan hanya lunas dari notifikasi webhook bertanda tangan, bukan dari hasil popup Snap yang dilaporkan peramban.
 */
final class MulaiPembayaranGerbangLangganan
{
    public function __construct(
        private readonly GerbangBillingPlatform $gerbang,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $uuidTagihan, int $idPengguna, string $namaPengguna, string $emailPengguna): HasilSnap
    {
        if (! $this->gerbang->CekAktif()) {
            throw new PelanggaranAturanBisnis('GerbangBillingTidakAktif', 'Pembayaran online belum tersedia. Silakan transfer manual dan unggah buktinya.');
        }

        $pembayaran = DB::transaction(function () use ($uuidTagihan, $idPengguna, $namaPengguna, $emailPengguna): PembayaranLangganan {
            $awal = TagihanLangganan::query()->where('Uuid', $uuidTagihan)->first()
                ?? throw new PelanggaranAturanBisnis('TagihanTidakDitemukan', 'Tagihan tidak ditemukan.');
            // Urutan kunci Langganan → Tagihan, sama dengan unggah bukti, verifikasi, dan penjadwal tunggakan.
            Langganan::query()->where('IdTenant', $awal->IdTenant)->lockForUpdate()->first();
            $tagihan = TagihanLangganan::query()->whereKey($awal->Id)->lockForUpdate()->firstOrFail();

            if (! $tagihan->Status->CekTerbuka()) {
                throw new PelanggaranAturanBisnis('TagihanTidakTerbuka', "Tagihan {$tagihan->Nomor} sudah {$tagihan->Status->AmbilLabel()}.");
            }

            // Bukti transfer yang sedang diverifikasi didahulukan: membayar online saat itu berisiko bayar dua kali.
            $adaBuktiManual = PembayaranLangganan::query()
                ->where('IdTagihanLangganan', $tagihan->Id)
                ->where('Metode', MetodePembayaranLangganan::TransferManual->value)
                ->where('Status', StatusPembayaranLangganan::Menunggu->value)
                ->exists();

            if ($adaBuktiManual) {
                throw new PelanggaranAturanBisnis('PembayaranMasihDiverifikasi', 'Bukti transfer tagihan ini sedang diverifikasi. Tunggu hasilnya sebelum membayar online.');
            }

            // ULID dibuat di sini, bukan dibiarkan terisi otomatis, supaya nomor pesanan gerbang ikut dalam satu
            // INSERT: tidak ada celah di mana baris pembayaran ada tanpa `RefGateway` pasangannya.
            $uuid = (string) Str::ulid();
            $pembayaran = PembayaranLangganan::query()->create([
                'Uuid' => $uuid,
                'IdTenant' => $tagihan->IdTenant,
                'IdTagihanLangganan' => $tagihan->Id,
                'Metode' => MetodePembayaranLangganan::Gateway,
                'Status' => StatusPembayaranLangganan::Menunggu,
                'Jumlah' => $tagihan->Total,
                'RefGateway' => NomorPesananBilling::Buat($tagihan->IdTenant, $uuid),
                'IdPenggunaPengunggah' => $idPengguna,
                'EmailPemberitahuan' => $emailPengguna,
                'NamaPemberitahuan' => $namaPengguna,
            ]);

            $this->audit->Catat('langganan.pembayaran-gerbang-mulai', $pembayaran, nilaiBaru: [
                'NomorTagihan' => $tagihan->Nomor, 'Jumlah' => $tagihan->Total, 'RefGateway' => $pembayaran->RefGateway,
            ]);

            return $pembayaran->setRelation('TagihanLangganan', $tagihan);
        });

        return $this->BuatDiGerbang($pembayaran, $namaPengguna, $emailPengguna);
    }

    /**
     * @throws PelanggaranAturanBisnis
     */
    private function BuatDiGerbang(PembayaranLangganan $pembayaran, string $namaPengguna, string $emailPengguna): HasilSnap
    {
        $tagihan = $pembayaran->TagihanLangganan;

        try {
            return $this->gerbang->BuatTransaksiSnap(
                (string) $pembayaran->RefGateway,
                $pembayaran->AmbilJumlah(),
                "Langganan Payoung {$tagihan->Nomor}",
                $namaPengguna,
                $emailPengguna,
            );
        } catch (GalatGerbang $galat) {
            // Hanya galat yang pasti tidak membuat apa pun di gerbang boleh ditutup. Yang `tidakPasti` dibiarkan
            // `Menunggu` supaya notifikasi webhook yang mungkin menyusul masih menemukan pasangannya.
            if (! $galat->tidakPasti) {
                $pembayaran->update([
                    'Status' => StatusPembayaranLangganan::Ditolak,
                    'AlasanTolak' => 'Gerbang pembayaran menolak permintaan: '.$galat->getMessage(),
                ]);
            }

            throw new PelanggaranAturanBisnis('GerbangMenolak', $galat->getMessage());
        }
    }
}
