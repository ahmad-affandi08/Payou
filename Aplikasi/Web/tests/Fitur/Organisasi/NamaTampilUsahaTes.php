<?php

declare(strict_types=1);

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Kueri\NamaTampilUsaha;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Str;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(fn () => BantuanPendaftaran::SiapkanPrasyarat());

describe('Nama untuk pelanggan = nama outlet (D-77)', function (): void {
    it('satu outlet: nama outlet, bukan nama akun pemilik, baik per outlet maupun per tenant', function (): void {
        $k = BantuanKasir::Siapkan($this, 'Sudirman Group');
        Outlet::query()->whereKey($k['Outlet']->Id)->update(['Nama' => "Lil' Escape Coffee & Eatery"]);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $nama = app(NamaTampilUsaha::class);

        expect($nama->UntukOutlet($k['Outlet']->Id, $k['Tenant']->Id))->toBe("Lil' Escape Coffee & Eatery")
            ->and($nama->UntukTenant($k['Tenant']->Id))->toBe("Lil' Escape Coffee & Eatery");
    });

    it('beberapa outlet: per outlet memakai namanya sendiri, per tenant memakai nama akun (nama payung)', function (): void {
        $k = BantuanKasir::Siapkan($this, 'Sudirman Group');
        Outlet::query()->whereKey($k['Outlet']->Id)->update(['Nama' => 'Lil Escape']);
        $lain = $k['Outlet']->replicate();
        $lain->Uuid = (string) Str::ulid();
        $lain->Kode = 'BRW';
        $lain->Nama = 'Brewland';
        $lain->Status = StatusOrganisasi::Aktif;
        $lain->save();
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $nama = app(NamaTampilUsaha::class);

        expect($nama->UntukOutlet($k['Outlet']->Id, $k['Tenant']->Id))->toBe('Lil Escape')
            ->and($nama->UntukOutlet($lain->Id, $k['Tenant']->Id))->toBe('Brewland')
            ->and($nama->UntukTenant($k['Tenant']->Id))->toBe('Sudirman Group');
    });

    it('outlet tak dikenal jatuh ke aturan tenant, dan konteks tenant dikembalikan seperti semula', function (): void {
        $k = BantuanKasir::Siapkan($this, 'Sudirman Group');
        Outlet::query()->whereKey($k['Outlet']->Id)->update(['Nama' => 'Lil Escape']);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

        expect(app(NamaTampilUsaha::class)->UntukOutlet(null, $k['Tenant']->Id))->toBe('Lil Escape')
            ->and(app(KonteksTenant::class)->Ambil())->toBe($k['Tenant']->Id);
    });
});
