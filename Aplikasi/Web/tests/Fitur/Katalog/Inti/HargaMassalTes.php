<?php

declare(strict_types=1);

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Katalog\Harga\Model\RiwayatHarga;
use App\Domain\Katalog\Model\ProdukHarga;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-03 aksi massal harga: naik/turun harga produk terpilih dengan persen atau nominal + pembulatan. Lewat
 * `SimpanHargaProduk` (riwayat harga BR-03.3 + audit per produk), semua-atau-tidak, hasil negatif menolak semuanya.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('harga massal: naik persen dengan pembulatan, riwayat dan audit tercatat per produk', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk();
    $kopi = BantuanKatalog::BuatProduk(['Nama' => 'Kopi Susu Gula Aren Literan', 'Sku' => 'KSG-1L', 'IdKelompokPajak' => $t['KelompokPajak']->Id], '85000.00', $t['Pcs']);
    $teh = BantuanKatalog::BuatProduk(['Nama' => 'Teh Melati Botol 1 Liter', 'Sku' => 'TMB-1L', 'IdKelompokPajak' => $t['KelompokPajak']->Id], '35000.00', $t['Pcs']);

    BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id)
        ->post('/kelola/produk/harga-massal', ['Mode' => 'NaikPersen', 'Nilai' => '10', 'Pembulatan' => 500, 'Uuid' => [$kopi->Uuid, $teh->Uuid]])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('Kilat', 'Harga 2 produk diubah (2 baris harga).');

    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    // 85.000 × 1,10 = 93.500 (sudah kelipatan 500); 35.000 × 1,10 = 38.500.
    expect(ProdukHarga::query()->where('IdProduk', $kopi->Id)->sole()->Harga)->toBe('93500.00')
        ->and(ProdukHarga::query()->where('IdProduk', $teh->Id)->sole()->Harga)->toBe('38500.00')
        ->and(RiwayatHarga::query()->where('Sumber', 'Manual')->count())->toBeGreaterThanOrEqual(2)
        ->and(LogAudit::query()->where('Peristiwa', 'produk.harga.ubah')->count())->toBe(2);
});

it('harga massal: turun nominal, tanpa perubahan tidak menulis, dan hasil negatif menolak semuanya', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk();
    $murah = BantuanKatalog::BuatProduk(['Nama' => 'Gelas Plastik Isi 50', 'Sku' => 'GLS-50', 'IdKelompokPajak' => $t['KelompokPajak']->Id], '4000.00', $t['Pcs']);
    $mahal = BantuanKatalog::BuatProduk(['Nama' => 'Biji Kopi Arabika 1 Kg', 'Sku' => 'BKA-1K', 'IdKelompokPajak' => $t['KelompokPajak']->Id], '150000.00', $t['Pcs']);
    $masuk = fn () => BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id);

    // Turun 5.000 membuat produk murah negatif: tidak satu pun berubah.
    $masuk()->post('/kelola/produk/harga-massal', ['Mode' => 'TurunNominal', 'Nilai' => '5000', 'Pembulatan' => 0, 'Uuid' => [$murah->Uuid, $mahal->Uuid]])
        ->assertSessionHasErrors('Nilai');
    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(ProdukHarga::query()->where('IdProduk', $mahal->Id)->sole()->Harga)->toBe('150000.00');

    $masuk()->post('/kelola/produk/harga-massal', ['Mode' => 'TurunNominal', 'Nilai' => '5000', 'Pembulatan' => 0, 'Uuid' => [$mahal->Uuid]])
        ->assertSessionHasNoErrors()->assertSessionHas('Kilat', 'Harga 1 produk diubah (1 baris harga).');
    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(ProdukHarga::query()->where('IdProduk', $mahal->Id)->sole()->Harga)->toBe('145000.00');

    // Naik persen yang dibulatkan ke kelipatan 1.000 pada harga yang sudah pas = tidak ada perubahan.
    $masuk()->post('/kelola/produk/harga-massal', ['Mode' => 'NaikPersen', 'Nilai' => '0.01', 'Pembulatan' => 1000, 'Uuid' => [$mahal->Uuid]])
        ->assertSessionHas('Kilat', 'Tidak ada harga yang berubah.');
});

it('harga massal: nilai tidak valid, Uuid asing, dan tanpa izin ubah harga ditolak', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk();
    $kopi = BantuanKatalog::BuatProduk(['Nama' => 'Kopi Susu Gula Aren Literan', 'Sku' => 'KSG-1L', 'IdKelompokPajak' => $t['KelompokPajak']->Id], '85000.00', $t['Pcs']);
    $masuk = fn () => BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id);

    $masuk()->post('/kelola/produk/harga-massal', ['Mode' => 'NaikPersen', 'Nilai' => '-5', 'Pembulatan' => 0, 'Uuid' => [$kopi->Uuid]])->assertSessionHasErrors('Nilai');
    $masuk()->post('/kelola/produk/harga-massal', ['Mode' => 'NaikPersen', 'Nilai' => '5', 'Pembulatan' => 0, 'Uuid' => [$kopi->Uuid, '01J9ZZZZZZZZZZZZZZZZZZZZZZ']])->assertSessionHasErrors('Uuid');
    $masuk()->post('/kelola/produk/harga-massal', ['Mode' => 'Acak', 'Nilai' => '5', 'Pembulatan' => 0, 'Uuid' => [$kopi->Uuid]])->assertSessionHasErrors('Mode');

    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    expect(ProdukHarga::query()->where('IdProduk', $kopi->Id)->sole()->Harga)->toBe('85000.00');
});
