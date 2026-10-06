<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Web;

/**
 * Alamat lintas domain produksi (D-20): pemasaran (`domain.Pemasaran`, misal `payoung.id`) dan tenant (`domain.Tenant`,
 * misal `dashboard.payoung.id`). Bila salah satu belum diatur (pengembangan & test), jalur dikembalikan relatif.
 */
final class AlamatDomain
{
    /** Alamat [jalur] di domain tenant; jalur relatif bila domain belum dipisah. */
    public static function BuatUrlTenant(string $jalur): string
    {
        $tenant = self::AmbilDomain('domain.Tenant');

        return $tenant === null || self::AmbilDomain('domain.Pemasaran') === null ? $jalur : self::BuatUrl($tenant, $jalur);
    }

    /** Alamat [jalur] di domain pemasaran; jalur relatif bila domain belum dipisah. */
    public static function BuatUrlPemasaran(string $jalur): string
    {
        $pemasaran = self::AmbilDomain('domain.Pemasaran');

        return $pemasaran === null || self::AmbilDomain('domain.Tenant') === null ? $jalur : self::BuatUrl($pemasaran, $jalur);
    }

    /** Alamat absolut [jalur] di domain tenant (atau `APP_URL` bila satu host), misal tautan di email. */
    public static function BuatUrlAbsolutTenant(string $jalur): string
    {
        $url = self::BuatUrlTenant($jalur);

        return str_starts_with($url, 'http') ? $url : rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    /** Alamat absolut [jalur] di domain pemasaran (atau `APP_URL` bila satu host). */
    public static function BuatUrlAbsolutPemasaran(string $jalur): string
    {
        $url = self::BuatUrlPemasaran($jalur);

        return str_starts_with($url, 'http') ? $url : rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    public static function BuatUrl(string $host, string $jalur): string
    {
        $skema = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        return (is_string($skema) && $skema !== '' ? $skema : 'https').'://'.$host.'/'.ltrim($jalur, '/');
    }

    /** Domain terkonfigurasi (huruf kecil) atau null bila kosong. */
    public static function AmbilDomain(string $kunci): ?string
    {
        $nilai = config($kunci);

        return is_string($nilai) && trim($nilai) !== '' ? strtolower(trim($nilai)) : null;
    }
}
