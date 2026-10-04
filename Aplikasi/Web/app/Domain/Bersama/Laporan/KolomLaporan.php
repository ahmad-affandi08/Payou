<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Laporan;

/**
 * Satu kolom tabel laporan. `jumlahkan` menambah baris "Jumlah" di kaki tabel (hanya kolom angka).
 */
final readonly class KolomLaporan
{
    public function __construct(
        public string $judul,
        public JenisKolom $jenis = JenisKolom::Teks,
        public ?int $lebar = null,
        public bool $jumlahkan = false,
    ) {}

    public function AmbilLebar(): int
    {
        return $this->lebar ?? $this->jenis->LebarBawaan();
    }
}
