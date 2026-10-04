<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Laporan;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Penamaan zona waktu untuk kop laporan: "Asia/Jakarta (GMT +7)" dan singkatan WIB/WITA/WIT untuk jejak waktu.
 */
final class ZonaLaporan
{
    public static function Buat(string $zonaWaktu): DateTimeZone
    {
        try {
            return new DateTimeZone($zonaWaktu);
        } catch (Throwable) {
            return new DateTimeZone('Asia/Jakarta');
        }
    }

    public static function Label(string $zonaWaktu): string
    {
        $zona = self::Buat($zonaWaktu);

        return $zona->getName().' (GMT '.self::OffsetTeks($zona).')';
    }

    public static function Singkatan(string $zonaWaktu): string
    {
        $zona = self::Buat($zonaWaktu);

        return match ($zona->getName()) {
            'Asia/Jakarta', 'Asia/Pontianak' => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => 'GMT'.self::OffsetTeks($zona),
        };
    }

    /** "04/10/2026 - 22:37:16 WIB", sesuai jejak waktu di kop. */
    public static function Jejak(DateTimeInterface $waktu, string $zonaWaktu): string
    {
        $lokal = DateTimeImmutable::createFromInterface($waktu)->setTimezone(self::Buat($zonaWaktu));

        return $lokal->format('d/m/Y - H:i:s').' '.self::Singkatan($zonaWaktu);
    }

    private static function OffsetTeks(DateTimeZone $zona): string
    {
        $detik = $zona->getOffset(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $tanda = $detik < 0 ? '-' : '+';
        $mutlak = abs($detik);
        $jam = intdiv($mutlak, 3600);
        $menit = intdiv($mutlak % 3600, 60);

        return $tanda.$jam.($menit > 0 ? ':'.sprintf('%02d', $menit) : '');
    }
}
