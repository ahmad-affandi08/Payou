<?php

declare(strict_types=1);

use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Pengelola\Tagihan\Aksi\RekonsiliasiPembayaranGerbangLangganan;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Pendukung\Tenant\BantuanTagihan;
use Tests\TestCase;

/*
 * P-08 langkah 3 | BR-P08.11 (PRD v4.06): pembayaran langganan lewat gerbang yang notifikasi webhook-nya tidak pernah
 * tiba direkonsiliasi tiap 15 menit lewat API status Midtrans, dan hasilnya diproses jalur yang sama dengan webhook.
 */

// Jawaban API status per putaran (Jawaban null = koneksi gagal).
$GLOBALS['StatusGerbangRekonsiliasi'] = ['Jawaban' => null, 'KodeHttp' => 200, 'Dipanggil' => 0];

/** @param  array<string, mixed>|null  $jawaban */
function AturStatusGerbangRekonsiliasi(?array $jawaban, int $kodeHttp = 200): void
{
    $GLOBALS['StatusGerbangRekonsiliasi'] = ['Jawaban' => $jawaban, 'KodeHttp' => $kodeHttp, 'Dipanggil' => 0];
}

function SiapkanGerbangRekonsiliasi(): void
{
    config()->set('integrasi.GerbangBilling', [
        'Penyedia' => 'MidtransBilling',
        'Pengaturan' => ['Mode' => 'Sandbox', 'KunciKlien' => 'SB-Mid-client-uji'],
        'Kredensial' => ['KunciServer' => 'SB-Mid-server-rekonsiliasi-uji'],
    ]);
    Http::fake(function (Request $permintaan) {
        if (str_contains($permintaan->url(), '/snap/v1/transactions')) {
            return Http::response(['token' => 'tok-rekon', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/tok-rekon']);
        }

        if (str_starts_with($permintaan->url(), 'https://api.sandbox.midtrans.com/v2/') && str_ends_with($permintaan->url(), '/status')) {
            $GLOBALS['StatusGerbangRekonsiliasi']['Dipanggil']++;
            $jawaban = $GLOBALS['StatusGerbangRekonsiliasi']['Jawaban'];

            if ($jawaban === null) {
                throw new ConnectionException('Gerbang tidak terjangkau.');
            }

            return Http::response($jawaban, $GLOBALS['StatusGerbangRekonsiliasi']['KodeHttp']);
        }

        return Http::response([], 404);
    });
}

/** @return array{Tenant: Tenant, Tagihan: TagihanLangganan, Pembayaran: PembayaranLangganan} */
function SiapkanPembayaranTersangkut(TestCase $tes): array
{
    /** @var array{Tenant: Tenant, Pengguna: Pengguna} $data */
    $data = BantuanTagihan::DaftarTenant();
    BantuanTagihan::Masuk($tes, $data['Pengguna'], $data['Tenant'])
        ->post(BantuanTagihan::Url('/kelola/langganan/tagihan'), ['KodePaket' => 'PRO', 'Siklus' => 'Bulanan'])
        ->assertSessionHasNoErrors();
    $tes->flushSession();
    $tagihan = TagihanLangganan::query()->withoutGlobalScopes()->where('IdTenant', $data['Tenant']->Id)->latest('Id')->firstOrFail();

    BantuanTagihan::Masuk($tes, $data['Pengguna'], $data['Tenant'])
        ->postJson(BantuanTagihan::Url("/kelola/langganan/tagihan/{$tagihan->Uuid}/bayar-online"))
        ->assertOk();
    $tes->flushSession();

    $pembayaran = PembayaranLangganan::query()->withoutGlobalScopes()->where('IdTagihanLangganan', $tagihan->Id)->latest('Id')->firstOrFail();

    return ['Tenant' => $data['Tenant'], 'Tagihan' => $tagihan, 'Pembayaran' => $pembayaran];
}

/** @return array<string, mixed> */
function JawabanStatusMidtrans(PembayaranLangganan $pembayaran, string $status, string $jumlah): array
{
    return [
        'status_code' => $status === 'settlement' ? '200' : '202',
        'order_id' => $pembayaran->RefGateway,
        'gross_amount' => $jumlah,
        'transaction_status' => $status,
        'fraud_status' => 'accept',
        'transaction_id' => 'trx-rekon-1',
    ];
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-27 03:00:00');
    BantuanTagihan::SiapkanPrasyarat();
    SiapkanGerbangRekonsiliasi();
    Mail::fake();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('BR-P08.11 rekonsiliasi pembayaran gerbang tersangkut', function (): void {
    it('settlement yang webhook-nya hilang melunasi tagihan lewat jalur webhook, dan putaran ulang tidak memperpanjang dua kali', function (): void {
        ['Tenant' => $tenant, 'Tagihan' => $tagihan, 'Pembayaran' => $pembayaran] = SiapkanPembayaranTersangkut($this);
        AturStatusGerbangRekonsiliasi(JawabanStatusMidtrans($pembayaran, 'settlement', $tagihan->Total));
        Carbon::setTestNow('2026-09-27 03:16:00');

        $this->artisan('tagihan:rekonsiliasi-gerbang')
            ->expectsOutput('1 pembayaran diperiksa, 1 selesai, 0 masih menunggu, 0 gagal diperiksa.')
            ->assertSuccessful();

        $langganan = Langganan::query()->where('IdTenant', $tenant->Id)->sole();
        expect($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Diterima)
            ->and($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Lunas)
            ->and($langganan->Status)->toBe(StatusLangganan::Aktif);
        $selesai = $langganan->PeriodeSelesai?->toDateTimeString();

        // Pembayaran sudah tidak Menunggu: tidak ditanyakan lagi dan periode tidak bergeser.
        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan()['Diperiksa'])->toBe(0)
            ->and(Langganan::query()->where('IdTenant', $tenant->Id)->sole()->PeriodeSelesai?->toDateTimeString())->toBe($selesai);
    });

    it('pembayaran yang belum 15 menit tidak ditanyakan (webhook diberi kesempatan dulu)', function (): void {
        ['Tagihan' => $tagihan, 'Pembayaran' => $pembayaran] = SiapkanPembayaranTersangkut($this);
        AturStatusGerbangRekonsiliasi(JawabanStatusMidtrans($pembayaran, 'settlement', $tagihan->Total));
        Carbon::setTestNow('2026-09-27 03:10:00');

        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan()['Diperiksa'])->toBe(0)
            ->and($GLOBALS['StatusGerbangRekonsiliasi']['Dipanggil'])->toBe(0)
            ->and($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Menunggu);
    });

    it('transaksi tidak dikenal Midtrans: masih menunggu sebelum masa Snap habis, ditolak sesudahnya sehingga tagihan bisa dibayar ulang', function (): void {
        ['Tagihan' => $tagihan, 'Pembayaran' => $pembayaran] = SiapkanPembayaranTersangkut($this);
        AturStatusGerbangRekonsiliasi(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404);

        Carbon::setTestNow('2026-09-27 03:30:00');
        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan())
            ->toBe(['Diperiksa' => 1, 'Selesai' => 0, 'Menunggu' => 1, 'Gagal' => 0])
            ->and($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Menunggu);

        Carbon::setTestNow('2026-09-27 04:20:00');
        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan()['Selesai'])->toBe(1)
            ->and($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Ditolak)
            ->and($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Terbit);
    });

    it('status pending di gerbang dibiarkan menunggu', function (): void {
        ['Tagihan' => $tagihan, 'Pembayaran' => $pembayaran] = SiapkanPembayaranTersangkut($this);
        AturStatusGerbangRekonsiliasi(JawabanStatusMidtrans($pembayaran, 'pending', $tagihan->Total));
        Carbon::setTestNow('2026-09-27 03:20:00');

        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan()['Menunggu'])->toBe(1)
            ->and($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Menunggu);
    });

    it('gerbang tidak terjangkau atau jawaban untuk nomor pesanan lain: tidak mengubah apa pun, dicoba lagi nanti', function (): void {
        ['Tagihan' => $tagihan, 'Pembayaran' => $pembayaran] = SiapkanPembayaranTersangkut($this);
        Carbon::setTestNow('2026-09-27 03:20:00');

        AturStatusGerbangRekonsiliasi(null);
        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan()['Gagal'])->toBe(1);

        AturStatusGerbangRekonsiliasi([...JawabanStatusMidtrans($pembayaran, 'settlement', $tagihan->Total), 'order_id' => 'lain-01']);
        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan()['Gagal'])->toBe(1)
            ->and($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Menunggu)
            ->and($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Terbit);
    });

    it('jumlah dibayar berbeda tetap menunggu verifikasi manual dan berhenti ditanyakan setelah 7 hari', function (): void {
        ['Tagihan' => $tagihan, 'Pembayaran' => $pembayaran] = SiapkanPembayaranTersangkut($this);
        AturStatusGerbangRekonsiliasi(JawabanStatusMidtrans($pembayaran, 'settlement', '1000.00'));

        Carbon::setTestNow('2026-09-27 03:20:00');
        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan()['Gagal'])->toBe(1)
            ->and($pembayaran->refresh()->Status)->toBe(StatusPembayaranLangganan::Menunggu)
            ->and($tagihan->refresh()->Status)->toBe(StatusTagihanLangganan::Terbit);

        Carbon::setTestNow('2026-10-04 03:01:00');
        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan()['Diperiksa'])->toBe(0);
    });

    it('gerbang billing dinonaktifkan: tidak ada yang ditanyakan', function (): void {
        SiapkanPembayaranTersangkut($this);
        config()->set('integrasi.GerbangBilling', null);
        Carbon::setTestNow('2026-09-27 03:20:00');

        expect(app(RekonsiliasiPembayaranGerbangLangganan::class)->Jalankan()['Diperiksa'])->toBe(0);
    });
});
