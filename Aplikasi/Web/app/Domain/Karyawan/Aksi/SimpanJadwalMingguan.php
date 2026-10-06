<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Karyawan\Data\DataJadwalMingguan;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Model\JadwalKerja;
use App\Domain\Karyawan\Model\Karyawan;
use Illuminate\Support\Facades\DB;

/**
 * Simpan jadwal kerja satu minggu satu outlet (F-18, EMP-02, izin `karyawan.kelola`). Tiap sel: jam `HH:mm` terisi =
 * buat/ubah jadwal hari itu (karyawan aktif), jam kosong = hapus jadwal hari itu di outlet ini. Tanggal harus di minggu
 * tersebut; karyawan yang sudah dijadwalkan di outlet lain pada hari yang sama ditolak (`JadwalBentrok`).
 * `JamSelesai` ≤ `JamMulai` = lewat tengah malam. Audit `jadwal-kerja.simpan`.
 */
final class SimpanJadwalMingguan
{
    public const POLA_JAM = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @return int jumlah sel yang berubah
     */
    public function Jalankan(DataJadwalMingguan $data): int
    {
        $awal = $data->senin->toDateString();
        $akhir = $data->senin->addDays(6)->toDateString();
        $uuid = array_values(array_unique(array_map(fn (array $s): string => $s['UuidKaryawan'], $data->sel)));
        $karyawan = Karyawan::query()->whereIn('Uuid', $uuid)->get()->keyBy('Uuid');

        foreach ($data->sel as $i => $sel) {
            $k = $karyawan->get($sel['UuidKaryawan']);

            if (! $k instanceof Karyawan) {
                throw new PelanggaranAturanBisnis('KaryawanTidakDitemukan', 'Karyawan tidak ditemukan.', "Sel.{$i}.UuidKaryawan");
            }

            if ($sel['Tanggal'] < $awal || $sel['Tanggal'] > $akhir) {
                throw new PelanggaranAturanBisnis('TanggalTidakValid', 'Tanggal jadwal harus di minggu yang dipilih.', "Sel.{$i}.Tanggal");
            }

            $kosong = $sel['JamMulai'] === null || $sel['JamSelesai'] === null;

            if (! $kosong && (! preg_match(self::POLA_JAM, (string) $sel['JamMulai']) || ! preg_match(self::POLA_JAM, (string) $sel['JamSelesai']) || $sel['JamMulai'] === $sel['JamSelesai'])) {
                throw new PelanggaranAturanBisnis('JamTidakValid', "Jam kerja {$k->Nama} tidak valid (format JJ:MM, mulai ≠ selesai).", "Sel.{$i}.JamMulai");
            }

            if (! $kosong && $k->Status !== StatusKaryawan::Aktif) {
                throw new PelanggaranAturanBisnis('KaryawanNonaktif', "{$k->Nama} sudah nonaktif dan tidak bisa dijadwalkan.", "Sel.{$i}.UuidKaryawan");
            }
        }

        return DB::transaction(function () use ($data, $karyawan, $awal, $akhir): int {
            $berubah = 0;

            foreach ($data->sel as $i => $sel) {
                /** @var Karyawan $k */
                $k = $karyawan->get($sel['UuidKaryawan']);
                $ada = JadwalKerja::query()->where('IdKaryawan', $k->Id)->where('Tanggal', $sel['Tanggal'])->lockForUpdate()->first();

                if ($sel['JamMulai'] === null || $sel['JamSelesai'] === null) {
                    if ($ada !== null && $ada->IdOutlet === $data->idOutlet) {
                        $ada->delete();
                        $berubah++;
                    }

                    continue;
                }

                if ($ada !== null && $ada->IdOutlet !== $data->idOutlet) {
                    throw new PelanggaranAturanBisnis('JadwalBentrok', "{$k->Nama} sudah dijadwalkan di outlet lain pada {$sel['Tanggal']}.", "Sel.{$i}.JamMulai");
                }

                if ($ada !== null && $ada->JamMulai === $sel['JamMulai'] && $ada->JamSelesai === $sel['JamSelesai']) {
                    continue;
                }

                JadwalKerja::query()->updateOrCreate(
                    ['IdKaryawan' => $k->Id, 'Tanggal' => $sel['Tanggal']],
                    ['IdOutlet' => $data->idOutlet, 'JamMulai' => $sel['JamMulai'], 'JamSelesai' => $sel['JamSelesai']],
                );
                $berubah++;
            }

            if ($berubah > 0) {
                $this->audit->Catat('jadwal-kerja.simpan', null, nilaiBaru: ['IdOutlet' => $data->idOutlet, 'Minggu' => "{$awal}..{$akhir}", 'JumlahBerubah' => $berubah], idPengguna: $data->idPengguna);
            }

            return $berubah;
        }, 3);
    }
}
