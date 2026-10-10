<?php

declare(strict_types=1);

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Model\KodeAktivasi;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\PanduanAwal\Model\ProgresPanduanAwal;
use App\Domain\Tenant\Model\Langganan;
use Database\Seeders\DataDemoLokal;
use Database\Seeders\TimPengujiSeeder;

afterEach(function (): void {
    foreach (['DEMO_KATA_SANDI', 'PENGUJI_EMAIL', 'PENGUJI_NO_HP', 'PENGUJI_KATA_SANDI', 'PENGUJI_PIN', 'PENGUJI_NAMA_USAHA', 'PENGUJI_JUMLAH_PERANGKAT'] as $kunci) {
        putenv($kunci);
    }

    app(KonteksTenant::class)->Kosongkan();
});

describe('Seeder TimPengujiSeeder (tenant penguji Google Play)', function (): void {
    it('membuat tenant siap pakai: email terverifikasi, panduan awal selesai, trial dipanjangkan, perangkat melebihi batas paket lewat override + kode aktivasi; idempoten', function (): void {
        putenv('DEMO_KATA_SANDI=DemoLokalTes123');
        $this->seed();
        $this->seed(DataDemoLokal::class);

        putenv('PENGUJI_EMAIL=Penguji.Tes@gmail.com');
        putenv('PENGUJI_NO_HP=081355500099');
        putenv('PENGUJI_KATA_SANDI=SandiPengujiTes2026');
        putenv('PENGUJI_PIN=847261');
        putenv('PENGUJI_JUMLAH_PERANGKAT=7');
        $this->seed(TimPengujiSeeder::class);

        $pemilik = Pengguna::query()->where('Email', 'penguji.tes@gmail.com')->sole();
        expect($pemilik->EmailDiverifikasiPada)->not->toBeNull();

        $idTenant = Langganan::query()->where('IdTenant', '>', 0)->orderByDesc('Id')->value('IdTenant');
        $langganan = Langganan::query()->where('IdTenant', $idTenant)->sole();
        expect($langganan->TrialBerakhirPada?->greaterThan(now()->addDays(50)))->toBeTrue();

        app(KonteksTenant::class)->Atur((int) $idTenant);
        expect(ProgresPanduanAwal::query()->sole()->SelesaiPada)->not->toBeNull()
            ->and(Perangkat::query()->count())->toBe(7)
            ->and(KodeAktivasi::query()->whereNull('DipakaiPada')->count())->toBe(7);

        // Dijalankan ulang: Owner sudah ada, tidak ada perangkat atau tenant tambahan.
        $this->seed(TimPengujiSeeder::class);

        expect(Perangkat::query()->count())->toBe(7)
            ->and(Pengguna::query()->where('Email', 'penguji.tes@gmail.com')->count())->toBe(1);
    });
});
