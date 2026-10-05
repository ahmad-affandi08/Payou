<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\MasukGoogle;

/**
 * Konfigurasi Masuk dengan Google dari integrasi aktif P-05 (`config('integrasi.LoginSosial')`, jenis `LoginSosial`).
 * Client ID web dipakai alur pengalihan di dashboard; Client ID tambahan (Android/iOS) hanya menambah audiens token
 * yang diterima dari Aplikasi Owner. Tanpa konfigurasi aktif, semua tombol Google disembunyikan.
 */
final class KonfigurasiGoogle
{
    public function CekAktif(): bool
    {
        return $this->ClientId() !== '' && $this->ClientSecret() !== '';
    }

    public function ClientId(): string
    {
        return trim((string) $this->Ambil('Pengaturan', 'ClientId'));
    }

    public function ClientSecret(): string
    {
        return trim((string) $this->Ambil('Kredensial', 'ClientSecret'));
    }

    /**
     * Audiens (`aud`) yang diterima: Client ID web ditambah Client ID Android/iOS.
     *
     * @return list<string>
     */
    public function DaftarAudiens(): array
    {
        $tambahan = preg_split('/[\s,;]+/', (string) $this->Ambil('Pengaturan', 'ClientIdTambahan')) ?: [];
        $semua = array_filter([$this->ClientId(), ...$tambahan], fn (string $id): bool => $id !== '');

        return array_values(array_unique($semua));
    }

    private function Ambil(string $bagian, string $kunci): mixed
    {
        $konfigurasi = config('integrasi.LoginSosial');

        return is_array($konfigurasi) && is_array($konfigurasi[$bagian] ?? null) ? ($konfigurasi[$bagian][$kunci] ?? null) : null;
    }
}
