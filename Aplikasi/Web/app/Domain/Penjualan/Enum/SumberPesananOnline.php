<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/** Dari mana pesanan online datang: toko online `/{slug}` atau kios pesan sendiri di outlet (F-17 bagian 4). */
enum SumberPesananOnline: string
{
    case Web = 'Web';
    case Kios = 'Kios';
}
