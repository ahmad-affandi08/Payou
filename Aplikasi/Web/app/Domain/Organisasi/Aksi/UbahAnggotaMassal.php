<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Data\DataAksesAnggota;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\OutletPengguna;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\Peran;
use App\Domain\Organisasi\Model\TenantPengguna;
use Illuminate\Support\Facades\DB;

/**
 * Aksi massal anggota terpilih (F-02): nonaktifkan, aktifkan kembali, atau ganti peran. Tiap anggota diproses lewat
 * Aksi satuannya (`UbahStatusAnggota`, `UbahAksesAnggota`) sehingga semua penjaga tetap berlaku: tidak bisa mengubah diri
 * sendiri, Pemilik hanya oleh Pemilik, Pemilik aktif terakhir tidak bisa dinonaktifkan, batas kursi paket saat
 * mengaktifkan, dan aturan anti-eskalasi peran. Semua atau tidak sama sekali: satu anggota yang ditolak membatalkan
 * seluruhnya dengan pesan penolakannya. Anggota yang sudah berstatus/berperan tujuan dilewati. Ganti peran
 * mempertahankan akses outlet masing-masing (semua outlet tetap semua outlet, daftar outlet tetap sama).
 */
final class UbahAnggotaMassal
{
    public const MAKS = 100;

    public const AKSI = ['Nonaktifkan', 'Aktifkan', 'Peran'];

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly UbahStatusAnggota $ubahStatus,
        private readonly UbahAksesAnggota $ubahAkses,
    ) {}

    /**
     * @param  list<string>  $uuidPengguna
     * @return array{Diubah: int, Dilewati: int}
     *
     * @throws PelanggaranAturanBisnis AksiTidakDikenal, PilihanKosong, TerlaluBanyak, AnggotaTidakDikenal, PeranWajib, PeranTidakDikenal, serta penolakan penjaga anggota
     */
    public function Jalankan(int $idPelaku, string $aksi, array $uuidPengguna, ?string $uuidPeran = null): array
    {
        if (! in_array($aksi, self::AKSI, true)) {
            throw new PelanggaranAturanBisnis('AksiTidakDikenal', 'Aksi massal tidak dikenal.', 'Aksi');
        }

        $uuidPengguna = array_values(array_unique($uuidPengguna));

        if ($uuidPengguna === []) {
            throw new PelanggaranAturanBisnis('PilihanKosong', 'Pilih minimal satu pengguna.', 'Uuid');
        }

        if (count($uuidPengguna) > self::MAKS) {
            throw new PelanggaranAturanBisnis('TerlaluBanyak', 'Maksimal '.self::MAKS.' pengguna sekali proses.', 'Uuid');
        }

        $peran = null;

        if ($aksi === 'Peran') {
            $peran = $uuidPeran === null || $uuidPeran === ''
                ? throw new PelanggaranAturanBisnis('PeranWajib', 'Pilih peran tujuan.', 'UuidPeran')
                : (Peran::query()->where('Uuid', $uuidPeran)->first() ?? throw new PelanggaranAturanBisnis('PeranTidakDikenal', 'Peran tidak ditemukan.', 'UuidPeran'));
        }

        return DB::transaction(function () use ($idPelaku, $aksi, $uuidPengguna, $peran): array {
            $idTenant = $this->konteks->Wajib();
            $idPengguna = Pengguna::query()->whereIn('Uuid', $uuidPengguna)->pluck('Id', 'Uuid');
            $anggota = TenantPengguna::query()->where('IdTenant', $idTenant)->whereIn('IdPengguna', $idPengguna->values())->orderBy('Id')->get();

            if ($idPengguna->count() !== count($uuidPengguna) || $anggota->count() !== count($uuidPengguna)) {
                throw new PelanggaranAturanBisnis('AnggotaTidakDikenal', 'Sebagian pengguna tidak ditemukan. Muat ulang halaman.', 'Uuid');
            }

            $diubah = 0;

            foreach ($anggota as $a) {
                if ($aksi === 'Peran') {
                    $diubah += $this->GantiPeran($idPelaku, $a, $peran) ? 1 : 0;

                    continue;
                }

                $tujuan = $aksi === 'Nonaktifkan' ? StatusKeanggotaan::Nonaktif : StatusKeanggotaan::Aktif;

                if ($a->Status === $tujuan) {
                    continue;
                }

                $this->ubahStatus->Jalankan($idPelaku, $a, $tujuan);
                $diubah++;
            }

            return ['Diubah' => $diubah, 'Dilewati' => $anggota->count() - $diubah];
        });
    }

    private function GantiPeran(int $idPelaku, TenantPengguna $anggota, ?Peran $peran): bool
    {
        if ($peran === null || $anggota->IdPeran === $peran->Id) {
            return false;
        }

        $uuidOutlet = Outlet::query()
            ->whereIn('Id', OutletPengguna::query()->where('IdPengguna', $anggota->IdPengguna)->pluck('IdOutlet'))
            ->orderBy('Id')
            ->pluck('Uuid')
            ->all();
        $this->ubahAkses->Jalankan($idPelaku, $anggota, new DataAksesAnggota($peran->Uuid, $anggota->SemuaOutlet, array_values($uuidOutlet)));

        return true;
    }
}
