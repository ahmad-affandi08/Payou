<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Kueri;

use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Model\JadwalKerja;
use App\Domain\Karyawan\Model\Karyawan;

/**
 * F-07 mode service: staf aktif yang punya jadwal kerja di outlet pada satu tanggal (dasar slot reservasi), urut nama.
 * Jam dari `JadwalKerja` (HH:MM); jadwal lewat tengah malam dipotong di 23:59.
 */
final class JadwalStafReservasi
{
    /**
     * @return list<array{Id: int, Uuid: string, Nama: string, JamMulai: string, JamSelesai: string}>
     */
    public function Ambil(int $idOutlet, string $tanggal): array
    {
        $jadwal = JadwalKerja::query()
            ->where('IdOutlet', $idOutlet)
            ->where('Tanggal', $tanggal)
            ->get(['IdKaryawan', 'JamMulai', 'JamSelesai'])
            ->keyBy('IdKaryawan');

        if ($jadwal->isEmpty()) {
            return [];
        }

        return array_values(Karyawan::query()
            ->whereIn('Id', $jadwal->keys()->all())
            ->where('Status', StatusKaryawan::Aktif->value)
            ->orderBy('Nama')
            ->get(['Id', 'Uuid', 'Nama'])
            ->map(function (Karyawan $k) use ($jadwal): array {
                $j = $jadwal->get($k->Id);
                $mulai = substr((string) $j?->JamMulai, 0, 5);
                $selesai = substr((string) $j?->JamSelesai, 0, 5);

                return [
                    'Id' => $k->Id,
                    'Uuid' => $k->Uuid,
                    'Nama' => $k->Nama,
                    'JamMulai' => $mulai,
                    'JamSelesai' => $selesai <= $mulai ? '23:59' : $selesai,
                ];
            })->all());
    }

    /**
     * Staf aktif menurut Uuid (pilihan staf di formulir), atau null.
     *
     * @return array{Id: int, Uuid: string, Nama: string}|null
     */
    public function CariStaf(string $uuid): ?array
    {
        $k = Karyawan::query()->where('Uuid', $uuid)->where('Status', StatusKaryawan::Aktif->value)->first(['Id', 'Uuid', 'Nama']);

        return $k === null ? null : ['Id' => $k->Id, 'Uuid' => $k->Uuid, 'Nama' => $k->Nama];
    }

    /**
     * @param  list<int>  $id
     * @return array<int, array{Uuid: string, Nama: string}>
     */
    public function AmbilRingkas(array $id): array
    {
        $hasil = [];

        foreach (Karyawan::query()->whereIn('Id', $id)->get(['Id', 'Uuid', 'Nama']) as $k) {
            $hasil[$k->Id] = ['Uuid' => $k->Uuid, 'Nama' => $k->Nama];
        }

        return $hasil;
    }

    /**
     * Staf aktif (pilihan saring & formulir), urut nama.
     *
     * @return list<array{Uuid: string, Nama: string}>
     */
    public function AmbilOpsi(): array
    {
        return array_values(Karyawan::query()->where('Status', StatusKaryawan::Aktif->value)->orderBy('Nama')->get(['Uuid', 'Nama'])
            ->map(fn (Karyawan $k): array => ['Uuid' => $k->Uuid, 'Nama' => $k->Nama])->all());
    }
}
