<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Laporan;

/**
 * Jenis nilai kolom/ringkasan pada ekspor laporan. Menentukan format sel Excel, perataan, dan lebar bawaan.
 * Uang & kuantitas selalu diterima sebagai teks desimal dan ditulis apa adanya ke sel (tanpa float, aturan emas #7).
 */
enum JenisKolom: string
{
    case Teks = 'Teks';
    case Uang = 'Uang';
    case Bilangan = 'Bilangan';
    case Kuantitas = 'Kuantitas';
    /** Angka persen apa adanya (12.5 = 12,5%), bukan pecahan. */
    case Persen = 'Persen';
    case Tanggal = 'Tanggal';
    case TanggalWaktu = 'TanggalWaktu';

    public function LebarBawaan(): int
    {
        return match ($this) {
            self::Teks => 26,
            self::Uang => 20,
            self::Bilangan, self::Kuantitas => 14,
            self::Persen => 11,
            self::Tanggal => 14,
            self::TanggalWaktu => 21,
        };
    }

    public function CekAngka(): bool
    {
        return in_array($this, [self::Uang, self::Bilangan, self::Kuantitas, self::Persen], true);
    }
}
