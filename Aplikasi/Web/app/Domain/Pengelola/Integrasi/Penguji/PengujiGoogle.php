<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Integrasi\MasukGoogle\KlienOauthGoogle;
use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Menguji Client ID + secret Masuk dengan Google dengan menukar kode palsu di titik token Google: `invalid_client`
 * berarti ID/secret salah, sedangkan `invalid_grant` berarti pasangan itu dikenal dan hanya kodenya yang palsu.
 * Tidak ada akun atau token yang tercipta (P-05).
 */
final class PengujiGoogle implements PengujiKoneksi
{
    public function Uji(array $pengaturan, array $kredensial): HasilUjiKoneksi
    {
        $clientId = trim((string) ($pengaturan['ClientId'] ?? ''));
        $rahasia = trim((string) ($kredensial['ClientSecret'] ?? ''));

        if ($clientId === '' || $rahasia === '') {
            return HasilUjiKoneksi::Gagal('Client ID dan client secret wajib diisi.');
        }

        if (! str_ends_with($clientId, '.apps.googleusercontent.com')) {
            return HasilUjiKoneksi::Gagal('Client ID harus berakhiran .apps.googleusercontent.com.');
        }

        try {
            $respons = Http::asForm()->timeout(10)->post(KlienOauthGoogle::URL_TOKEN, [
                'code' => 'uji-koneksi-pengelola',
                'client_id' => $clientId,
                'client_secret' => $rahasia,
                'redirect_uri' => 'https://localhost/uji',
                'grant_type' => 'authorization_code',
            ]);
        } catch (Throwable $galat) {
            return HasilUjiKoneksi::Gagal('Tidak bisa menghubungi Google: '.PenyaringPesan::Saring($galat->getMessage(), $kredensial));
        }

        $galat = (string) $respons->json('error');

        if ($galat === 'invalid_client') {
            return HasilUjiKoneksi::Gagal('Client ID atau client secret ditolak Google.');
        }

        if ($galat === 'invalid_grant' || $galat === 'redirect_uri_mismatch') {
            return HasilUjiKoneksi::Berhasil('Client ID dan secret diterima Google. Pastikan Authorized redirect URI sudah didaftarkan.');
        }

        return HasilUjiKoneksi::Gagal("Jawaban Google tidak terduga (HTTP {$respons->status()}).");
    }
}
