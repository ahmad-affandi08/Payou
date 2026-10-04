<?php

declare(strict_types=1);

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Integrasi\Billing\NomorPesananBilling;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Pengelola\Tagihan\Aksi\TerimaPembayaranLangganan;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Tenant\Enum\MetodePembayaranLangganan;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Pendukung\Pengelola\BantuanPengelola;
use Tests\Pendukung\Tenant\BantuanTagihan;
use Tests\TestCase;

/*
 * BR-P08.11 | P-08 langkah 3 jalur gerbang: tenant membayar tagihan langganan lewat Snap Midtrans (akun platform),
 * dan tagihan menjadi Lunas **hanya** dari notifikasi webhook bertanda tangan.
 *
 * Yang dijaga berkas ini: pelunasannya memakai layanan yang sama dengan verifikasi transfer manual
 * (`PelunasTagihanLangganan`), notifikasinya idempoten, dan tanda tangan palsu tidak pernah melunasi apa pun.
 */

const KUNCI_SERVER_BILLING = 'SB-Mid-server-kunci-billing-uji';

const TOKEN_SNAP_UJI = 'tok-snap-uji-abcdef';

function AturGerbangBilling(): void
{
    config()->set('integrasi.GerbangBilling', [
        'Penyedia' => 'MidtransBilling',
        'Pengaturan' => ['Mode' => 'Sandbox', 'KunciKlien' => 'SB-Mid-client-uji'],
        'Kredensial' => ['KunciServer' => KUNCI_SERVER_BILLING],
    ]);
    Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
        'token' => TOKEN_SNAP_UJI,
        'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/'.TOKEN_SNAP_UJI,
    ])]);
}

/** Tagihan terbuka untuk tenant ini (paket & siklus dari pilihan Owner, BR-P08.4). */
function BuatTagihanGerbangUji(TestCase $tes, Pengguna $pemilik, Tenant $tenant, string $paket = 'PRO', string $siklus = 'Bulanan'): TagihanLangganan
{
    BantuanTagihan::Masuk($tes, $pemilik, $tenant)
        ->post(BantuanTagihan::Url('/kelola/langganan/tagihan'), ['KodePaket' => $paket, 'Siklus' => $siklus])
        ->assertSessionHasNoErrors();
    $tes->flushSession();

    return TagihanLangganan::query()->withoutGlobalScopes()->where('IdTenant', $tenant->Id)->latest('Id')->firstOrFail();
}

/** Owner menekan "Bayar online": transaksi Snap dibuat dan baris pembayaran `Gateway` menunggu notifikasi. */
function MulaiBayarOnlineUji(TestCase $tes, Pengguna $pemilik, Tenant $tenant, TagihanLangganan $tagihan): TestResponse
{
    $respons = BantuanTagihan::Masuk($tes, $pemilik, $tenant)
        ->postJson(BantuanTagihan::Url("/kelola/langganan/tagihan/{$tagihan->Uuid}/bayar-online"));
    $tes->flushSession();

    return $respons;
}

/** Bukti transfer manual untuk tagihan yang sama, dipakai menguji jalur manual & aturan saling-blokir. */
function UnggahBuktiGerbangUji(TestCase $tes, Pengguna $pemilik, Tenant $tenant, TagihanLangganan $tagihan): void
{
    BantuanTagihan::UnggahBuktiLangsung($tenant, $pemilik, $tagihan);
    $tes->flushSession();
}

/**
 * Notifikasi HTTP Midtrans. Tanda tangan dihitung dari isi yang sudah digabung, jadi menimpa `gross_amount` atau
 * `status_code` tetap menghasilkan notifikasi yang sah — kecuali `signature_key` ikut ditimpa dengan sengaja.
 *
 * @param  array<string, string>  $timpa
 */
function KirimNotifikasiBilling(TestCase $tes, string $nomorPesanan, string $status, string $jumlah, array $timpa = []): TestResponse
{
    $isi = [
        'order_id' => $nomorPesanan,
        'status_code' => '200',
        'gross_amount' => $jumlah,
        'transaction_status' => $status,
        'fraud_status' => 'accept',
        'transaction_id' => 'trx-midtrans-uji-1',
        ...$timpa,
    ];
    $isi['signature_key'] = $timpa['signature_key']
        ?? hash('sha512', $isi['order_id'].$isi['status_code'].$isi['gross_amount'].KUNCI_SERVER_BILLING);

    return $tes->postJson('/webhook/billing/midtrans', $isi);
}

function LanggananGerbangUji(Tenant $tenant): Langganan
{
    return Langganan::query()->where('IdTenant', $tenant->Id)->sole();
}

function PembayaranGerbangUji(TagihanLangganan $tagihan): PembayaranLangganan
{
    return PembayaranLangganan::query()->withoutGlobalScopes()->where('IdTagihanLangganan', $tagihan->Id)->latest('Id')->firstOrFail();
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-27 03:00:00');
    BantuanTagihan::SiapkanPrasyarat();
    AturGerbangBilling();
    Mail::fake();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('BR-P08.11 membuat transaksi di gerbang', function (): void {
    it('Owner menekan Bayar online: transaksi Snap dibuat dan pembayaran Gateway menunggu notifikasi', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $tagihan = BuatTagihanGerbangUji($this, $pemilik, $tenant);

        MulaiBayarOnlineUji($this, $pemilik, $tenant, $tagihan)
            ->assertOk()
            ->assertJson(['Token' => TOKEN_SNAP_UJI]);

        $pembayaran = PembayaranGerbangUji($tagihan);
        expect($pembayaran->Metode)->toBe(MetodePembayaranLangganan::Gateway)
            ->and($pembayaran->Status)->toBe(StatusPembayaranLangganan::Menunggu)
            ->and($pembayaran->Jumlah)->toBe($tagihan->Total)
            // Nomor pesanan memuat IdTenant + ULID pembayaran, sehingga webhook bisa menetapkan tenant tanpa
            // query lintas tenant dan tagihan tetap terbuka sampai notifikasi datang.
            ->and($pembayaran->RefGateway)->toBe(NomorPesananBilling::Buat($tenant->Id, $pembayaran->Uuid))
            ->and($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Terbit);

        Http::assertSent(fn ($permintaan): bool => $permintaan->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $permintaan['transaction_details']['order_id'] === $pembayaran->RefGateway
            && $permintaan['transaction_details']['gross_amount'] === (int) $tagihan->Total);
    });

    it('gerbang billing belum dikonfigurasi: pembayaran online ditolak, transfer manual tetap jalan', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $tagihan = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        config()->set('integrasi.GerbangBilling', null);

        MulaiBayarOnlineUji($this, $pemilik, $tenant, $tagihan)
            ->assertStatus(422)
            ->assertJsonPath('Galat.Kode', 'GerbangBillingTidakAktif');

        expect(PembayaranLangganan::query()->withoutGlobalScopes()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('bukti transfer yang sedang diverifikasi memblokir bayar online (risiko bayar dua kali)', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $tagihan = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        UnggahBuktiGerbangUji($this, $pemilik, $tenant, $tagihan);

        MulaiBayarOnlineUji($this, $pemilik, $tenant, $tagihan)
            ->assertStatus(422)
            ->assertJsonPath('Galat.Kode', 'PembayaranMasihDiverifikasi');
        Http::assertNothingSent();
    });
});

describe('BR-P08.11 notifikasi webhook', function (): void {
    it('notifikasi uji coba (order_id test/sample) tetap wajib bertanda tangan sah (audit PAY-P2-07)', function (): void {
        // Tanpa tanda tangan sah: ditolak, tidak lagi dibalas "siap menerima".
        KirimNotifikasiBilling($this, 'test-123', 'settlement', '10000.00', ['signature_key' => 'palsu'])->assertStatus(401);
        KirimNotifikasiBilling($this, 'sample-1', 'settlement', '10000.00', ['signature_key' => 'palsu'])->assertStatus(401);
        $this->postJson('/webhook/billing/midtrans', [])->assertStatus(401);

        // Uji dari dasbor Midtrans memakai kunci server yang sama, jadi lolos dan tidak mengubah pembayaran apa pun.
        KirimNotifikasiBilling($this, 'test-123', 'settlement', '10000.00')->assertOk()->assertJson(['Diterima' => true]);
    });

    it('settlement melunasi tagihan, mengaktifkan langganan, dan tercatat di audit tanpa pelaku orang', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $tagihan = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        MulaiBayarOnlineUji($this, $pemilik, $tenant, $tagihan);
        $pembayaran = PembayaranGerbangUji($tagihan);

        KirimNotifikasiBilling($this, (string) $pembayaran->RefGateway, 'settlement', $tagihan->Total)
            ->assertOk()
            ->assertJson(['Diterima' => true]);

        $langganan = LanggananGerbangUji($tenant);
        expect($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Diterima)
            ->and($pembayaran->JumlahDiterima)->toBe($tagihan->Total)
            // Pelunasan gerbang tidak punya verifikator manusia (bedanya dengan transfer manual).
            ->and($pembayaran->IdPenggunaPengelolaVerifikator)->toBeNull()
            ->and($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Lunas)
            ->and($langganan->Status)->toBe(StatusLangganan::Aktif)
            ->and($langganan->PeriodeMulai?->toDateTimeString())->toBe('2026-09-27 03:00:00')
            ->and($langganan->PeriodeSelesai?->toDateTimeString())->toBe('2026-10-27 03:00:00');

        // Pelakunya sistem, jadi audit masuk LogAudit tenant tanpa pengguna — bukan LogAuditPengelola, yang dipakai
        // jalur manual karena di sana ada verifikator yang bertanggung jawab.
        $log = LogAudit::query()->withoutGlobalScopes()->where('Peristiwa', 'langganan.pembayaran-gerbang-lunas')->sole();
        expect($log->IdPengguna)->toBeNull()
            ->and($log->IdTenant)->toBe($tenant->Id)
            ->and($log->NilaiBaru['IdTransaksiGerbang'])->toBe('trx-midtrans-uji-1');
    });

    it('notifikasi diulang berkali-kali tidak memperpanjang periode dua kali (idempoten)', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $tagihan = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        MulaiBayarOnlineUji($this, $pemilik, $tenant, $tagihan);
        $nomor = (string) PembayaranGerbangUji($tagihan)->RefGateway;

        KirimNotifikasiBilling($this, $nomor, 'settlement', $tagihan->Total)->assertOk();
        $selesaiPertama = LanggananGerbangUji($tenant)->PeriodeSelesai?->toDateTimeString();

        // Midtrans mengulang notifikasi sampai dijawab 200; ulangan harus jadi tanpa efek, bukan perpanjangan baru.
        KirimNotifikasiBilling($this, $nomor, 'settlement', $tagihan->Total)->assertOk()->assertJson(['Diterima' => true]);
        KirimNotifikasiBilling($this, $nomor, 'settlement', $tagihan->Total)->assertOk();

        expect(LanggananGerbangUji($tenant)->PeriodeSelesai?->toDateTimeString())->toBe($selesaiPertama)
            ->and(PembayaranLangganan::query()->withoutGlobalScopes()->where('Status', StatusPembayaranLangganan::Menunggu->value)->count())->toBe(0)
            ->and(LogAudit::query()->withoutGlobalScopes()->where('Peristiwa', 'langganan.pembayaran-gerbang-lunas')->count())->toBe(1);
    });

    it('tanda tangan tidak sah dijawab 401 dan tidak menyentuh tagihan', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $tagihan = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        MulaiBayarOnlineUji($this, $pemilik, $tenant, $tagihan);
        $nomor = (string) PembayaranGerbangUji($tagihan)->RefGateway;

        KirimNotifikasiBilling($this, $nomor, 'settlement', $tagihan->Total, ['signature_key' => 'tanda-tangan-palsu'])
            ->assertStatus(401)
            ->assertJsonPath('Galat.Kode', 'TandaTanganTidakSah');

        expect($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Terbit)
            ->and(LanggananGerbangUji($tenant)->Status)->not->toBe(StatusLangganan::Aktif);
    });

    it('nomor pesanan sah tetapi tidak dikenal dijawab 200 tanpa efek, agar gerbang berhenti mengulang', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $asing = NomorPesananBilling::Buat($tenant->Id, (string) Str::ulid());

        KirimNotifikasiBilling($this, $asing, 'settlement', '222000.00')
            ->assertOk()
            ->assertJson(['Diterima' => false]);

        expect(PembayaranLangganan::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('deny & expire menolak pembayaran beralasan, tagihan tetap terbuka supaya bisa dibayar ulang', function (string $status): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $tagihan = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        MulaiBayarOnlineUji($this, $pemilik, $tenant, $tagihan);
        $pembayaran = PembayaranGerbangUji($tagihan);

        KirimNotifikasiBilling($this, (string) $pembayaran->RefGateway, $status, $tagihan->Total)
            ->assertOk()
            ->assertJson(['Diterima' => true]);

        expect($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Ditolak)
            ->and($pembayaran->AlasanTolak)->toContain($status)
            ->and($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Terbit)
            ->and(LanggananGerbangUji($tenant)->Status)->not->toBe(StatusLangganan::Aktif);
    })->with(['deny', 'expire']);

    it('capture yang masih ditinjau (fraud challenge) belum melunasi apa pun', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $tagihan = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        MulaiBayarOnlineUji($this, $pemilik, $tenant, $tagihan);
        $pembayaran = PembayaranGerbangUji($tagihan);

        KirimNotifikasiBilling($this, (string) $pembayaran->RefGateway, 'capture', $tagihan->Total, ['fraud_status' => 'challenge'])
            ->assertOk();

        expect($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Menunggu)
            ->and($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Terbit);
    });

    it('jumlah yang dibayar berbeda dari total tidak dilunasi otomatis; pembayaran tetap menunggu verifikasi manual', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();
        $tagihan = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        MulaiBayarOnlineUji($this, $pemilik, $tenant, $tagihan);
        $pembayaran = PembayaranGerbangUji($tagihan);

        KirimNotifikasiBilling($this, (string) $pembayaran->RefGateway, 'settlement', '1000.00')
            ->assertOk()
            ->assertJson(['Diterima' => false]);

        expect($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Menunggu)
            ->and($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Terbit);
    });

    it('nomor pesanan milik tenant lain tidak bisa melunasi tagihan tenant ini (isolasi tenant)', function (): void {
        ['Tenant' => $tenantA, 'Pengguna' => $pemilikA] = BantuanTagihan::DaftarTenant();
        ['Tenant' => $tenantB] = BantuanTagihan::DaftarTenant('bayu@sinarjaya.id', '081298765432', 'Sinar Jaya');
        $tagihanA = BuatTagihanGerbangUji($this, $pemilikA, $tenantA);
        MulaiBayarOnlineUji($this, $pemilikA, $tenantA, $tagihanA);
        $pembayaranA = PembayaranGerbangUji($tagihanA);

        // ULID pembayaran tenant A, tetapi bagian IdTenant diganti tenant B. Karena tenant ditetapkan dari nomor
        // pesanan lalu pencarian lewat scope MilikTenant, pembayaran tenant A tidak terlihat sama sekali dari
        // lingkup tenant B — isolasinya struktural, bukan hasil perbandingan.
        $nomorPalsu = NomorPesananBilling::Buat($tenantB->Id, $pembayaranA->Uuid);
        KirimNotifikasiBilling($this, $nomorPalsu, 'settlement', $tagihanA->Total)
            ->assertOk()
            ->assertJson(['Diterima' => false]);

        expect($pembayaranA->refresh()->Status)->toBe(StatusPembayaranLangganan::Menunggu)
            ->and($tagihanA->refresh()->Status)->toBe(StatusTagihanLangganan::Terbit);
    });
});

describe('BR-P08.11 jalur gerbang = jalur manual', function (): void {
    it('perpanjangan lewat gerbang menyambung periode & mempertahankan jangkar grandfathering, sama seperti verifikasi manual', function (): void {
        ['Tenant' => $tenant, 'Pengguna' => $pemilik] = BantuanTagihan::DaftarTenant();

        // 1. Aktivasi dibayar transfer manual dan diverifikasi Keuangan.
        $aktivasi = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        UnggahBuktiGerbangUji($this, $pemilik, $tenant, $aktivasi);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);
        app(TerimaPembayaranLangganan::class)->Jalankan($keuangan, PembayaranGerbangUji($aktivasi)->Uuid, $aktivasi->Total);

        $aktivasi->refresh();
        expect(LanggananGerbangUji($tenant)->PeriodeSelesai?->toDateTimeString())->toBe('2026-10-27 03:00:00');

        // 2. Perpanjangan paket yang sama dibayar lewat gerbang, beberapa hari sebelum periode berjalan habis.
        Carbon::setTestNow('2026-10-20 04:00:00');
        $perpanjangan = BuatTagihanGerbangUji($this, $pemilik, $tenant);
        MulaiBayarOnlineUji($this, $pemilik, $tenant, $perpanjangan);
        KirimNotifikasiBilling($this, (string) PembayaranGerbangUji($perpanjangan)->RefGateway, 'settlement', $perpanjangan->Total)->assertOk();

        $langganan = LanggananGerbangUji($tenant);
        expect($perpanjangan->refresh()->Status)->toBe(StatusTagihanLangganan::Lunas)
            // Menyambung dari akhir periode berjalan, bukan dari tanggal bayar: 7 hari sisa tidak hilang.
            ->and($langganan->PeriodeMulai?->toDateTimeString())->toBe('2026-10-27 03:00:00')
            ->and($langganan->PeriodeSelesai?->toDateTimeString())->toBe('2026-11-27 03:00:00')
            // Jangkar grandfathering harga (BR-P04.1) diteruskan dari tagihan lunas sebelumnya, paket sama.
            ->and($perpanjangan->MulaiLanggananPaket?->toDateString())->toBe($aktivasi->MulaiLanggananPaket?->toDateString());
    });
});
