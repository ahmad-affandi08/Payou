<?php

declare(strict_types=1);

namespace Tests\Pendukung\Tenant;

use App\Domain\Integrasi\MasukGoogle\KlienOauthGoogle;
use App\Domain\Integrasi\MasukGoogle\PemverifikasiTokenGoogle;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;

/**
 * Bantuan test Masuk dengan Google (D-57): pasangan kunci RSA uji yang mengambil peran kunci tanda tangan Google,
 * penerbit token ID, dan konfigurasi integrasi aktif. Tidak ada panggilan jaringan sungguhan.
 */
final class BantuanGoogle
{
    public const CLIENT_ID = '1234567890-abc.apps.googleusercontent.com';

    public const CLIENT_ID_ANDROID = '1234567890-android.apps.googleusercontent.com';

    public const KID = 'kunci-uji-1';

    private static ?OpenSSLAsymmetricKey $kunci = null;

    /** Menyalakan integrasi (config seperti hasil `PenerapKonfigurasiIntegrasi`) dan menyiapkan JWKS palsu. */
    public static function Aktifkan(string $clientIdTambahan = self::CLIENT_ID_ANDROID): void
    {
        config(['integrasi.LoginSosial' => [
            'Penyedia' => 'Google',
            'Pengaturan' => ['ClientId' => self::CLIENT_ID, 'ClientIdTambahan' => $clientIdTambahan],
            'Kredensial' => ['ClientSecret' => 'rahasia-uji'],
        ]]);
        Cache::forget(PemverifikasiTokenGoogle::KUNCI_CACHE);
        self::PalsukanJwks();
    }

    public static function PalsukanJwks(): void
    {
        $detail = openssl_pkey_get_details(self::Kunci());
        $rsa = is_array($detail) && is_array($detail['rsa'] ?? null) ? $detail['rsa'] : [];

        Http::fake([
            PemverifikasiTokenGoogle::URL_KUNCI => Http::response(['keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => self::KID,
                'n' => self::Base64Url((string) ($rsa['n'] ?? '')),
                'e' => self::Base64Url((string) ($rsa['e'] ?? '')),
            ]]]),
        ]);
    }

    /**
     * Token ID Google bertanda tangan kunci uji.
     *
     * @param  array<string, mixed>  $klaim  menimpa klaim bawaan (null menghapus klaimnya)
     */
    public static function BuatToken(array $klaim = [], string $kid = self::KID, ?OpenSSLAsymmetricKey $kunci = null): string
    {
        $isi = array_filter([
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => '110248495921238986420',
            'email' => 'rina@kopinusantara.id',
            'email_verified' => true,
            'name' => 'Rina Wulandari',
            'iat' => time(),
            'exp' => time() + 3600,
            ...$klaim,
        ], fn (mixed $nilai): bool => $nilai !== null);
        $kepala = self::Base64Url((string) json_encode(['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT']));
        $badan = self::Base64Url((string) json_encode($isi));
        openssl_sign($kepala.'.'.$badan, $tandaTangan, $kunci ?? self::Kunci(), OPENSSL_ALGO_SHA256);

        return $kepala.'.'.$badan.'.'.self::Base64Url($tandaTangan);
    }

    /** Titik token Google menjawab dengan token ID tertentu (alur pengalihan web). */
    public static function PalsukanTukarKode(string $tokenId): void
    {
        // Stub lama harus dibuang: stub pertama yang cocok selalu menang, sehingga alur kedua akan menerima token alur pertama.
        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstances();
        Http::fake([
            KlienOauthGoogle::URL_TOKEN => Http::response(['id_token' => $tokenId, 'access_token' => 'x', 'token_type' => 'Bearer']),
            PemverifikasiTokenGoogle::URL_KUNCI => self::Respons(),
        ]);
    }

    /** Kunci RSA lain yang bukan milik "Google": tanda tangan yang dihasilkannya harus ditolak. */
    public static function BuatKunciAsing(): OpenSSLAsymmetricKey
    {
        $kunci = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($kunci instanceof OpenSSLAsymmetricKey);

        return $kunci;
    }

    private static function Respons(): PromiseInterface
    {
        $detail = openssl_pkey_get_details(self::Kunci());
        $rsa = is_array($detail) && is_array($detail['rsa'] ?? null) ? $detail['rsa'] : [];

        return Http::response(['keys' => [[
            'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => self::KID,
            'n' => self::Base64Url((string) ($rsa['n'] ?? '')), 'e' => self::Base64Url((string) ($rsa['e'] ?? '')),
        ]]]);
    }

    private static function Kunci(): OpenSSLAsymmetricKey
    {
        return self::$kunci ??= self::BuatKunciAsing();
    }

    private static function Base64Url(string $biner): string
    {
        return rtrim(strtr(base64_encode($biner), '+/', '-_'), '=');
    }
}
