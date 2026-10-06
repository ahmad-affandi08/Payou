<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

/**
 * Nomor dokumen untuk dibaca pelanggan (D-71): kode perangkat dibuang, misal `INV/SLB/260924/POS-001-0042` menjadi
 * `INV/SLB/260924/0042`. Nomor lengkap tetap yang tersimpan (keunikan offline per perangkat); ini hanya tampilan di
 * struk digital dan pesan. Nomor yang tidak berpola dikembalikan apa adanya.
 */
final class NomorStruk
{
    public static function Pendekkan(string $nomor): string
    {
        return preg_match('#^(.+/)[^/]*-(\d+)$#', $nomor, $cocok) === 1 ? $cocok[1].$cocok[2] : $nomor;
    }
}
