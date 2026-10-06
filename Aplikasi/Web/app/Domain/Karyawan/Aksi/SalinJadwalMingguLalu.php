<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Model\JadwalKerja;
use App\Domain\Karyawan\Model\Karyawan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Salin jadwal minggu sebelumnya ke minggu [senin] di outlet yang sama (F-18, izin `karyawan.kelola`): hanya karyawan
 * aktif dan hanya hari yang masih kosong (jadwal yang sudah ada tidak ditimpa). Audit `jadwal-kerja.salin`.
 */
final class SalinJadwalMingguLalu
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(int $idOutlet, CarbonImmutable $senin, int $idPengguna): int
    {
        return DB::transaction(function () use ($idOutlet, $senin, $idPengguna): int {
            $aktif = Karyawan::query()->where('Status', StatusKaryawan::Aktif->value)->pluck('Id')->all();
            $lalu = JadwalKerja::query()
                ->where('IdOutlet', $idOutlet)
                ->whereIn('IdKaryawan', $aktif)
                ->whereBetween('Tanggal', [$senin->subDays(7)->toDateString(), $senin->subDay()->toDateString()])
                ->get();
            $disalin = 0;

            foreach ($lalu as $j) {
                $tanggal = CarbonImmutable::parse($j->Tanggal->toDateString())->addDays(7)->toDateString();

                if (JadwalKerja::query()->where('IdKaryawan', $j->IdKaryawan)->where('Tanggal', $tanggal)->exists()) {
                    continue;
                }

                JadwalKerja::query()->create([
                    'IdKaryawan' => $j->IdKaryawan,
                    'IdOutlet' => $idOutlet,
                    'Tanggal' => $tanggal,
                    'JamMulai' => $j->JamMulai,
                    'JamSelesai' => $j->JamSelesai,
                ]);
                $disalin++;
            }

            if ($disalin > 0) {
                $this->audit->Catat('jadwal-kerja.salin', null, nilaiBaru: ['IdOutlet' => $idOutlet, 'Senin' => $senin->toDateString(), 'Jumlah' => $disalin], idPengguna: $idPengguna);
            }

            return $disalin;
        }, 3);
    }
}
