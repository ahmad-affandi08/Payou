<?php

declare(strict_types=1);

use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\PaketFitur;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Server yang dipasang sebelum sebuah fitur ditambahkan ke katalog tidak pernah mendapat fitur itu pada paket yang
 * sudah ada (seeder hanya membuat paket baru). Contoh nyata: persetujuan jarak jauh pada Bisnis tidak muncul di kasir.
 * `katalog:lengkapi-fitur` menambah saja, tidak pernah mencabut.
 */

beforeEach(fn () => BantuanPendaftaran::SiapkanPrasyarat());

describe('katalog:lengkapi-fitur', function (): void {
    it('menambah fitur yang hilang pada paket yang sudah ada; --kering tidak mengubah; idempoten; tidak mencabut apa pun', function (): void {
        ['Tenant' => $tenant] = app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data('bisnis@contoh.id', '081234500001', 'BISNIS', 'Toko Bisnis'));
        $bisnis = Paket::query()->where('Kode', 'BISNIS')->sole();
        PaketFitur::query()->where('IdPaket', $bisnis->Id)->where('KunciFitur', 'persetujuan.jarak-jauh')->delete();
        // Fitur yang sengaja ditambahkan tim lewat konsol tetap ada setelah dilengkapi.
        Fitur::query()->firstOrCreate(['Kunci' => 'uji.khusus'], ['Nama' => 'Uji khusus', 'Modul' => 'Uji']);
        PaketFitur::query()->create(['IdPaket' => $bisnis->Id, 'KunciFitur' => 'uji.khusus']);
        $jumlahSebelum = PaketFitur::query()->count();

        expect(app(PemeriksaFiturTenant::class)->CekAktif($tenant->Id, 'persetujuan.jarak-jauh'))->toBeFalse();

        $this->artisan('katalog:lengkapi-fitur', ['--kering' => true])
            ->expectsOutputToContain('[kering] paket BISNIS + persetujuan.jarak-jauh')
            ->assertSuccessful();
        expect(PaketFitur::query()->count())->toBe($jumlahSebelum);

        $this->artisan('katalog:lengkapi-fitur')->expectsOutputToContain('paket BISNIS + persetujuan.jarak-jauh')->assertSuccessful();
        expect(PaketFitur::query()->count())->toBe($jumlahSebelum + 1)
            ->and(app(PemeriksaFiturTenant::class)->CekAktif($tenant->Id, 'persetujuan.jarak-jauh'))->toBeTrue()
            ->and(PaketFitur::query()->where('IdPaket', $bisnis->Id)->where('KunciFitur', 'uji.khusus')->exists())->toBeTrue();

        $this->artisan('katalog:lengkapi-fitur')->expectsOutputToContain('Menambah 0 fitur katalog dan 0 fitur ke paket.')->assertSuccessful();
        expect(PaketFitur::query()->count())->toBe($jumlahSebelum + 1);
    });
});
