<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Layanan;

use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\Karyawan\Model\Absensi;
use App\Domain\Karyawan\Model\Karyawan;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kotak Tindakan kehadiran (F-18 bagian 5, D-44, izin `karyawan.lihat`, dibatasi outlet akses): **absen di luar jadwal**
 * (Perhatian), yaitu absen dari aplikasi kasir 7 hari terakhir yang diterima walau tidak sesuai jadwal saat aturan
 * wajib-jadwal aktif (kasir bisa offline, jadi tidak ditolak). Pengelola memeriksa lalu menjadwalkan atau mengoreksinya.
 */
final class PenyediaTindakanKehadiran implements PenyediaTindakan
{
    private const HARI = 7;

    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        if (! $konteks->CekIzin('karyawan.lihat')) {
            return [];
        }

        $dari = $konteks->hariIni->subDays(self::HARI)->toDateString();
        $kueri = fn (): Builder => Absensi::query()
            ->where('DiluarJadwal', true)
            ->where('TanggalBisnis', '>=', $dari)
            ->when($konteks->idOutletBoleh !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $konteks->idOutletBoleh ?: [0]));
        $jumlah = $kueri()->count();

        if ($jumlah === 0) {
            return [];
        }

        $daftar = $kueri()->orderByDesc('MasukPada')->limit(DataButirTindakan::BATAS_RINCIAN)->get();
        $nama = Karyawan::query()->whereKey($daftar->pluck('IdKaryawan')->all())->pluck('Nama', 'Id');

        return [new DataButirTindakan(
            'karyawan.absen-diluar-jadwal',
            'Karyawan',
            TingkatTindakan::Perhatian,
            'Absen di luar jadwal',
            'Karyawan absen di kasir padahal tidak sesuai jadwal kerjanya. Periksa, lalu jadwalkan atau koreksi absensinya.',
            $jumlah,
            "/kelola/karyawan/absensi?saring[DiluarJadwal]=1&saring[TanggalBisnis]={$dari}..{$konteks->hariIni->toDateString()}",
            'Lihat absensi',
            array_values($daftar->map(fn (Absensi $a): DataRincianTindakan => new DataRincianTindakan(
                $a->Uuid,
                (string) ($nama->get($a->IdKaryawan) ?? 'Karyawan'),
                'Absen masuk tidak sesuai jadwal',
                $a->TanggalBisnis->toDateString(),
                '/kelola/karyawan/absensi?saring[DiluarJadwal]=1',
            ))->all()),
        )];
    }

    public function AmbilJenisDokumen(): array
    {
        return [];
    }

    public function SaringDokumen(string $jenisDokumen, array $uuid): array
    {
        return [];
    }
}
