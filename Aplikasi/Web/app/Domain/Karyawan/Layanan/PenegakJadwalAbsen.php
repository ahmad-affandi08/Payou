<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Layanan;

use App\Domain\Karyawan\Kueri\AturanKehadiranTenant;
use App\Domain\Karyawan\Model\JadwalKerja;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use Carbon\CarbonImmutable;

/**
 * Menilai apakah sebuah absen masuk sesuai jadwal kerja (F-18 bagian 5, D-44). Hanya berlaku bila aturan tenant
 * `WajibJadwal` aktif; selain itu tidak ada penolakan (perilaku lama). Absen masuk sah bila ada jadwal karyawan itu di
 * **outlet yang sama** dengan jendela `[JamMulai − MasukPalingAwalMenit, JamSelesai]` yang memuat waktu masuk. Jadwal
 * tanggal kemarin ikut diperiksa untuk shift yang melewati tengah malam. Dibaca dalam konteks tenant.
 */
final class PenegakJadwalAbsen
{
    public function __construct(
        private readonly AturanKehadiranTenant $aturan,
        private readonly ZonaWaktuOutlet $zona,
    ) {}

    /**
     * @return array{Kode: string, Pesan: string}|null null = boleh masuk (atau aturan tidak aktif)
     */
    public function Periksa(int $idKaryawan, int $idOutlet, CarbonImmutable $masukUtc): ?array
    {
        $aturan = $this->aturan->Ambil();

        if (! $aturan['WajibJadwal']) {
            return null;
        }

        $lokal = $masukUtc->utc()->addSeconds($this->zona->AmbilSelisihDetik([$idOutlet])[$idOutlet] ?? 25200);
        $jadwal = JadwalKerja::query()
            ->where('IdKaryawan', $idKaryawan)
            ->whereIn('Tanggal', [$lokal->subDay()->toDateString(), $lokal->toDateString()])
            ->orderBy('Tanggal')
            ->get();

        if ($jadwal->isEmpty()) {
            return ['Kode' => 'TidakAdaJadwal', 'Pesan' => 'Anda tidak punya jadwal kerja hari ini. Hubungi pengelola untuk dijadwalkan.'];
        }

        $terlaluAwal = null;
        $berakhir = null;
        $diOutletLain = null;

        foreach ($jadwal as $j) {
            $batas = PenilaiKehadiran::AmbilBatasShift($j->Tanggal->toDateString(), $j->JamMulai, $j->JamSelesai);
            $dibuka = $batas['Mulai']->subMinutes($aturan['MasukPalingAwalMenit']);

            if ($lokal->lessThan($dibuka)) {
                $terlaluAwal = $j;
            } elseif ($lokal->greaterThan($batas['Selesai'])) {
                $berakhir = $j;
            } elseif ($j->IdOutlet !== $idOutlet) {
                $diOutletLain = $j;
            } else {
                return null;
            }
        }

        if ($diOutletLain !== null) {
            return ['Kode' => 'JadwalDiOutletLain', 'Pesan' => 'Jadwal Anda saat ini ada di outlet lain. Absen di outlet yang dijadwalkan.'];
        }

        if ($terlaluAwal !== null) {
            return ['Kode' => 'TerlaluAwal', 'Pesan' => "Shift Anda mulai {$terlaluAwal->JamMulai}. Absen masuk baru dibuka {$aturan['MasukPalingAwalMenit']} menit sebelumnya."];
        }

        return ['Kode' => 'ShiftSudahBerakhir', 'Pesan' => "Shift Anda sudah berakhir pukul {$berakhir?->JamSelesai}. Hubungi pengelola bila Anda tetap bekerja."];
    }
}
