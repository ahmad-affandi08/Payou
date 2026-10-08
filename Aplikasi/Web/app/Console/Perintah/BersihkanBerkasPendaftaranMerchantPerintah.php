<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\Aksi\BersihkanBerkasPendaftaranMerchant;
use App\Domain\Integrasi\Model\PendaftaranMerchantPembayaran;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use Illuminate\Console\Command;

/**
 * Tiap jam menghapus foto KYC sementara (KTP, selfie, foto tempat usaha) yang tersimpan lebih dari 24 jam. Foto
 * normalnya sudah dihapus lebih awal, begitu terunggah ke DOKU.
 */
final class BersihkanBerkasPendaftaranMerchantPerintah extends Command
{
    protected $signature = 'pembayaran:bersihkan-berkas-pendaftaran';

    protected $description = 'Menghapus foto KYC sementara pendaftaran merchant yang melewati batas penyimpanan 24 jam.';

    public function handle(KonteksPengelola $pengelola, KonteksTenant $konteks, BersihkanBerkasPendaftaranMerchant $bersihkan): int
    {
        $sebelumnya = $konteks->Ambil();
        $dibersihkan = 0;

        try {
            $batas = now()->subHours((int) config('merchant.JamBerkasKedaluwarsa'));

            foreach ($pengelola->IdTenantDenganPekerjaan(PendaftaranMerchantPembayaran::class, fn ($q) => $q->where('BerkasDiunggahPada', '<', $batas)) as $idTenant) {
                $konteks->Atur($idTenant);
                $dibersihkan += $bersihkan->Jalankan();
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line("{$dibersihkan} pendaftaran merchant dibersihkan berkasnya.");

        return self::SUCCESS;
    }
}
