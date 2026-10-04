<?php

declare(strict_types=1);

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-16a/F-16b aksi massal pelanggan: arsipkan, pulihkan, atur tier. Lewat Aksi satuan (audit per pelanggan),
 * semua-atau-tidak, yang sudah berstatus tujuan dilewati, Uuid asing menolak semuanya.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('massal: arsipkan lalu pulihkan, yang sudah berstatus itu dilewati, audit per pelanggan', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk('Toko Kelontong Berkah Massal');
    BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id);
    $ani = Pelanggan::query()->create(['Nama' => 'Ani Rahmawati', 'NoHp' => '6281234567801']);
    $budi = Pelanggan::query()->create(['Nama' => 'Budi Santoso', 'NoHp' => '6281234567802']);
    $budi->forceFill(['Status' => StatusPelanggan::Diarsipkan])->save();

    $this->post('/kelola/pelanggan/massal', ['Aksi' => 'Arsipkan', 'Uuid' => [$ani->Uuid, $budi->Uuid]])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('Kilat', '1 pelanggan diarsipkan. 1 dilewati karena sudah berstatus itu.');
    expect($ani->refresh()->Status)->toBe(StatusPelanggan::Diarsipkan);

    $this->post('/kelola/pelanggan/massal', ['Aksi' => 'Pulihkan', 'Uuid' => [$ani->Uuid, $budi->Uuid]])
        ->assertSessionHas('Kilat', '2 pelanggan diaktifkan kembali.');
    expect($ani->refresh()->Status)->toBe(StatusPelanggan::Aktif)->and($budi->refresh()->Status)->toBe(StatusPelanggan::Aktif)
        ->and(LogAudit::query()->where('Peristiwa', 'pelanggan.arsipkan')->count())->toBe(1)
        ->and(LogAudit::query()->where('Peristiwa', 'pelanggan.pulihkan')->count())->toBe(2);
});

it('massal: atur dan lepas tier, tier diarsipkan dan Uuid asing menolak semuanya', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk('Toko Kelontong Berkah Tier');
    BantuanKatalog::MasukSebagai($this, $t['Tenant']->Id);
    $gold = TierPelanggan::query()->create(['Kode' => 'GOLD', 'Nama' => 'Gold', 'MinimalBelanja' => '0', 'PengaliPoin' => '1.50']);
    $lama = TierPelanggan::query()->create(['Kode' => 'LAMA', 'Nama' => 'Lama', 'MinimalBelanja' => '0', 'Status' => StatusPelanggan::Diarsipkan]);
    $ani = Pelanggan::query()->create(['Nama' => 'Ani Rahmawati', 'NoHp' => '6281234567801']);
    $budi = Pelanggan::query()->create(['Nama' => 'Budi Santoso', 'NoHp' => '6281234567802']);
    $uuid = [$ani->Uuid, $budi->Uuid];

    $this->post('/kelola/pelanggan/massal', ['Aksi' => 'Tier', 'Uuid' => $uuid, 'UuidTier' => $gold->Uuid, 'TierTetap' => true])
        ->assertSessionHasNoErrors()->assertSessionHas('Kilat', '2 pelanggan diatur tiernya.');
    expect($ani->refresh()->IdTier)->toBe($gold->Id)->and($budi->refresh()->TierTetap)->toBeTrue()
        ->and(LogAudit::query()->where('Peristiwa', 'pelanggan.tier')->count())->toBe(2);

    $this->post('/kelola/pelanggan/massal', ['Aksi' => 'Tier', 'Uuid' => $uuid, 'UuidTier' => $lama->Uuid])->assertSessionHasErrors('UuidTier');
    $this->post('/kelola/pelanggan/massal', ['Aksi' => 'Tier', 'Uuid' => [$ani->Uuid, '01J9ZZZZZZZZZZZZZZZZZZZZZZ'], 'UuidTier' => null])->assertSessionHasErrors('Uuid');
    expect($ani->refresh()->IdTier)->toBe($gold->Id);

    $this->post('/kelola/pelanggan/massal', ['Aksi' => 'Tier', 'Uuid' => $uuid, 'UuidTier' => null])->assertSessionHasNoErrors();
    expect($ani->refresh()->IdTier)->toBeNull()->and($ani->TierTetap)->toBeFalse();

    BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
    $this->post('/kelola/pelanggan/massal', ['Aksi' => 'Hapus', 'Uuid' => $uuid])->assertSessionHasErrors('Aksi');
});
