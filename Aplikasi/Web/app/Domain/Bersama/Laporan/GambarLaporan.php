<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Laporan;

/**
 * Menyiapkan gambar untuk kop/kaki Excel: apa pun formatnya (PNG, JPEG, WebP, GIF) dijadikan PNG berukuran kecil
 * (tinggi tetap) agar berkas tetap ringan dan dibaca semua versi Excel/LibreOffice. Gagal membaca = tanpa gambar,
 * laporan tetap jadi.
 */
final class GambarLaporan
{
    private const BATAS_BYTE = 5_242_880;

    private const BATAS_SISI = 5000;

    /**
     * @return array{Png: string, Lebar: int, Tinggi: int}|null
     */
    public static function Siapkan(?string $isi, int $tinggiTarget): ?array
    {
        if ($isi === null || $isi === '' || strlen($isi) > self::BATAS_BYTE || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $info = @getimagesizefromstring($isi);

        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] > self::BATAS_SISI || $info[1] > self::BATAS_SISI) {
            return null;
        }

        $sumber = @imagecreatefromstring($isi);

        if ($sumber === false) {
            return null;
        }

        $tinggi = max(1, min($tinggiTarget, $info[1]));
        $lebar = max(1, intdiv($info[0] * $tinggi, $info[1]));
        $hasil = imagecreatetruecolor($lebar, $tinggi);

        if ($hasil === false) {
            return null;
        }

        imagealphablending($hasil, false);
        imagesavealpha($hasil, true);
        imagefill($hasil, 0, 0, (int) imagecolorallocatealpha($hasil, 255, 255, 255, 127));
        imagecopyresampled($hasil, $sumber, 0, 0, 0, 0, $lebar, $tinggi, $info[0], $info[1]);

        ob_start();
        imagepng($hasil);
        $png = (string) ob_get_clean();

        return $png === '' ? null : ['Png' => $png, 'Lebar' => $lebar, 'Tinggi' => $tinggi];
    }

    /**
     * Logo PAYOU untuk kaki "Dibuat dengan PAYOU".
     *
     * @return array{Png: string, Lebar: int, Tinggi: int}|null
     */
    public static function LogoPayou(int $tinggiTarget): ?array
    {
        $path = resource_path('js/Aset/Merek/LogoHorizontal.webp');

        return is_file($path) ? self::Siapkan((string) file_get_contents($path), $tinggiTarget) : null;
    }
}
