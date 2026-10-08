<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Billing;

/**
 * Transaksi DOKU Checkout yang sudah dibuat untuk satu tagihan langganan. `urlBayar` adalah halaman bayar DOKU tempat
 * peramban tenant diarahkan; hasil pembayarannya tidak pernah dibaca dari sini, hanya dari notifikasi/status DOKU.
 */
final class HasilPembayaranBilling
{
    public function __construct(
        public readonly string $urlBayar,
    ) {}
}
