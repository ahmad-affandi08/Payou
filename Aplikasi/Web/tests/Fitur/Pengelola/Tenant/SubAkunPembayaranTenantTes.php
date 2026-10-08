<?php

declare(strict_types=1);

use App\Domain\Integrasi\Enum\StatusSubAkunPembayaran;
use App\Domain\Integrasi\GerbangPembayaran\ProtokolDoku;
use App\Domain\Integrasi\Model\SubAkunPembayaran;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Pengelola\BantuanPengelola;
use Tests\Pendukung\Pengelola\BantuanTenantPengelola;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Tahap 3 DOKU: sub account pembayaran per tenant, dibuat otomatis dari konsol pengelola lewat DOKU Sub Account API
 * (`POST /sac-merchant/v1/accounts`) memakai akun induk DOKU platform (kredensial `DokuBilling`). Hanya wadah +
 * pembuatan; rute dana QRIS ke sub account belum ada (jalur QRIS toko tidak disentuh).
 */

const ID_KLIEN_INDUK = 'BRN-0201-INDUK';

const KUNCI_INDUK = 'SK-induk-doku-payoung-rahasia-1234';

const ALAMAT_SUB_AKUN = 'https://api-sandbox.doku.com/sac-merchant/v1/accounts';

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    IsiKredensialInduk();
});

function IsiKredensialInduk(bool $isi = true): void
{
    config()->set('integrasi.GerbangBilling', $isi ? [
        'Penyedia' => 'DokuBilling',
        'Pengaturan' => ['Mode' => 'Sandbox', 'IdKlien' => ID_KLIEN_INDUK],
        'Kredensial' => ['KunciRahasia' => KUNCI_INDUK],
    ] : []);
}

/** @param  array<string, mixed>  $badan */
function FakeSubAkun(int $kode, array $badan): void
{
    AtaSetelUlangHttp();
    Http::fake(['api-sandbox.doku.com/sac-merchant/*' => Http::response($badan, $kode)]);
}

/** Http::fake berurutan: stub pertama yang cocok menang, jadi tiap jawaban baru perlu pabrik Http yang bersih. */
function AtaSetelUlangHttp(): void
{
    Http::swap(new Factory);
}

function UrlKonsolSubAkun(object $tenant): string
{
    return BantuanPengelola::Url("/tenant/{$tenant->Uuid}/sub-akun-pembayaran");
}

function SubAkunTenant(int $idTenant): ?SubAkunPembayaran
{
    BantuanOrganisasi::AturKonteks($idTenant);

    return SubAkunPembayaran::query()->first();
}

describe('tahap 3 DOKU: pembuatan sub account dari konsol', function (): void {
    it('berhasil: memanggil Sub Account API dengan tanda tangan DOKU, body email Owner + nama usaha, menyimpan ID & mencatat audit', function (): void {
        FakeSubAkun(200, ['account' => ['id' => 'SAC-1234-5678', 'status' => 'PENDING']]);
        $tenant = BantuanTenantPengelola::BuatTenant('Toko Kelontong Berkah Solo', 'owner.berkah@contoh.id');
        $pelaku = BantuanTenantPengelola::Masuk($this, PeranPengelolaBawaan::Teknis);

        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHasNoErrors()->assertSessionHas('Kilat');

        Http::assertSent(function (Request $r): bool {
            $badan = $r->body();
            $harapan = ProtokolDoku::Tandatangani(
                ID_KLIEN_INDUK,
                $r->header('Request-Id')[0] ?? '',
                $r->header('Request-Timestamp')[0] ?? '',
                '/sac-merchant/v1/accounts',
                $badan,
                KUNCI_INDUK,
            );

            return $r->method() === 'POST'
                && $r->url() === ALAMAT_SUB_AKUN
                && $r->hasHeader('Client-Id', ID_KLIEN_INDUK)
                && ($r->header('Request-Id')[0] ?? '') !== ''
                && ($r->header('Request-Timestamp')[0] ?? '') !== ''
                && ($r->header('Signature')[0] ?? '') === $harapan
                && $r->data() === ['account' => ['email' => 'owner.berkah@contoh.id', 'type' => 'STANDARD', 'name' => 'Toko Kelontong Berkah Solo']];
        });

        $sub = SubAkunTenant($tenant->Id);
        expect($sub)->not->toBeNull()
            ->and($sub->IdSubAkun)->toBe('SAC-1234-5678')
            ->and($sub->Status)->toBe(StatusSubAkunPembayaran::Menunggu)
            ->and($sub->Penyedia)->toBe('Doku')
            ->and($sub->PesanGalat)->toBeNull()
            ->and($sub->DibuatOleh)->toBe($pelaku->Id);

        $log = LogAuditPengelola::query()->where('Aksi', 'tenant.subakun.buat')->sole();
        expect($log->IdTenant)->toBe($tenant->Id)
            ->and($log->IdPenggunaPengelola)->toBe($pelaku->Id)
            ->and($log->NilaiBaru)->toMatchArray(['Status' => 'Menunggu', 'IdSubAkun' => 'SAC-1234-5678'])
            ->and(json_encode($log->NilaiBaru))->not->toContain(KUNCI_INDUK);

        $this->get(BantuanPengelola::Url("/tenant/{$tenant->Uuid}"))
            ->assertInertia(fn (AssertableInertia $h) => $h
                ->where('Tenant.SubAkunPembayaran.GerbangPlatformAktif', true)
                ->where('Tenant.SubAkunPembayaran.Sub.IdSubAkun', 'SAC-1234-5678')
                ->where('Tenant.SubAkunPembayaran.Sub.Status', 'Menunggu')
                ->where('Tenant.SubAkunPembayaran.Sub.BisaDibuat', false));
    });

    it('status ACTIVE dari DOKU disimpan sebagai Aktif', function (): void {
        FakeSubAkun(200, ['account' => ['id' => 'SAC-AKTIF-1', 'status' => 'ACTIVE']]);
        $tenant = BantuanTenantPengelola::BuatTenant();
        BantuanTenantPengelola::Masuk($this, PeranPengelolaBawaan::SuperAdmin);

        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHasNoErrors();

        expect(SubAkunTenant($tenant->Id)->Status)->toBe(StatusSubAkunPembayaran::Aktif);
    });

    it('idempoten: sub account yang sudah punya ID tidak dibuat lagi dan DOKU tidak dipanggil untuk kedua kalinya', function (): void {
        FakeSubAkun(200, ['account' => ['id' => 'SAC-SATU-KALI', 'status' => 'PENDING']]);
        $tenant = BantuanTenantPengelola::BuatTenant();
        BantuanTenantPengelola::Masuk($this, PeranPengelolaBawaan::Teknis);

        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHasNoErrors();
        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHasNoErrors();

        Http::assertSentCount(1);
        BantuanOrganisasi::AturKonteks($tenant->Id);
        expect(SubAkunPembayaran::query()->count())->toBe(1)
            ->and(SubAkunPembayaran::query()->sole()->IdSubAkun)->toBe('SAC-SATU-KALI')
            ->and(LogAuditPengelola::query()->where('Aksi', 'tenant.subakun.buat')->count())->toBe(1);
    });

    it('galat 4xx pasti: Gagal + pesan tanpa rahasia; setelah diperbaiki boleh dicoba lagi', function (): void {
        FakeSubAkun(400, ['message' => ['Email sudah dipakai. Kunci '.KUNCI_INDUK]]);
        $tenant = BantuanTenantPengelola::BuatTenant();
        BantuanTenantPengelola::Masuk($this, PeranPengelolaBawaan::Teknis);

        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHasErrors('Umum');

        $sub = SubAkunTenant($tenant->Id);
        expect($sub->Status)->toBe(StatusSubAkunPembayaran::Gagal)
            ->and($sub->IdSubAkun)->toBeNull()
            ->and($sub->PesanGalat)->toContain('HTTP 400')->toContain('Email sudah dipakai')
            ->and($sub->PesanGalat)->not->toContain(KUNCI_INDUK)
            ->and(json_encode(LogAuditPengelola::query()->where('Aksi', 'tenant.subakun.buat')->get()->all()))->not->toContain(KUNCI_INDUK);

        FakeSubAkun(200, ['account' => ['id' => 'SAC-ULANG-OK', 'status' => 'PENDING']]);
        // Galat percobaan pertama masih terbawa di sesi uji yang sama, jadi hasilnya dibaca dari pesan sukses & data.
        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHas('Kilat');

        $sub = SubAkunTenant($tenant->Id);
        expect($sub->Status)->toBe(StatusSubAkunPembayaran::Menunggu)
            ->and($sub->IdSubAkun)->toBe('SAC-ULANG-OK')
            ->and($sub->PesanGalat)->toBeNull()
            ->and(SubAkunPembayaran::query()->count())->toBe(1);
    });

    it('galat 5xx dan jaringan: bukan Gagal permanen (tetap Menunggu), pesan jelas, boleh dicoba lagi', function (): void {
        FakeSubAkun(503, ['message' => ['Layanan sedang sibuk']]);
        $tenant = BantuanTenantPengelola::BuatTenant();
        BantuanTenantPengelola::Masuk($this, PeranPengelolaBawaan::Teknis);

        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHasErrors('Umum');
        $sub = SubAkunTenant($tenant->Id);
        expect($sub->Status)->toBe(StatusSubAkunPembayaran::Menunggu)
            ->and($sub->IdSubAkun)->toBeNull()
            ->and($sub->PesanGalat)->toContain('HTTP 503');

        AtaSetelUlangHttp();
        Http::fake(['api-sandbox.doku.com/*' => fn () => throw new ConnectionException('timeout')]);
        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHasErrors('Umum');
        $sub = SubAkunTenant($tenant->Id);
        expect($sub->Status)->toBe(StatusSubAkunPembayaran::Menunggu)
            ->and($sub->PesanGalat)->toContain('tidak bisa dihubungi');

        FakeSubAkun(200, ['account' => ['id' => 'SAC-PULIH', 'status' => 'PENDING']]);
        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHas('Kilat');
        expect(SubAkunTenant($tenant->Id)->IdSubAkun)->toBe('SAC-PULIH');
    });

    it('sukses tetapi ID tidak terbaca: tetap Menunggu tanpa ID (tidak dianggap Gagal)', function (): void {
        FakeSubAkun(200, ['hasil' => 'ok']);
        $tenant = BantuanTenantPengelola::BuatTenant();
        BantuanTenantPengelola::Masuk($this, PeranPengelolaBawaan::Teknis);

        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHasErrors('Umum');

        $sub = SubAkunTenant($tenant->Id);
        expect($sub->Status)->toBe(StatusSubAkunPembayaran::Menunggu)->and($sub->IdSubAkun)->toBeNull();
    });

    it('gerbang platform belum diisi: galat jelas, DOKU tidak dipanggil, tidak ada baris', function (): void {
        IsiKredensialInduk(false);
        Http::fake();
        $tenant = BantuanTenantPengelola::BuatTenant();
        BantuanTenantPengelola::Masuk($this, PeranPengelolaBawaan::Teknis);

        $this->post(UrlKonsolSubAkun($tenant))
            ->assertSessionHasErrors(['Umum' => 'Isi kredensial DOKU platform dulu di menu Integrasi (Akun DOKU Payoung), lalu coba lagi.']);

        Http::assertNothingSent();
        expect(SubAkunTenant($tenant->Id))->toBeNull();

        $this->get(BantuanPengelola::Url("/tenant/{$tenant->Uuid}"))
            ->assertInertia(fn (AssertableInertia $h) => $h
                ->where('Tenant.SubAkunPembayaran.GerbangPlatformAktif', false)
                ->where('Tenant.SubAkunPembayaran.Sub', null));
    });

    it('menggunakan mode Produksi dari kredensial induk (host api.doku.com)', function (): void {
        config()->set('integrasi.GerbangBilling.Pengaturan.Mode', 'Produksi');
        Http::fake(['api.doku.com/sac-merchant/*' => Http::response(['account' => ['id' => 'SAC-PROD', 'status' => 'PENDING']], 200)]);
        $tenant = BantuanTenantPengelola::BuatTenant();
        BantuanTenantPengelola::Masuk($this, PeranPengelolaBawaan::SuperAdmin);

        $this->post(UrlKonsolSubAkun($tenant))->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.doku.com/sac-merchant/v1/accounts');
    });

    it('izin: hanya peran dengan integrasi.kelola; Dukungan, Keuangan, Analis ditolak 403 tanpa memanggil DOKU', function (PeranPengelolaBawaan $peran): void {
        Http::fake();
        $tenant = BantuanTenantPengelola::BuatTenant();
        BantuanTenantPengelola::Masuk($this, $peran);

        $this->post(UrlKonsolSubAkun($tenant))->assertForbidden();

        Http::assertNothingSent();
        expect(SubAkunTenant($tenant->Id))->toBeNull();
    })->with([PeranPengelolaBawaan::Dukungan, PeranPengelolaBawaan::Keuangan, PeranPengelolaBawaan::Analis]);

    it('tanpa kode 2FA baru di sesi, aksi ditolak dan DOKU tidak dipanggil', function (): void {
        Http::fake();
        $tenant = BantuanTenantPengelola::BuatTenant();
        $anggota = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Teknis);
        $this->actingAs($anggota, 'pengelola')->withSession(BantuanPengelola::SesiPerangkatTepercaya());

        $this->post(UrlKonsolSubAkun($tenant));

        Http::assertNothingSent();
        expect(SubAkunTenant($tenant->Id))->toBeNull();
    });
});

describe('tahap 3 DOKU: tampilan back-office tenant (hanya baca) dan isolasi', function (): void {
    it('tenant hanya melihat status sub account miliknya; tenant lain tidak; ID & pesan galat tidak ikut terkirim', function (): void {
        FakeSubAkun(200, ['account' => ['id' => 'SAC-RAHASIA-TOKO-A', 'status' => 'ACTIVE']]);
        $tokoA = BantuanOrganisasi::BuatTenant('Toko Sembako Maju Jaya');
        $tokoB = BantuanOrganisasi::BuatTenant('Warung Kopi Sederhana Klaten');
        $pengelola = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Teknis);
        $this->actingAs($pengelola, 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi())
            ->post(UrlKonsolSubAkun($tokoA['Tenant']))->assertSessionHasNoErrors();

        // Pengelola dan tenant memakai guard berbeda; mulai dari penjaga tenant yang bersih.
        auth('pengelola')->logout();
        $alamat = rtrim((string) config('app.url'), '/').'/kelola/pembayaran/gerbang';

        $halamanA = BantuanOrganisasi::Masuk($this, $tokoA['Pemilik'], $tokoA['Tenant']->Id)->get($alamat)->assertOk();
        $halamanA->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Pembayaran/Gerbang')
            ->where('SubAkun', ['Status' => 'Aktif', 'LabelStatus' => 'Aktif']));
        expect($halamanA->getContent())->not->toContain('SAC-RAHASIA-TOKO-A');

        $halamanB = BantuanOrganisasi::Masuk($this, $tokoB['Pemilik'], $tokoB['Tenant']->Id)->get($alamat)->assertOk();
        $halamanB->assertInertia(fn (AssertableInertia $h) => $h->where('SubAkun', null));

        BantuanOrganisasi::AturKonteks($tokoB['Tenant']->Id);
        expect(SubAkunPembayaran::query()->count())->toBe(0);
        BantuanOrganisasi::AturKonteks($tokoA['Tenant']->Id);
        expect(SubAkunPembayaran::query()->count())->toBe(1);
        expect(DB::table('SubAkunPembayaran')->count())->toBe(1);
    });

    it('unik per (tenant, penyedia): baris kedua untuk tenant dan penyedia yang sama ditolak database', function (): void {
        $tenant = BantuanTenantPengelola::BuatTenant();
        BantuanOrganisasi::AturKonteks($tenant->Id);
        SubAkunPembayaran::query()->create(['Penyedia' => 'Doku']);

        expect(fn () => SubAkunPembayaran::query()->create(['Penyedia' => 'Doku']))->toThrow(QueryException::class);
    });
});
