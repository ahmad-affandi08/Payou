<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Laporan;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Format tampilan Indonesia untuk halaman cetak laporan (titik ribuan, koma desimal), langsung pada teks desimal
 * tanpa melewati float (aturan emas #7).
 */
final class FormatLaporanId
{
    public static function Nilai(mixed $nilai, JenisKolom $jenis, string $zonaWaktu): string
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        if ($jenis === JenisKolom::Tanggal || $jenis === JenisKolom::TanggalWaktu) {
            return self::Waktu($nilai, $jenis === JenisKolom::TanggalWaktu, $zonaWaktu);
        }

        $teks = is_scalar($nilai) ? (string) $nilai : '';

        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $teks, $m) !== 1) {
            return $teks;
        }

        $tanda = $m[1] === '-' && trim($teks, '-0.') !== '' ? '−' : '';
        $bulat = self::Ribuan(ltrim($m[2], '0') === '' ? '0' : ltrim($m[2], '0'));
        $pecahan = $m[3] ?? '';

        return match ($jenis) {
            JenisKolom::Uang => $tanda.'Rp '.$bulat.self::PecahanUang($pecahan),
            JenisKolom::Bilangan => $tanda.$bulat,
            JenisKolom::Kuantitas => $tanda.$bulat.self::PecahanRingkas($pecahan),
            JenisKolom::Persen => $tanda.$bulat.self::PecahanRingkas($pecahan).'%',
            default => $teks,
        };
    }

    private static function Ribuan(string $bulat): string
    {
        return (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $bulat);
    }

    private static function PecahanUang(string $pecahan): string
    {
        $dua = substr(str_pad($pecahan, 2, '0'), 0, 2);

        return $dua === '00' ? '' : ','.$dua;
    }

    private static function PecahanRingkas(string $pecahan): string
    {
        $ringkas = rtrim($pecahan, '0');

        return $ringkas === '' ? '' : ','.$ringkas;
    }

    private static function Waktu(mixed $nilai, bool $denganJam, string $zonaWaktu): string
    {
        try {
            $zona = ZonaLaporan::Buat($zonaWaktu);
            $waktu = $nilai instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($nilai)->setTimezone($zona)
                : new DateTimeImmutable(is_scalar($nilai) ? (string) $nilai : '', $zona);
        } catch (Throwable) {
            return is_scalar($nilai) ? (string) $nilai : '';
        }

        return $waktu->format($denganJam ? 'd-m-Y H:i:s' : 'd-m-Y');
    }
}
