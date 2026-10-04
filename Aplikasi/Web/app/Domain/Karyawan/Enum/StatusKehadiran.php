<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Enum;

/**
 * Status kehadiran satu absensi terhadap jadwalnya (F-18, dihitung saat dibaca): `TepatWaktu`, `Terlambat` (masuk lewat
 * `JamMulai` + toleransi), `PulangCepat` (keluar sebelum `JamSelesai` − toleransi), `Lembur` (keluar jauh setelah
 * `JamSelesai`), `TanpaJadwal`, dan `BelumKeluar` (belum absen keluar). Bila beberapa berlaku, yang paling merugikan
 * ditampilkan (Terlambat, Pulang cepat, Lembur); angka menitnya tetap dilaporkan terpisah.
 */
enum StatusKehadiran: string
{
    case TepatWaktu = 'TepatWaktu';
    case Terlambat = 'Terlambat';
    case PulangCepat = 'PulangCepat';
    case Lembur = 'Lembur';
    case TanpaJadwal = 'TanpaJadwal';
    case BelumKeluar = 'BelumKeluar';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::TepatWaktu => 'Tepat waktu',
            self::Terlambat => 'Terlambat',
            self::PulangCepat => 'Pulang cepat',
            self::Lembur => 'Lembur',
            self::TanpaJadwal => 'Tanpa jadwal',
            self::BelumKeluar => 'Belum keluar',
        };
    }
}
