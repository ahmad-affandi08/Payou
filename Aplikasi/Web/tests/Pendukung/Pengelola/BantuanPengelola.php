<?php

declare(strict_types=1);

namespace Tests\Pendukung\Pengelola;

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Http\Perantara\Pengelola\SesiPengelola;

/**
 * Bantuan test Platform Pengelola (P-01).
 */
final class BantuanPengelola
{
    public static function Url(string $path = '/'): string
    {
        return 'http://'.config('pengelola.Domain').$path;
    }

    /** Anggota aktif dengan 2FA sudah aktif. */
    public static function BuatAnggota(PeranPengelolaBawaan ...$peran): PenggunaPengelola
    {
        return PenggunaPengelola::factory()->DenganDuaFaktor()->DenganPeran(...$peran)->createOne();
    }

    /**
     * Isi sesi setelah masuk dan baru saja memasukkan kode 2FA (aksi berbahaya tidak meminta konfirmasi, D-42).
     *
     * @return array<string, mixed>
     */
    public static function SesiTerverifikasi(): array
    {
        return [
            SesiPengelola::DUA_FAKTOR_TERVERIFIKASI => true,
            SesiPengelola::KODE_DUA_FAKTOR_PADA => now()->getTimestamp(),
            SesiPengelola::TERAKHIR_AKTIF => now()->getTimestamp(),
        ];
    }

    /**
     * Isi sesi setelah masuk lewat perangkat tepercaya (D-42): 2FA terlewati, tetapi belum ada kode di sesi ini.
     *
     * @return array<string, mixed>
     */
    public static function SesiPerangkatTepercaya(): array
    {
        return [
            SesiPengelola::DUA_FAKTOR_TERVERIFIKASI => true,
            SesiPengelola::TERAKHIR_AKTIF => now()->getTimestamp(),
        ];
    }
}
