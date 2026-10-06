<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Kueri\PemeriksaanTutupHarian;
use App\Domain\Kasir\Model\Shift;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use Carbon\CarbonImmutable;

/**
 * D-23 D bagian 3: menutup otomatis hari yang **aman** ditutup (14 hari terakhir per outlet aktif): hari sudah
 * berakhir, belum ditutup, ada minimal satu shift dan semuanya sudah ditutup, dan tidak ada peringatan (perangkat
 * belum sinkron, penjualan perlu tinjauan). Selain itu hari dibiarkan untuk ditutup manual; pengingatnya sudah ada di
 * Kotak Tindakan (shift lupa ditutup, penjualan perlu dicek). Lewat `TutupHarianOutlet` yang sama (`DitutupOtomatis`).
 */
final class TutupHarianOtomatis
{
    public function __construct(
        private readonly PemeriksaanTutupHarian $pemeriksaan,
        private readonly PetaUuidOutlet $outlet,
        private readonly TutupHarianOutlet $tutup,
    ) {}

    /** @return int jumlah hari yang ditutup */
    public function Jalankan(int $idTenant, int $idPengguna): int
    {
        $idOutlet = array_column($this->outlet->AmbilRingkas(null, hanyaAktif: true), 'Id', 'Uuid');
        $ditutup = 0;

        foreach (array_reverse($this->pemeriksaan->Daftar($idTenant, null)) as $hari) {
            $id = $idOutlet[$hari['Outlet']] ?? null;

            if ($id === null || $hari['Ditutup'] || $hari['Berjalan'] || $hari['ShiftBelumDitutup'] > 0 || $hari['Peringatan'] !== []) {
                continue;
            }

            $adaShift = Shift::query()
                ->where('IdOutlet', $id)
                ->where('TanggalBisnis', $hari['TanggalBisnis'])
                ->where('Status', StatusShift::Tertutup->value)
                ->exists();

            if (! $adaShift) {
                continue;
            }

            try {
                $this->tutup->Jalankan($id, CarbonImmutable::parse($hari['TanggalBisnis']), $idPengguna, false, otomatis: true);
                $ditutup++;
            } catch (PelanggaranAturanBisnis) {
                // Keadaan berubah sejak diperiksa (misal shift dibuka ulang): coba lagi di putaran berikutnya.
            }
        }

        return $ditutup;
    }
}
