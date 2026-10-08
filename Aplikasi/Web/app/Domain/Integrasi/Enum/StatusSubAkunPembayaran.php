<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Enum;

/**
 * Status sub account pembayaran tenant. `Menunggu` = diminta tetapi belum aktif di penyedia (atau pembuatan belum
 * pasti berhasil); `Gagal` = penyedia menolak permintaan (galat 4xx pasti); `Dinonaktifkan` = dimatikan manual.
 */
enum StatusSubAkunPembayaran: string
{
    case Menunggu = 'Menunggu';
    case Aktif = 'Aktif';
    case Gagal = 'Gagal';
    case Dinonaktifkan = 'Dinonaktifkan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Aktif => 'Aktif',
            self::Gagal => 'Gagal',
            self::Dinonaktifkan => 'Dinonaktifkan',
        };
    }
}
