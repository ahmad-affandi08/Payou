<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Integrasi\GerbangPembayaran\GalatGerbang;
use App\Domain\Integrasi\GerbangPembayaran\PembuatGerbangPembayaran;
use App\Domain\Integrasi\GerbangPembayaran\PermintaanQris;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/*
 * Adaptor gerbang pembayaran QRIS dinamis (v2.04): bentuk permintaan & tanda tangan sesuai dokumentasi penyedia,
 * verifikasi notifikasi (tanda tangan palsu ditolak), dan pemetaan status.
 */

function PermintaanQrisUji(): PermintaanQris
{
    return new PermintaanQris('QR-SLB-0001', Uang::Dari('25000'), 'Penjualan Kopi Senja', CarbonImmutable::now()->addMinutes(15), 'https://payoung.id/webhook/uji');
}

function Gerbang(string $penyedia, array $pengaturan, array $kredensial)
{
    return app(PembuatGerbangPembayaran::class)->Buat($penyedia, $pengaturan, $kredensial);
}

function WebhookJson(array $isi, array $header = []): Request
{
    $permintaan = Request::create('/webhook/uji', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($isi));
    foreach ($header as $nama => $nilai) {
        $permintaan->headers->set($nama, $nilai);
    }

    return $permintaan;
}

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('Midtrans: charge QRIS dengan Basic server key, webhook SHA512, status settlement = lunas', function (): void {
    Http::fake(['api.sandbox.midtrans.com/v2/charge' => Http::response([
        'status_code' => '201', 'transaction_id' => 'trx-1', 'qr_string' => '00020101021226...', 'expiry_time' => '2026-09-26 10:15:00',
    ])]);
    $gerbang = Gerbang('Midtrans', ['Mode' => 'Sandbox', 'Akuisitor' => 'gopay'], ['KunciServer' => 'SB-Mid-server-abc']);
    $hasil = $gerbang->BuatQris(PermintaanQrisUji());
    expect($hasil->isiQr)->toBe('00020101021226...')->and($hasil->idReferensi)->toBe('trx-1');
    Http::assertSent(fn (PermintaanHttp $r) => $r['payment_type'] === 'qris'
        && $r['transaction_details'] === ['order_id' => 'QR-SLB-0001', 'gross_amount' => 25000]
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-abc:'))
        && $r->hasHeader('X-Override-Notification', 'https://payoung.id/webhook/uji'));

    $isi = ['order_id' => 'QR-SLB-0001', 'status_code' => '200', 'gross_amount' => '25000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'trx-1'];
    $sah = WebhookJson($isi + ['signature_key' => hash('sha512', 'QR-SLB-0001'.'200'.'25000.00'.'SB-Mid-server-abc')]);
    $palsu = WebhookJson($isi + ['signature_key' => str_repeat('0', 128)]);
    expect($gerbang->UraiWebhook($sah)?->status)->toBe(StatusPembayaranGerbang::Lunas)
        ->and($gerbang->UraiWebhook($palsu))->toBeNull();
});

it('Midtrans: galat gerbang dilempar sebagai GalatGerbang tanpa membocorkan kunci', function (): void {
    Http::fake(['*' => Http::response(['status_code' => '401', 'status_message' => 'Unknown key SB-Mid-server-abc'], 401)]);
    expect(fn () => Gerbang('Midtrans', ['Mode' => 'Sandbox'], ['KunciServer' => 'SB-Mid-server-abc'])->BuatQris(PermintaanQrisUji()))
        ->toThrow(GalatGerbang::class, 'Unknown key ••••');
});

it('Xendit: QR dinamis, callback x-callback-token, status dari daftar pembayaran', function (): void {
    Http::fake([
        'api.xendit.co/qr_codes' => Http::response(['id' => 'qr_1', 'qr_string' => '000201...', 'status' => 'ACTIVE', 'expires_at' => '2026-09-26T03:15:00Z']),
        'api.xendit.co/qr_codes/qr_1/payments' => Http::response(['data' => [['status' => 'SUCCEEDED']]]),
    ]);
    $gerbang = Gerbang('Xendit', [], ['KunciRahasia' => 'xnd_dev', 'TokenCallback' => 'tok-cb']);
    expect($gerbang->BuatQris(PermintaanQrisUji())->idReferensi)->toBe('qr_1')
        ->and($gerbang->CekStatus('QR-SLB-0001', 'qr_1'))->toBe(StatusPembayaranGerbang::Lunas);
    Http::assertSent(fn (PermintaanHttp $r) => $r->url() === 'https://api.xendit.co/qr_codes' && $r['type'] === 'DYNAMIC' && $r['amount'] === 25000 && $r->hasHeader('api-version', '2022-07-31'));

    $isi = ['event' => 'qr.payment', 'data' => ['reference_id' => 'QR-SLB-0001', 'qr_id' => 'qr_1', 'amount' => 25000, 'status' => 'SUCCEEDED']];
    expect($gerbang->UraiWebhook(WebhookJson($isi, ['x-callback-token' => 'tok-cb']))?->status)->toBe(StatusPembayaranGerbang::Lunas)
        ->and($gerbang->UraiWebhook(WebhookJson($isi, ['x-callback-token' => 'salah'])))->toBeNull();
});

it('Tripay: signature HMAC permintaan & callback atas body mentah', function (): void {
    Http::fake(['tripay.co.id/api-sandbox/transaction/create' => Http::response(['success' => true, 'data' => [
        'reference' => 'T123', 'qr_string' => '000201...', 'expired_time' => 1790000000,
    ]])]);
    $gerbang = Gerbang('Tripay', ['Mode' => 'Sandbox', 'KodeMerchant' => 'T0001', 'KanalQris' => 'QRIS'], ['KunciApi' => 'api-key', 'KunciPrivat' => 'privat']);
    expect($gerbang->BuatQris(PermintaanQrisUji())->idReferensi)->toBe('T123');
    Http::assertSent(fn (PermintaanHttp $r) => $r['method'] === 'QRIS' && $r['signature'] === hash_hmac('sha256', 'T0001QR-SLB-000125000', 'privat'));

    $isi = ['merchant_ref' => 'QR-SLB-0001', 'reference' => 'T123', 'status' => 'PAID', 'total_amount' => 25000];
    $mentah = (string) json_encode($isi);
    expect($gerbang->UraiWebhook(WebhookJson($isi, ['X-Callback-Signature' => hash_hmac('sha256', $mentah, 'privat')]))?->status)->toBe(StatusPembayaranGerbang::Lunas)
        ->and($gerbang->UraiWebhook(WebhookJson($isi, ['X-Callback-Signature' => 'palsu'])))->toBeNull();
});

it('Duitku: signature MD5 permintaan, callback form, kode merchant harus cocok', function (): void {
    Http::fake(['sandbox.duitku.com/webapi/api/merchant/v2/inquiry' => Http::response(['statusCode' => '00', 'reference' => 'DK1', 'qrString' => '000201...'])]);
    $gerbang = Gerbang('Duitku', ['Mode' => 'Sandbox', 'KodeMerchant' => 'D0001', 'KanalQris' => 'SP'], ['KunciApi' => 'kunci']);
    expect($gerbang->BuatQris(PermintaanQrisUji())->idReferensi)->toBe('DK1');
    Http::assertSent(fn (PermintaanHttp $r) => $r['paymentMethod'] === 'SP' && $r['signature'] === hash('md5', 'D0001QR-SLB-000125000kunci'));

    $form = ['merchantCode' => 'D0001', 'amount' => '25000', 'merchantOrderId' => 'QR-SLB-0001', 'resultCode' => '00', 'reference' => 'DK1'];
    $sah = Request::create('/webhook/duitku', 'POST', $form + ['signature' => hash('md5', 'D000125000QR-SLB-0001kunci')]);
    $palsu = Request::create('/webhook/duitku', 'POST', $form + ['signature' => 'palsu']);
    expect($gerbang->UraiWebhook($sah)?->status)->toBe(StatusPembayaranGerbang::Lunas)
        ->and($gerbang->UraiWebhook($palsu))->toBeNull();
});

it('iPaymu: permintaan bertanda tangan HMAC; notifikasi dikonfirmasi ulang ke iPaymu', function (): void {
    Http::fake([
        'sandbox.ipaymu.com/api/v2/payment/direct' => Http::response(['Status' => 200, 'Data' => ['TransactionId' => 99, 'QrString' => '000201...']]),
        'sandbox.ipaymu.com/api/v2/transaction' => Http::response(['Status' => 200, 'Data' => ['Status' => 1]]),
    ]);
    $gerbang = Gerbang('Ipaymu', ['Mode' => 'Sandbox', 'NomorVa' => '0000001234'], ['KunciApi' => 'kunci-ipaymu']);
    expect($gerbang->BuatQris(PermintaanQrisUji())->idReferensi)->toBe('99');
    Http::assertSent(function (PermintaanHttp $r): bool {
        if (! str_ends_with($r->url(), '/payment/direct')) {
            return false;
        }
        $harapan = hash_hmac('sha256', 'POST:0000001234:'.strtolower(hash('sha256', $r->body())).':kunci-ipaymu', 'kunci-ipaymu');

        return $r->hasHeader('signature', $harapan) && $r->hasHeader('va', '0000001234') && $r['paymentMethod'] === 'qris';
    });

    $notif = Request::create('/webhook/ipaymu', 'POST', ['trx_id' => '99', 'reference_id' => 'QR-SLB-0001', 'status' => 'berhasil']);
    expect($gerbang->UraiWebhook($notif)?->status)->toBe(StatusPembayaranGerbang::Lunas);
});

it('DOKU: header tanda tangan HMACSHA256 & notifikasi palsu ditolak; hasil berupa halaman bayar', function (): void {
    Http::fake(['api-sandbox.doku.com/checkout/v1/payment' => Http::response(['response' => ['payment' => ['url' => 'https://sandbox.doku.com/checkout/link/abc', 'token_id' => 'tok']]])]);
    $gerbang = Gerbang('Doku', ['Mode' => 'Sandbox', 'IdKlien' => 'BRN-001'], ['KunciRahasia' => 'SK-doku']);
    $hasil = $gerbang->BuatQris(PermintaanQrisUji());
    expect($hasil->halamanBayar)->toBeTrue()->and($hasil->isiQr)->toBe('https://sandbox.doku.com/checkout/link/abc');
    Http::assertSent(function (PermintaanHttp $r): bool {
        $komponen = 'Client-Id:BRN-001'."\nRequest-Id:".$r->header('Request-Id')[0]."\nRequest-Timestamp:".$r->header('Request-Timestamp')[0]
            ."\nRequest-Target:/checkout/v1/payment\nDigest:".base64_encode(hash('sha256', $r->body(), true));

        return $r->hasHeader('Signature', 'HMACSHA256='.base64_encode(hash_hmac('sha256', $komponen, 'SK-doku', true)));
    });

    $notif = WebhookJson(['order' => ['invoice_number' => 'QR-SLB-0001', 'amount' => 25000], 'transaction' => ['status' => 'SUCCESS']], [
        'Client-Id' => 'BRN-001', 'Request-Id' => 'r1', 'Request-Timestamp' => '2026-09-26T03:00:00Z', 'Signature' => 'HMACSHA256=palsu',
    ]);
    expect($gerbang->UraiWebhook($notif))->toBeNull();
});

// v2.06: gerbang aktif dibaca per tenant (`AmbilAktifTenant`, diuji di GerbangPembayaranTenantTes); di sini hanya pabrik.
it('penyedia tidak dikenal → null', function (): void {
    expect(app(PembuatGerbangPembayaran::class)->Buat('Tidakada', [], []))->toBeNull();
});
