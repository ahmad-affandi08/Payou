<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use App\Domain\Penjualan\Aksi\KedaluwarsakanPesananOnline;
use App\Domain\Penjualan\Model\PesananOnline;
use Illuminate\Console\Command;

/**
 * F-17 toko online: tiap lima menit menghanguskan pesanan online yang tidak pernah dikonfirmasi staf sampai
 * `PengaturanTokoOnline.MenitKedaluwarsa`. Per tenant dengan `KonteksTenant` diatur sehingga semua kueri tetap
 * lewat scope `MilikTenant`.
 */
final class KedaluwarsakanPesananOnlinePerintah extends Command
{
    protected $signature = 'pesanan-online:kedaluwarsa';

    protected $description = 'Menghanguskan pesanan online yang tidak dikonfirmasi sampai batas waktu toko (F-17).';

    public function handle(KonteksPengelola $pengelola, KonteksTenant $konteks, KedaluwarsakanPesananOnline $kedaluwarsa): int
    {
        $sebelumnya = $konteks->Ambil();
        $jumlah = 0;

        try {
            // Hanya tenant yang punya pesanan online belum dibayar dan belum final (audit kinerja skala besar).
            foreach ($pengelola->IdTenantDenganPekerjaan(PesananOnline::class, fn ($q) => $q->whereIn('Status', array_keys(KedaluwarsakanPesananOnline::BATAS))->whereNull('DibayarPada')) as $idTenant) {
                $konteks->Atur($idTenant);
                $jumlah += $kedaluwarsa->Jalankan();
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line("{$jumlah} pesanan online dihanguskan.");

        return self::SUCCESS;
    }
}
