<?php

declare(strict_types=1);

use App\Domain\Organisasi\Model\Outlet;
use App\Domain\PanduanAwal\Aksi\SimpanProfilUsaha;
use App\Domain\PanduanAwal\Data\DataProfilUsaha;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    BantuanOrganisasi::BuatKota();
});

describe('D-78: pemilik hanya mengisi satu nama (nama toko = nama outlet pertama)', function (): void {
    it('outlet pertama dibuat dengan nama usaha, dan ikut berubah saat profil usaha diganti', function (): void {
        ['Outlet' => $outlet] = BantuanPanduanAwal::BuatTenant('Lil Escape');
        expect($outlet->Nama)->toBe('Lil Escape');

        app(SimpanProfilUsaha::class)->Jalankan($outlet, new DataProfilUsaha('Lil Escape Coffee', null, '33.72', null, false), null, false);

        expect(Outlet::query()->findOrFail($outlet->Id)->Nama)->toBe('Lil Escape Coffee');
    });

    it('outlet yang sudah diberi nama sendiri tidak ditimpa nama usaha', function (): void {
        ['Outlet' => $outlet] = BantuanPanduanAwal::BuatTenant('Lil Escape');
        $outlet->forceFill(['Nama' => 'Lil Escape Sudirman'])->save();

        app(SimpanProfilUsaha::class)->Jalankan($outlet->refresh(), new DataProfilUsaha('Sudirman Group', null, '33.72', null, false), null, false);

        expect(Outlet::query()->findOrFail($outlet->Id)->Nama)->toBe('Lil Escape Sudirman');
    });
});
