<?php

declare(strict_types=1);

use App\Domain\Pengelola\Integrasi\Penguji\PengujiDokuBilling;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
 * P-05 penyedia Gerbang billing (P-08 langkah 3): Client ID & secret key DOKU milik Payoung diuji dengan menanyakan
 * status invoice acak yang pasti tidak ada, lewat panggilan baca bertanda tangan. DOKU menjawab 401/403 bila
 * kredensial salah dan 404 bila kredensial benar tetapi invoice-nya tidak dikenal — jadi 404 adalah bukti kredensial
 * diterima, tanpa membuat transaksi palsu.
 */

const ID_KLIEN_UJI_BILLING = 'BRN-0201-1700000000000';
const KUNCI_UJI_BILLING = 'SK-rahasia-billing-doku-4321';

it('kredensial benar: 404 dari DOKU berarti diterima, dengan panggilan GET bertanda tangan ke invoice acak', function (): void {
    Http::fake(['api-sandbox.doku.com/*' => Http::response(['error' => ['message' => 'not found']], 404)]);

    $hasil = (new PengujiDokuBilling)->Uji(['Mode' => 'Sandbox', 'IdKlien' => ID_KLIEN_UJI_BILLING], ['KunciRahasia' => KUNCI_UJI_BILLING]);

    expect($hasil->berhasil)->toBeTrue()
        ->and($hasil->pesan)->toContain('Sandbox');
    Http::assertSent(function ($permintaan): bool {
        $target = parse_url($permintaan->url(), PHP_URL_PATH);
        $komponen = 'Client-Id:'.ID_KLIEN_UJI_BILLING
            ."\nRequest-Id:".$permintaan->header('Request-Id')[0]
            ."\nRequest-Timestamp:".$permintaan->header('Request-Timestamp')[0]
            ."\nRequest-Target:{$target}";

        return $permintaan->method() === 'GET'
            && str_starts_with($permintaan->url(), 'https://api-sandbox.doku.com/orders/v1/status/UJI-')
            && $permintaan->header('Signature') === ['HMACSHA256='.base64_encode(hash_hmac('sha256', $komponen, KUNCI_UJI_BILLING, true))];
    });
});

it('kredensial ditolak DOKU (401 atau 403) dilaporkan gagal tanpa membocorkan secret key', function (int $kode): void {
    Http::fake(['api-sandbox.doku.com/*' => Http::response(['message' => ['Invalid signature '.KUNCI_UJI_BILLING]], $kode)]);

    $hasil = (new PengujiDokuBilling)->Uji(['Mode' => 'Sandbox', 'IdKlien' => ID_KLIEN_UJI_BILLING], ['KunciRahasia' => KUNCI_UJI_BILLING]);

    expect($hasil->berhasil)->toBeFalse()
        ->and($hasil->pesan)->toContain('ditolak DOKU')
        ->and($hasil->pesan)->not->toContain(KUNCI_UJI_BILLING);
})->with([401, 403]);

it('mode produksi memakai alamat produksi, bukan sandbox', function (): void {
    Http::fake(['api.doku.com/*' => Http::response([], 404)]);

    $hasil = (new PengujiDokuBilling)->Uji(['Mode' => 'Produksi', 'IdKlien' => ID_KLIEN_UJI_BILLING], ['KunciRahasia' => KUNCI_UJI_BILLING]);

    expect($hasil->berhasil)->toBeTrue()
        ->and($hasil->pesan)->toContain('Produksi');
    Http::assertSent(fn ($permintaan): bool => str_starts_with($permintaan->url(), 'https://api.doku.com/orders/v1/status/'));
});

it('Client ID atau secret key kosong dilaporkan gagal tanpa menghubungi DOKU', function (array $pengaturan, array $kredensial, string $bagian): void {
    Http::fake();

    $hasil = (new PengujiDokuBilling)->Uji($pengaturan, $kredensial);

    expect($hasil->berhasil)->toBeFalse()
        ->and($hasil->pesan)->toContain($bagian);
    Http::assertNothingSent();
})->with([
    'tanpa Client ID' => [['Mode' => 'Sandbox'], ['KunciRahasia' => KUNCI_UJI_BILLING], 'Client ID'],
    'tanpa secret key' => [['Mode' => 'Sandbox', 'IdKlien' => ID_KLIEN_UJI_BILLING], [], 'Secret key'],
]);

it('DOKU bermasalah (5xx) atau tidak terjangkau dilaporkan gagal, pesan bersih dari secret key', function (): void {
    Http::fake(['api-sandbox.doku.com/*' => Http::response([], 503)]);
    $uji = (new PengujiDokuBilling)->Uji(['IdKlien' => ID_KLIEN_UJI_BILLING], ['KunciRahasia' => KUNCI_UJI_BILLING]);
    expect($uji->berhasil)->toBeFalse()->and($uji->pesan)->toContain('503');

    Http::fake(['api-sandbox.doku.com/*' => fn () => throw new ConnectionException('timeout memanggil '.KUNCI_UJI_BILLING)]);
    $putus = (new PengujiDokuBilling)->Uji(['IdKlien' => ID_KLIEN_UJI_BILLING], ['KunciRahasia' => KUNCI_UJI_BILLING]);
    expect($putus->berhasil)->toBeFalse()
        ->and($putus->pesan)->toContain('Tidak bisa menghubungi DOKU')
        ->and($putus->pesan)->not->toContain(KUNCI_UJI_BILLING);
});
