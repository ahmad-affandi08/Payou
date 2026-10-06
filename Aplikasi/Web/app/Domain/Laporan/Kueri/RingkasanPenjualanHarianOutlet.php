<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Kueri;

use App\Domain\Laporan\Model\RingkasanPenjualanHarian;
use Carbon\CarbonInterface;

/**
 * Ringkasan penjualan satu outlet untuk satu tanggal bisnis dari tabel turunan `RingkasanPenjualanHarian` (F-15
 * tutup harian menyimpan cuplikannya). Tanpa baris = nol.
 */
final class RingkasanPenjualanHarianOutlet
{
    /**
     * @return array{JumlahTransaksi: int, Bersih: string}
     */
    public function Ambil(int $idOutlet, CarbonInterface $tanggal): array
    {
        $baris = RingkasanPenjualanHarian::query()
            ->where('IdOutlet', $idOutlet)
            ->where('TanggalBisnis', $tanggal->toDateString())
            ->first(['JumlahTransaksi', 'Bersih']);

        return ['JumlahTransaksi' => $baris->JumlahTransaksi ?? 0, 'Bersih' => $baris->Bersih ?? '0.00'];
    }
}
