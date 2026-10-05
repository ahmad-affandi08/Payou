<?php

declare(strict_types=1);

use App\Domain\Integrasi\MasukGoogle\KlienOauthGoogle;
use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\PenyediaIntegrasi;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiGoogle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
 * D-57 / P-05: uji koneksi Masuk dengan Google menukar kode palsu di titik token. `invalid_client` = ID/secret salah,
 * `invalid_grant` = pasangan dikenal dan hanya kodenya yang palsu.
 */

const CLIENT_ID_UJI = '1234567890-abc.apps.googleusercontent.com';

it('invalid_grant berarti Client ID dan secret diterima', function (): void {
    Http::fake([KlienOauthGoogle::URL_TOKEN => Http::response(['error' => 'invalid_grant', 'error_description' => 'Bad Request'], 400)]);

    $hasil = (new PengujiGoogle)->Uji(['ClientId' => CLIENT_ID_UJI], ['ClientSecret' => 'rahasia']);

    expect($hasil->berhasil)->toBeTrue();
    Http::assertSent(fn ($permintaan) => $permintaan['client_id'] === CLIENT_ID_UJI && $permintaan['client_secret'] === 'rahasia' && $permintaan['code'] === 'uji-koneksi-pengelola');
});

it('invalid_client berarti ID atau secret salah', function (): void {
    Http::fake([KlienOauthGoogle::URL_TOKEN => Http::response(['error' => 'invalid_client', 'error_description' => 'Unauthorized'], 401)]);

    $hasil = (new PengujiGoogle)->Uji(['ClientId' => CLIENT_ID_UJI], ['ClientSecret' => 'salah']);

    expect($hasil->berhasil)->toBeFalse()->and($hasil->pesan)->toContain('ditolak Google');
});

it('Client ID yang bukan berakhiran googleusercontent.com ditolak sebelum menghubungi Google', function (): void {
    Http::fake();

    $hasil = (new PengujiGoogle)->Uji(['ClientId' => 'bukan-client-id'], ['ClientSecret' => 'rahasia']);

    expect($hasil->berhasil)->toBeFalse();
    Http::assertNothingSent();
});

it('isian kosong dan Google tak terjangkau dilaporkan jelas, secret tidak bocor di pesan', function (): void {
    expect((new PengujiGoogle)->Uji([], [])->berhasil)->toBeFalse();

    Http::fake(fn () => throw new ConnectionException('cURL error rahasia-panjang-12345'));
    $hasil = (new PengujiGoogle)->Uji(['ClientId' => CLIENT_ID_UJI], ['ClientSecret' => 'rahasia-panjang-12345']);

    expect($hasil->berhasil)->toBeFalse()->and($hasil->pesan)->not->toContain('rahasia-panjang-12345');
});

it('katalog: Google adalah penyedia satu-satunya jenis LoginSosial dengan bidang yang benar', function (): void {
    expect(JenisIntegrasi::LoginSosial->AmbilDaftarPenyedia())->toBe([PenyediaIntegrasi::Google])
        ->and(JenisIntegrasi::LoginSosial->AmbilPenyedia())->toBe(PenyediaIntegrasi::Google)
        ->and(array_column(PenyediaIntegrasi::Google->AmbilBidangPengaturan(), 'Kunci'))->toBe(['ClientId', 'ClientIdTambahan'])
        ->and(array_column(PenyediaIntegrasi::Google->AmbilBidangKredensial(), 'Kunci'))->toBe(['ClientSecret'])
        ->and(PenyediaIntegrasi::Google->AmbilKelasPenguji())->toBe(PengujiGoogle::class);
});
