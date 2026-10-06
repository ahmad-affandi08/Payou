<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Enum\JenisGudang;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Merek;
use App\Domain\Organisasi\Model\Outlet;

/**
 * Organisasi bawaan tenant baru (F-00 langkah 3): satu merek, outlet pertama (bernama sama dengan usaha, D-78), dan gudang tokonya, serta peran
 * bawaan tenant dengan Owner sebagai Pemilik (F-02, §19.1). Dipanggil setelah Owner dibuat.
 */
final class SiapkanOrganisasiAwal
{
    public const KODE_OUTLET_UTAMA = 'UTAMA';

    public function __construct(private readonly SiapkanPeranBawaanTenant $siapkanPeran) {}

    public function Jalankan(int $idTenant, string $namaUsaha, string $zonaWaktu): Outlet
    {
        $merek = Merek::query()->create(['IdTenant' => $idTenant, 'Nama' => $namaUsaha]);
        $outlet = Outlet::query()->create([
            'IdTenant' => $idTenant,
            'IdMerek' => $merek->Id,
            'Kode' => self::KODE_OUTLET_UTAMA,
            'Nama' => $namaUsaha,
            'ZonaWaktu' => $zonaWaktu,
        ]);
        Gudang::query()->create([
            'IdTenant' => $idTenant,
            'IdOutlet' => $outlet->Id,
            'Kode' => self::KODE_OUTLET_UTAMA,
            'Nama' => 'Gudang Outlet Utama',
            'Jenis' => JenisGudang::Toko,
        ]);

        $this->siapkanPeran->Jalankan($idTenant);

        return $outlet;
    }
}
