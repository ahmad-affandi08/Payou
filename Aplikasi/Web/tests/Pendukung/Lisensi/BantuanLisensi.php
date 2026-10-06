<?php

declare(strict_types=1);

namespace Tests\Pendukung\Lisensi;

use App\Domain\Lisensi\Data\DataLisensi;
use App\Domain\Lisensi\Layanan\PenandaLisensi;

/**
 * D-35: pasangan kunci uji sekali pakai per proses test (bukan kunci penerbit Payoung) dan pembuat berkas lisensi.
 */
final class BantuanLisensi
{
    /** @var array{KunciPublik: string, KunciPrivat: string}|null */
    private static ?array $kunci = null;

    /**
     * @return array{KunciPublik: string, KunciPrivat: string}
     */
    public static function Kunci(): array
    {
        return self::$kunci ??= app(PenandaLisensi::class)->BuatPasanganKunci();
    }

    /** Pasang kunci publik uji ke konfigurasi aplikasi yang sedang berjalan. */
    public static function PasangKunciPublik(): void
    {
        config(['lisensi.KunciPublik' => self::Kunci()['KunciPublik']]);
    }

    public static function Data(string $domain = 'localhost', ?int $batasOutlet = 2, ?int $batasPerangkat = 3, ?int $batasPengguna = null, string $nomor = 'PAYOUNG-L-2026-0001', ?string $pembaruanSampai = null): DataLisensi
    {
        return new DataLisensi(
            nomor: $nomor,
            namaPemegang: 'PT Kopi Nusantara Sejahtera',
            domain: $domain,
            batasOutlet: $batasOutlet,
            batasPerangkatPerOutlet: $batasPerangkat,
            batasPengguna: $batasPengguna,
            diterbitkanPada: '2026-10-03',
            pembaruanSampai: $pembaruanSampai,
        );
    }

    public static function Berkas(?DataLisensi $data = null): string
    {
        return app(PenandaLisensi::class)->Tandatangani($data ?? self::Data(), self::Kunci()['KunciPrivat']);
    }
}
