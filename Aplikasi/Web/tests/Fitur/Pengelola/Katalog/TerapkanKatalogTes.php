<?php

declare(strict_types=1);

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Katalog\Aksi\TerapkanKatalogBawaan;
use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Kueri\HargaPaketBerlaku;
use App\Domain\Tenant\Model\Addon;
use App\Domain\Tenant\Model\HargaPaket;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Support\Carbon;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * D-86: `katalog:terapkan` menyelaraskan harga paket, add-on, dan fitur server production dengan berkas data rilis
 * dalam satu perintah. Langsung terbit, berlaku untuk tenant lama pada tagihan berikutnya, idempoten, tidak merusak.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    // Server production sebelum rilis ini: harga terbit lama (79.000 / 199.000), tanpa versi baru.
    foreach (['STARTER' => ['79000', '758400'], 'PRO' => ['199000', '1910400']] as $kode => [$bulanan, $tahunan]) {
        HargaPaket::query()->create([
            'IdPaket' => Paket::query()->where('Kode', $kode)->value('Id'),
            'HargaBulanan' => $bulanan,
            'HargaTahunan' => $tahunan,
            'BerlakuMulai' => '2026-01-01',
            'Status' => StatusDataMaster::Terbit,
        ]);
    }
});

function HargaTerbitBerlaku(string $kode, string $langgananMulai): HargaPaket
{
    $paket = Paket::query()->where('Kode', $kode)->sole();
    $harga = app(HargaPaketBerlaku::class)->Cari($paket->Id, now('Asia/Jakarta'), Carbon::parse($langgananMulai));

    return $harga ?? throw new RuntimeException('Tidak ada harga berlaku.');
}

describe('katalog:terapkan', function (): void {
    it('--kering hanya menampilkan rencana dan tidak mengubah apa pun', function (): void {
        $jumlahHarga = HargaPaket::query()->count();
        Addon::query()->where('Kode', 'OUTLET_TAMBAHAN')->update(['HargaBulanan' => '99000', 'Status' => StatusPaket::Diarsipkan->value]);

        $this->artisan('katalog:terapkan', ['--kering' => true])
            ->expectsOutputToContain('[kering] harga paket STARTER: 79000.00 → 99000.00')
            ->expectsOutputToContain('[kering] add-on OUTLET_TAMBAHAN (ubah): harga 99000.00 → 79000.00, diaktifkan')
            ->assertSuccessful();

        expect(HargaPaket::query()->count())->toBe($jumlahHarga)
            ->and(Addon::query()->where('Kode', 'OUTLET_TAMBAHAN')->sole()->HargaBulanan)->toBe('99000.00');
    });

    it('menerbitkan harga baru langsung, berlaku untuk tenant lama, mengakhiri versi lama, dan tercatat di audit', function (): void {
        $this->artisan('katalog:terapkan')->assertSuccessful();

        $starter = Paket::query()->where('Kode', 'STARTER')->sole();
        $terbit = HargaPaket::query()->where('IdPaket', $starter->Id)->where('Status', StatusDataMaster::Terbit->value)->orderBy('BerlakuMulai')->get();
        $hariIni = now('Asia/Jakarta')->toDateString();

        expect($terbit)->toHaveCount(2)
            ->and($terbit[0]->BerlakuSampai?->toDateString())->toBe(now('Asia/Jakarta')->subDay()->toDateString())
            ->and($terbit[1]->HargaBulanan)->toBe('99000.00')
            ->and($terbit[1]->HargaTahunan)->toBe('950400.00')
            ->and($terbit[1]->BerlakuMulai->toDateString())->toBe($hariIni)
            ->and($terbit[1]->TerapkanKePelangganLama)->toBeTrue()
            // Langganan yang dimulai jauh sebelum rilis tetap ikut harga baru pada tagihan berikutnya.
            ->and(HargaTerbitBerlaku('STARTER', '2026-02-01')->HargaBulanan)->toBe('99000.00')
            ->and(HargaTerbitBerlaku('PRO', '2026-02-01')->HargaBulanan)->toBe('249000.00');

        $log = LogAuditPengelola::query()->where('Aksi', 'katalog.harga.terapkan-rilis')->get();
        // Gratis (Rp0), Starter, Pro, dan Bisnis; Enterprise memakai harga negosiasi.
        expect($log)->toHaveCount(4)->and($log->first()->IdPenggunaPengelola)->toBeNull();
    });

    it('--tanpa-pelanggan-lama menjaga langganan lama di harga lamanya', function (): void {
        $this->artisan('katalog:terapkan', ['--tanpa-pelanggan-lama' => true])->assertSuccessful();

        expect(HargaTerbitBerlaku('STARTER', '2026-02-01')->HargaBulanan)->toBe('79000.00')
            ->and(HargaTerbitBerlaku('STARTER', now('Asia/Jakarta')->toDateString())->HargaBulanan)->toBe('99000.00');
    });

    it('menyelaraskan add-on: harga, fitur, aktif; menambah yang belum ada; tidak menyentuh yang sudah sesuai', function (): void {
        Addon::query()->where('Kode', 'OUTLET_TAMBAHAN')->update(['HargaBulanan' => '99000', 'Status' => StatusPaket::Diarsipkan->value]);
        Addon::query()->where('Kode', 'INSIGHT')->update(['HargaBulanan' => '59000']);
        Addon::query()->where('Kode', 'SELF_ORDER')->delete();

        $this->artisan('katalog:terapkan')->assertSuccessful();

        $outlet = Addon::query()->where('Kode', 'OUTLET_TAMBAHAN')->sole();
        $baru = Addon::query()->where('Kode', 'SELF_ORDER')->sole();

        expect($outlet->HargaBulanan)->toBe('79000.00')
            ->and($outlet->Status)->toBe(StatusPaket::Aktif)
            ->and(Addon::query()->where('Kode', 'INSIGHT')->sole()->HargaBulanan)->toBe('49000.00')
            ->and($baru->Status)->toBe(StatusPaket::Aktif)
            ->and($baru->KunciFitur)->toBe('kanal.self-order');
    });

    it('--tanpa-aktifkan-addon membiarkan add-on yang diarsipkan Keuangan tetap diarsipkan (harga tetap diselaraskan)', function (): void {
        Addon::query()->where('Kode', 'TOKO_ONLINE')->update(['HargaBulanan' => '99000', 'Status' => StatusPaket::Diarsipkan->value]);

        $this->artisan('katalog:terapkan', ['--tanpa-aktifkan-addon' => true])->assertSuccessful();

        $toko = Addon::query()->where('Kode', 'TOKO_ONLINE')->sole();
        expect($toko->Status)->toBe(StatusPaket::Diarsipkan)->and($toko->HargaBulanan)->toBe('79000.00');
    });

    it('idempoten: dijalankan ulang tidak membuat versi harga atau perubahan lagi', function (): void {
        $this->artisan('katalog:terapkan')->assertSuccessful();
        $jumlah = HargaPaket::query()->count();

        $this->artisan('katalog:terapkan')
            ->expectsOutputToContain('Mengubah 0 harga paket, 0 add-on, 0 fitur katalog, dan 0 fitur paket.')
            ->assertSuccessful();

        expect(HargaPaket::query()->count())->toBe($jumlah);
    });

    it('paket dengan harga negosiasi (Enterprise) dan paket yang tidak ada di server dilewati', function (): void {
        $berkas = tempnam(sys_get_temp_dir(), 'paket');
        file_put_contents($berkas, json_encode(['Paket' => [
            ['Kode' => 'TIDAK_ADA', 'Nama' => 'Tidak ada', 'HargaNegosiasi' => false, 'Harga' => ['HargaBulanan' => '1000', 'HargaTahunan' => '9600'], 'Fitur' => []],
            ['Kode' => 'ENTERPRISE', 'Nama' => 'Enterprise', 'HargaNegosiasi' => true, 'Harga' => ['HargaBulanan' => '5000000', 'HargaTahunan' => '1'], 'Fitur' => []],
        ]]));

        $hasil = app(TerapkanKatalogBawaan::class)->Jalankan(pathPaket: $berkas);
        unlink($berkas);

        expect($hasil['Harga'])->toBe([])
            ->and(HargaPaket::query()->where('IdPaket', Paket::query()->where('Kode', 'ENTERPRISE')->value('Id'))->where('Status', StatusDataMaster::Terbit->value)->exists())->toBeFalse();
    });

    it('versi terakhir yang berlaku hari ini atau nanti membuat harga baru berlaku sehari sesudahnya', function (): void {
        $starter = Paket::query()->where('Kode', 'STARTER')->sole();
        HargaPaket::query()->where('IdPaket', $starter->Id)->where('Status', StatusDataMaster::Terbit->value)->update(['BerlakuMulai' => now('Asia/Jakarta')->toDateString()]);

        $this->artisan('katalog:terapkan')->assertSuccessful();

        $baru = HargaPaket::query()->where('IdPaket', $starter->Id)->where('Status', StatusDataMaster::Terbit->value)->orderByDesc('BerlakuMulai')->first();
        expect($baru->HargaBulanan)->toBe('99000.00')
            ->and($baru->BerlakuMulai->toDateString())->toBe(now('Asia/Jakarta')->addDay()->toDateString());
    });
});
