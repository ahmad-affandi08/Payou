<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Layanan;

use App\Domain\Lisensi\Data\DataLisensi;
use App\Domain\Lisensi\Galat\LisensiTidakSah;
use JsonException;
use SensitiveParameter;
use SodiumException;

/**
 * Tanda tangan & verifikasi berkas lisensi (D-35) dengan Ed25519 (libsodium). Verifikasi sepenuhnya offline: server
 * pembeli hanya butuh kunci publik di `config/lisensi.php`, tidak pernah menghubungi server Payoung.
 *
 * Bentuk berkas: JSON `{"Format": 1|2, "Data": {...}, "TandaTangan": "<base64>"}`. Yang ditandatangani adalah JSON
 * kanonik `DataLisensi::KeArray()` (urutan kunci tetap, tanpa escape garis miring/unicode), bukan teks berkas apa
 * adanya, sehingga spasi/indentasi berkas boleh berubah tanpa merusak tanda tangan.
 */
final class PenandaLisensi
{
    /**
     * Buat pasangan kunci baru (base64). Hanya dipakai `lisensi:buat-kunci` di mesin Payoung.
     *
     * @return array{KunciPublik: string, KunciPrivat: string}
     */
    public function BuatPasanganKunci(): array
    {
        $pasangan = sodium_crypto_sign_keypair();

        return [
            'KunciPublik' => base64_encode(sodium_crypto_sign_publickey($pasangan)),
            'KunciPrivat' => base64_encode(sodium_crypto_sign_secretkey($pasangan)),
        ];
    }

    public function Tandatangani(DataLisensi $data, #[SensitiveParameter] string $kunciPrivatBase64): string
    {
        $kunciPrivat = base64_decode(trim($kunciPrivatBase64), true);

        if ($kunciPrivat === false || strlen($kunciPrivat) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new LisensiTidakSah('Kunci privat penerbit tidak sah.');
        }

        $tandaTangan = sodium_crypto_sign_detached(self::KodekanKanonik($data), $kunciPrivat);
        sodium_memzero($kunciPrivat);

        return json_encode([
            'Format' => $data->AmbilFormat(),
            'Data' => $data->KeArray(),
            'TandaTangan' => base64_encode($tandaTangan),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Baca & verifikasi isi berkas lisensi. Melempar `LisensiTidakSah` bila rusak atau tanda tangan tidak cocok.
     */
    public function Baca(string $isiBerkas, ?string $kunciPublikBase64 = null): DataLisensi
    {
        $kunciPublik = base64_decode(trim($kunciPublikBase64 ?? self::AmbilKunciPublikTerpasang()), true);

        if ($kunciPublik === false || strlen($kunciPublik) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new LisensiTidakSah('Kunci publik penerbit lisensi belum diisi di config/lisensi.php.');
        }

        try {
            $berkas = json_decode($isiBerkas, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new LisensiTidakSah('Berkas lisensi rusak: bukan JSON yang sah.');
        }

        if (! is_array($berkas) || ! in_array($berkas['Format'] ?? null, DataLisensi::FORMAT_DIKENAL, true)
            || ! is_array($berkas['Data'] ?? null) || ! is_string($berkas['TandaTangan'] ?? null)) {
            throw new LisensiTidakSah('Berkas lisensi rusak atau formatnya tidak dikenal.');
        }

        $data = DataLisensi::DariArray($berkas['Data'], (int) $berkas['Format']);
        $tandaTangan = base64_decode($berkas['TandaTangan'], true);

        try {
            $sah = $tandaTangan !== false && strlen($tandaTangan) === SODIUM_CRYPTO_SIGN_BYTES
                && sodium_crypto_sign_verify_detached($tandaTangan, self::KodekanKanonik($data), $kunciPublik);
        } catch (SodiumException) {
            $sah = false;
        }

        if (! $sah) {
            throw new LisensiTidakSah('Tanda tangan berkas lisensi tidak cocok. Berkas diubah atau bukan dari Payoung.');
        }

        return $data;
    }

    private static function KodekanKanonik(DataLisensi $data): string
    {
        return json_encode($data->KeArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function AmbilKunciPublikTerpasang(): string
    {
        $kunci = config('lisensi.KunciPublik');

        return is_string($kunci) ? $kunci : '';
    }
}
