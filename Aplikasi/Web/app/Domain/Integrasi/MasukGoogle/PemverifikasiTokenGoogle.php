<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\MasukGoogle;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Memverifikasi token ID Google (JWT RS256) tanpa SDK: tanda tangan terhadap kunci publik Google (JWKS, di-cache), lalu
 * klaim `iss`, `aud` (harus salah satu Client ID terdaftar), `exp`, `email_verified`, dan `nonce` bila diminta.
 * Dipakai alur pengalihan web (token diterima langsung dari Google, nonce dicocokkan) dan token dari Aplikasi Owner
 * (nonce tidak ada; keamanan bertumpu pada tanda tangan + audiens + masa berlaku pendek).
 */
final class PemverifikasiTokenGoogle
{
    public const URL_KUNCI = 'https://www.googleapis.com/oauth2/v3/certs';

    public const KUNCI_CACHE = 'google.kunci-publik';

    public const DETIK_CACHE = 3600;

    /** Toleransi selisih jam server terhadap `exp`. */
    private const TOLERANSI_DETIK = 60;

    public function __construct(private readonly KonfigurasiGoogle $konfigurasi) {}

    /**
     * @throws TokenGoogleTidakSah
     */
    public function Verifikasi(string $token, ?string $nonce = null): IdentitasGoogle
    {
        $bagian = explode('.', $token);

        if (count($bagian) !== 3) {
            throw new TokenGoogleTidakSah('Format token tidak dikenali.');
        }

        $kepala = $this->DekodeJson($bagian[0]);
        $klaim = $this->DekodeJson($bagian[1]);
        $tandaTangan = $this->DekodeBase64Url($bagian[2]);

        if (($kepala['alg'] ?? null) !== 'RS256' || ! is_string($kepala['kid'] ?? null) || $tandaTangan === null) {
            throw new TokenGoogleTidakSah('Algoritma token tidak didukung.');
        }

        $kunci = $this->CariKunci($kepala['kid']);

        if ($kunci === null || openssl_verify($bagian[0].'.'.$bagian[1], $tandaTangan, $kunci, OPENSSL_ALGO_SHA256) !== 1) {
            throw new TokenGoogleTidakSah('Tanda tangan token tidak cocok.');
        }

        if (! in_array($klaim['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new TokenGoogleTidakSah('Penerbit token bukan Google.');
        }

        $audiens = $klaim['aud'] ?? null;

        if (! is_string($audiens) || ! in_array($audiens, $this->konfigurasi->DaftarAudiens(), true)) {
            throw new TokenGoogleTidakSah('Token bukan untuk aplikasi ini.');
        }

        $kedaluwarsa = $klaim['exp'] ?? null;

        if (! is_numeric($kedaluwarsa) || (int) $kedaluwarsa + self::TOLERANSI_DETIK < time()) {
            throw new TokenGoogleTidakSah('Token sudah kedaluwarsa.');
        }

        if ($nonce !== null && ! hash_equals($nonce, (string) ($klaim['nonce'] ?? ''))) {
            throw new TokenGoogleTidakSah('Nonce token tidak cocok.');
        }

        $sub = $klaim['sub'] ?? null;
        $email = $klaim['email'] ?? null;
        $terverifikasi = $klaim['email_verified'] ?? null;

        if (! is_string($sub) || $sub === '' || strlen($sub) > 64 || ! is_string($email) || $email === '') {
            throw new TokenGoogleTidakSah('Token tidak memuat identitas yang lengkap.');
        }

        // Google mengirim boolean atau string "true" tergantung jalurnya.
        if ($terverifikasi !== true && $terverifikasi !== 'true') {
            throw new TokenGoogleTidakSah('Email akun Google belum terverifikasi.');
        }

        $nama = is_string($klaim['name'] ?? null) && trim($klaim['name']) !== '' ? trim($klaim['name']) : strstr($email, '@', true);

        return new IdentitasGoogle($sub, mb_strtolower(trim($email)), mb_substr((string) $nama, 0, 150));
    }

    /** @return array<string, mixed> */
    private function DekodeJson(string $bagian): array
    {
        $mentah = $this->DekodeBase64Url($bagian);
        $data = $mentah === null ? null : json_decode($mentah, true);

        return is_array($data) ? $data : [];
    }

    private function DekodeBase64Url(string $nilai): ?string
    {
        $hasil = base64_decode(strtr($nilai, '-_', '+/'), true);

        return $hasil === false ? null : $hasil;
    }

    /** Kunci publik (PEM) untuk `kid`; kunci baru dimuat ulang sekali bila `kid` belum dikenal (rotasi kunci Google). */
    private function CariKunci(string $kid): ?string
    {
        foreach ([false, true] as $paksaMuat) {
            $daftar = $this->AmbilDaftarKunci($paksaMuat);

            if (isset($daftar[$kid])) {
                return $daftar[$kid];
            }
        }

        return null;
    }

    /** @return array<string, string> kid → PEM */
    private function AmbilDaftarKunci(bool $paksaMuat): array
    {
        if ($paksaMuat) {
            Cache::forget(self::KUNCI_CACHE);
        }

        try {
            /** @var array<string, string> $daftar */
            $daftar = Cache::remember(self::KUNCI_CACHE, self::DETIK_CACHE, function (): array {
                $respons = Http::timeout(10)->get(self::URL_KUNCI);
                $hasil = [];

                foreach ((array) $respons->json('keys') as $kunci) {
                    if (is_array($kunci) && ($kunci['kty'] ?? null) === 'RSA' && is_string($kunci['kid'] ?? null) && is_string($kunci['n'] ?? null) && is_string($kunci['e'] ?? null)) {
                        $pem = self::UbahJwkKePem($kunci['n'], $kunci['e']);

                        if ($pem !== null) {
                            $hasil[$kunci['kid']] = $pem;
                        }
                    }
                }

                // Jangan menyimpan daftar kosong: kegagalan jaringan sesaat tidak boleh mengunci login selama sejam.
                if ($hasil === []) {
                    throw new TokenGoogleTidakSah('Kunci publik Google tidak tersedia.');
                }

                return $hasil;
            });

            return $daftar;
        } catch (Throwable) {
            return [];
        }
    }

    /** Mengubah kunci publik RSA berformat JWK (modulus `n`, eksponen `e`) menjadi PEM SubjectPublicKeyInfo. */
    public static function UbahJwkKePem(string $n, string $e): ?string
    {
        $modulus = base64_decode(strtr($n, '-_', '+/'), true);
        $eksponen = base64_decode(strtr($e, '-_', '+/'), true);

        if ($modulus === false || $eksponen === false || $modulus === '' || $eksponen === '') {
            return null;
        }

        $rsa = self::Der(0x30, self::DerInteger($modulus).self::DerInteger($eksponen));
        // OID rsaEncryption 1.2.840.113549.1.1.1 + NULL
        $algoritma = self::Der(0x30, hex2bin('06092a864886f70d0101010500') ?: '');
        $info = self::Der(0x30, $algoritma.self::Der(0x03, "\x00".$rsa));

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($info), 64, "\n").'-----END PUBLIC KEY-----';
    }

    private static function DerInteger(string $biner): string
    {
        $biner = ltrim($biner, "\x00");

        // Bit tertinggi menyala = negatif dalam DER; sisipkan nol di depan.
        return self::Der(0x02, (ord($biner[0] ?? "\x00") & 0x80) !== 0 ? "\x00".$biner : $biner);
    }

    private static function Der(int $tag, string $isi): string
    {
        $panjang = strlen($isi);

        if ($panjang < 0x80) {
            $penanda = chr($panjang);
        } else {
            $byte = ltrim(pack('N', $panjang), "\x00");
            $penanda = chr(0x80 | strlen($byte)).$byte;
        }

        return chr($tag).$penanda.$isi;
    }
}
