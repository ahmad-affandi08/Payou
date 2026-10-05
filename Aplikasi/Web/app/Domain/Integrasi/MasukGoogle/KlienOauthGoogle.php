<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\MasukGoogle;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Alur otorisasi OAuth 2.0 + OpenID Connect Google untuk dashboard web (D-57): membuat URL persetujuan dan menukar
 * `code` menjadi token ID di server (rahasia klien tidak pernah sampai ke peramban).
 */
final class KlienOauthGoogle
{
    public const URL_OTORISASI = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const URL_TOKEN = 'https://oauth2.googleapis.com/token';

    public function __construct(private readonly KonfigurasiGoogle $konfigurasi) {}

    public function BuatUrlOtorisasi(string $uriPengalihan, string $state, string $nonce): string
    {
        return self::URL_OTORISASI.'?'.http_build_query([
            'client_id' => $this->konfigurasi->ClientId(),
            'redirect_uri' => $uriPengalihan,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @throws TokenGoogleTidakSah
     */
    public function TukarKode(string $kode, string $uriPengalihan): string
    {
        try {
            $respons = Http::asForm()->timeout(15)->post(self::URL_TOKEN, [
                'code' => $kode,
                'client_id' => $this->konfigurasi->ClientId(),
                'client_secret' => $this->konfigurasi->ClientSecret(),
                'redirect_uri' => $uriPengalihan,
                'grant_type' => 'authorization_code',
            ]);
        } catch (Throwable) {
            throw new TokenGoogleTidakSah('Google tidak bisa dihubungi.');
        }

        $token = $respons->json('id_token');

        if (! $respons->successful() || ! is_string($token) || $token === '') {
            throw new TokenGoogleTidakSah('Google menolak kode otorisasi.');
        }

        return $token;
    }
}
