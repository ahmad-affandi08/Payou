<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Laporan;

use App\Domain\Laporan\Layanan\PenulisCsvLaporan;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV "data mentah" dari definisi laporan (D-43): hanya header dan baris tabel, tanpa kop, supaya mudah diolah sistem
 * lain atau diimpor ulang. UTF-8 dengan BOM (Excel membaca huruf Indonesia), pemisah koma, angka desimal bertitik
 * apa adanya. Waktu ditulis `YYYY-MM-DD HH:MM:SS` di zona laporan. Anti formula injection lewat `PenulisCsvLaporan::Netralkan`.
 */
final class PenulisCsvDefinisi
{
    public static function Alirkan(DefinisiLaporan $d): StreamedResponse
    {
        return new StreamedResponse(function () use ($d): void {
            $keluaran = fopen('php://output', 'wb');

            if ($keluaran === false) {
                return;
            }

            $zona = ZonaLaporan::Buat($d->zonaWaktu);
            fwrite($keluaran, "\xEF\xBB\xBF");
            fputcsv($keluaran, array_map(fn (KolomLaporan $k): string => PenulisCsvLaporan::Netralkan($k->judul), $d->kolom), ',', '"', '');

            foreach (($d->baris)() as $isi) {
                $baris = [];

                foreach ($d->kolom as $i => $kolom) {
                    $baris[] = PenulisCsvLaporan::Netralkan(self::Teks($isi[$i] ?? null, $kolom->jenis, $zona));
                }

                fputcsv($keluaran, $baris, ',', '"', '');
            }

            fclose($keluaran);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.PenulisXlsxLaporan::AmankanNamaBerkas($d->namaBerkas).'.csv"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function Teks(mixed $nilai, JenisKolom $jenis, \DateTimeZone $zona): string
    {
        if ($nilai instanceof DateTimeInterface) {
            $lokal = DateTimeImmutable::createFromInterface($nilai)->setTimezone($zona);

            return $jenis === JenisKolom::Tanggal ? $lokal->format('Y-m-d') : $lokal->format('Y-m-d H:i:s');
        }

        return is_scalar($nilai) ? (string) $nilai : '';
    }
}
