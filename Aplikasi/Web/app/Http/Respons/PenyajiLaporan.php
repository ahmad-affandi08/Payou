<?php

declare(strict_types=1);

namespace App\Http\Respons;

use App\Domain\Bersama\Laporan\DefinisiLaporan;
use App\Domain\Bersama\Laporan\FormatLaporanId;
use App\Domain\Bersama\Laporan\JenisKolom;
use App\Domain\Bersama\Laporan\PenulisCsvDefinisi;
use App\Domain\Bersama\Laporan\PenulisXlsxLaporan;
use App\Domain\Bersama\Laporan\ZonaLaporan;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyajikan satu `DefinisiLaporan` sebagai Excel, CSV (data mentah), atau halaman cetak/PDF sesuai `?format=` (D-43).
 * Tanpa `format` = CSV, agar tautan ekspor lama tetap bekerja; antarmuka selalu menyebut format dengan jelas.
 */
final class PenyajiLaporan
{
    public const FORMAT_XLSX = 'xlsx';

    public const FORMAT_CSV = 'csv';

    public const FORMAT_CETAK = 'cetak';

    /** Halaman cetak memuat semua baris di peramban; di atas batas ini disarankan Excel. */
    public const MAKS_BARIS_CETAK = 3000;

    public static function Sajikan(Request $permintaan, DefinisiLaporan $definisi): Response
    {
        return match (self::AmbilFormat($permintaan)) {
            self::FORMAT_XLSX => PenulisXlsxLaporan::Alirkan($definisi),
            self::FORMAT_CETAK => self::Cetak($permintaan, $definisi),
            default => PenulisCsvDefinisi::Alirkan($definisi),
        };
    }

    public static function AmbilFormat(Request $permintaan): string
    {
        $format = $permintaan->query('format');

        return in_array($format, [self::FORMAT_XLSX, self::FORMAT_CSV, self::FORMAT_CETAK], true) ? $format : self::FORMAT_CSV;
    }

    private static function Cetak(Request $permintaan, DefinisiLaporan $d): Response
    {
        $baris = [];
        $jumlahBaris = 0;
        $terpotong = false;
        $jumlahKolom = count($d->kolom);
        /** @var array<int, BigDecimal> $total */
        $total = [];

        foreach (($d->baris)() as $isi) {
            // Jumlah dihitung dari semua baris walau yang tampil dibatasi, supaya angka kaki tetap benar.
            foreach ($d->kolom as $i => $kolom) {
                $nilai = $isi[$i] ?? null;

                if ($kolom->jumlahkan && $kolom->jenis->CekAngka() && (is_int($nilai) || (is_string($nilai) && preg_match('/^-?\d+(\.\d+)?$/', $nilai) === 1))) {
                    $total[$i] = ($total[$i] ?? BigDecimal::zero())->plus(BigDecimal::of($nilai));
                }
            }

            if ($jumlahBaris >= self::MAKS_BARIS_CETAK) {
                $terpotong = true;

                continue;
            }

            $barisTampil = [];

            foreach ($d->kolom as $i => $kolom) {
                $barisTampil[] = FormatLaporanId::Nilai($isi[$i] ?? null, $kolom->jenis, $d->zonaWaktu);
            }

            $baris[] = $barisTampil;
            $jumlahBaris++;
        }

        $dibuat = $d->dibuatPada ?? new DateTimeImmutable('now');
        $logo = $d->logo !== null && strlen($d->logo) <= 1_000_000 && ($tipe = (new \finfo(FILEINFO_MIME_TYPE))->buffer($d->logo)) !== false && str_starts_with($tipe, 'image/')
            ? 'data:'.$tipe.';base64,'.base64_encode($d->logo)
            : null;

        return Inertia::render('Kelola/Laporan/Cetak', [
            'Judul' => $d->judul,
            'NamaUsaha' => $d->namaUsaha,
            'Cakupan' => $d->cakupan,
            'Logo' => $logo,
            'Saringan' => [...array_map(fn (array $s): array => ['Label' => $s[0], 'Nilai' => $s[1]], $d->saringan), ['Label' => 'Zona Waktu', 'Nilai' => ZonaLaporan::Label($d->zonaWaktu)]],
            'Ringkasan' => array_map(fn ($item): array => [
                'Label' => $item->label,
                'Nilai' => FormatLaporanId::Nilai($item->nilai, $item->jenis, $d->zonaWaktu),
            ], $d->ringkasan),
            'Kolom' => array_map(fn ($k): array => ['Judul' => $k->judul, 'Rata' => in_array($k->jenis, [JenisKolom::Teks], true) ? 'kiri' : ($k->jenis->CekAngka() ? 'kanan' : 'tengah')], $d->kolom),
            'Baris' => $baris,
            'Jumlah' => $d->AdaJumlah()
                ? array_map(fn ($k, int $i): string => $k->jumlahkan && $k->jenis->CekAngka() ? FormatLaporanId::Nilai((string) ($total[$i] ?? BigDecimal::zero()), $k->jenis, $d->zonaWaktu) : ($i === 0 ? 'Jumlah' : ''), $d->kolom, array_keys($d->kolom))
                : null,
            'Terpotong' => $terpotong,
            'MaksBaris' => self::MAKS_BARIS_CETAK,
            'DibuatPada' => ZonaLaporan::Jejak($dibuat, $d->zonaWaktu),
            'DataTerakhir' => $d->dataTerakhir === null ? null : ZonaLaporan::Jejak($d->dataTerakhir, $d->zonaWaktu),
            'JumlahKolom' => $jumlahKolom,
        ])->toResponse($permintaan);
    }
}
