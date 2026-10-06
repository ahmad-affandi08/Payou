<?php

declare(strict_types=1);

use App\Domain\Integrasi\Push\Layanan\PengirimFcm;
use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\PenyediaIntegrasi;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiFcm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * P-05 penyedia Push (OWN-03): akun layanan Firebase diuji dengan menukarnya menjadi token akses OAuth2 di Google.
 * Uji ini membuktikan berkas JSON utuh dan kuncinya bisa menandatangani, tanpa mengirim notifikasi ke perangkat
 * siapa pun — saat integrasi diatur, belum tentu ada token perangkat yang terdaftar.
 */

/** Akun layanan contoh dengan kunci RSA sungguhan, supaya penandatanganan benar-benar diuji. */
function AkunLayananUji(array $ganti = []): string
{
    $kunci = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($kunci, $pem);

    return json_encode(array_merge([
        'type' => 'service_account',
        'project_id' => 'payoung-uji',
        'client_email' => 'push@payoung-uji.iam.gserviceaccount.com',
        'private_key' => $pem,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ], $ganti), JSON_THROW_ON_ERROR);
}

it('akun layanan sah ditukar jadi token akses dan dinyatakan berhasil', function (): void {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.uji', 'expires_in' => 3599])]);

    $hasil = (new PengujiFcm)->Uji([], ['AkunLayanan' => AkunLayananUji()]);

    expect($hasil->berhasil)->toBeTrue()
        ->and($hasil->pesan)->toContain('payoung-uji');

    // Permintaannya memang alur akun layanan Google, bukan sekadar GET apa pun.
    Http::assertSent(fn ($permintaan): bool => $permintaan['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
        && is_string($permintaan['assertion'])
        && substr_count($permintaan['assertion'], '.') === 2);
});

it('isi yang bukan JSON ditolak dengan pesan yang bisa ditindaklanjuti', function (): void {
    $hasil = (new PengujiFcm)->Uji([], ['AkunLayanan' => 'AAAA1234']);

    expect($hasil->berhasil)->toBeFalse()
        ->and($hasil->pesan)->toContain('bukan JSON');
});

it('berkas google-services.json ditolak karena tidak memuat kunci privat', function (): void {
    // Kesalahan paling mungkin: mengunggah berkas konfigurasi aplikasi, bukan akun layanan.
    $hasil = (new PengujiFcm)->Uji([], ['AkunLayanan' => json_encode(['project_info' => ['project_id' => 'payoung-uji']])]);

    expect($hasil->berhasil)->toBeFalse()
        ->and($hasil->pesan)->toContain('client_email');
});

it('kunci privat rusak ditolak sebelum menghubungi Google', function (): void {
    Http::fake();

    $hasil = (new PengujiFcm)->Uji([], ['AkunLayanan' => AkunLayananUji(['private_key' => '-----BEGIN PRIVATE KEY-----terpotong'])]);

    expect($hasil->berhasil)->toBeFalse()
        ->and($hasil->pesan)->toContain('menandatangani');
    Http::assertNothingSent();
});

it('kunci yang dicabut Google dilaporkan apa adanya', function (): void {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error_description' => 'Invalid JWT Signature.'], 400)]);

    $hasil = (new PengujiFcm)->Uji([], ['AkunLayanan' => AkunLayananUji()]);

    expect($hasil->berhasil)->toBeFalse()
        ->and($hasil->pesan)->toContain('Invalid JWT Signature.');
});

it('penyedia Fcm terdaftar di jenis Push dengan satu bidang kredensial', function (): void {
    expect(PenyediaIntegrasi::Fcm->AmbilJenis())->toBe(JenisIntegrasi::Push)
        ->and(array_map(fn ($p) => $p->value, JenisIntegrasi::Push->AmbilDaftarPenyedia()))->toBe(['Fcm'])
        ->and(JenisIntegrasi::Push->AmbilPenyedia())->toBe(PenyediaIntegrasi::Fcm)
        ->and(array_column(PenyediaIntegrasi::Fcm->AmbilBidangKredensial(), 'Kunci'))->toBe(['AkunLayanan'])
        ->and(PenyediaIntegrasi::Fcm->AmbilKelasPenguji())->toBe(PengujiFcm::class)
        // Push diatur di tingkat platform (satu proyek Firebase untuk semua tenant), bukan per tenant.
        ->and(JenisIntegrasi::AmbilJenisPlatform())->toContain(JenisIntegrasi::Push);
});

it('pengirim menukar token OAuth lalu mengirim pesan FCM HTTP v1', function (): void {
    $akun = AkunLayananUji();
    config(['integrasi.Push' => ['Kredensial' => ['AkunLayanan' => $akun]]]);
    Cache::flush();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.kirim']),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/payoung-uji/messages/1']),
    ]);

    $hasil = (new PengirimFcm)->Kirim('token-perangkat-yang-panjang', 'Perlu persetujuan', 'Kas keluar Rp100.000', [
        'Tautan' => 'persetujuan',
    ]);

    expect($hasil->berhasil)->toBeTrue()->and($hasil->aktif)->toBeTrue();
    Http::assertSent(fn ($permintaan): bool => str_contains($permintaan->url(), '/messages:send')
        && $permintaan['message']['token'] === 'token-perangkat-yang-panjang'
        && $permintaan['message']['data']['Tautan'] === 'persetujuan');
});

it('pengirim menandai token perangkat yang sudah dicabut FCM', function (): void {
    config(['integrasi.Push' => ['Kredensial' => ['AkunLayanan' => AkunLayananUji()]]]);
    Cache::flush();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.kirim']),
        'fcm.googleapis.com/*' => Http::response([
            'error' => ['details' => [['errorCode' => 'UNREGISTERED']]],
        ], 404),
    ]);

    $hasil = (new PengirimFcm)->Kirim('token-perangkat-yang-panjang', 'Judul', 'Isi', []);

    expect($hasil->berhasil)->toBeFalse()
        ->and($hasil->tokenTidakBerlaku)->toBeTrue();
});
