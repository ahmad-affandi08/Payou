<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/** Pilihan pertama pelanggan di kios: makan di tempat atau dibawa pulang (F-17 bagian 4). */
enum JenisSantapKios: string
{
    case MakanDiTempat = 'MakanDiTempat';
    case BawaPulang = 'BawaPulang';

    public function AmbilLabel(): string
    {
        return $this === self::MakanDiTempat ? 'Makan di sini' : 'Bawa pulang';
    }
}
