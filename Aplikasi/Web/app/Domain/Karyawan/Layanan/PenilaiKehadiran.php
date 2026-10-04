<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Layanan;

use Carbon\CarbonImmutable;

/**
 * Menilai satu absensi terhadap jadwal kerjanya (F-18 bagian 5, D-44). Fungsi murni: semua waktu memakai jam dinding
 * outlet (dibaca sebagai UTC supaya selisih tidak bergantung zona), jadwal `HH:mm` pada `$tanggal`; `JamSelesai` ≤
 * `JamMulai` = shift melewati tengah malam. Hasil dalam menit bulat (tanpa float).
 *
 * - **Terlambat**: masuk setelah `JamMulai` + toleransi; nilainya selisih penuh dari `JamMulai`.
 * - **Pulang cepat**: keluar sebelum `JamSelesai` − toleransi; nilainya selisih penuh dari `JamSelesai`.
 * - **Lembur**: keluar setelah `JamSelesai` + ambang lembur; nilainya selisih penuh dari `JamSelesai`.
 */
final class PenilaiKehadiran
{
    /**
     * @return array{Mulai: CarbonImmutable, Selesai: CarbonImmutable}
     */
    public static function AmbilBatasShift(string $tanggal, string $jamMulai, string $jamSelesai): array
    {
        $mulai = CarbonImmutable::parse("{$tanggal} {$jamMulai}:00", 'UTC');
        $selesai = CarbonImmutable::parse("{$tanggal} {$jamSelesai}:00", 'UTC');

        return ['Mulai' => $mulai, 'Selesai' => $selesai->lessThanOrEqualTo($mulai) ? $selesai->addDay() : $selesai];
    }

    /**
     * @return array{TerlambatMenit: int, PulangCepatMenit: int, LemburMenit: int}
     */
    public function Nilai(
        string $tanggal,
        string $jamMulai,
        string $jamSelesai,
        CarbonImmutable $masuk,
        ?CarbonImmutable $keluar,
        int $toleransiTerlambat,
        int $toleransiPulangCepat,
        int $lemburSetelah,
    ): array {
        $batas = self::AmbilBatasShift($tanggal, $jamMulai, $jamSelesai);
        $terlambat = intdiv($masuk->getTimestamp() - $batas['Mulai']->getTimestamp(), 60);
        $pulangCepat = 0;
        $lembur = 0;

        if ($keluar !== null) {
            $sebelum = intdiv($batas['Selesai']->getTimestamp() - $keluar->getTimestamp(), 60);
            $sesudah = -$sebelum;
            $pulangCepat = $sebelum > $toleransiPulangCepat ? $sebelum : 0;
            $lembur = $sesudah > 0 && $sesudah >= max(1, $lemburSetelah) ? $sesudah : 0;
        }

        return [
            'TerlambatMenit' => $terlambat > $toleransiTerlambat ? $terlambat : 0,
            'PulangCepatMenit' => $pulangCepat,
            'LemburMenit' => $lembur,
        ];
    }
}
