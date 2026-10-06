<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Data\DataPengaturanStruk;
use App\Domain\Tenant\Model\Tenant;

/**
 * Pengaturan struk tenant dari `Tenant.Pengaturan.Struk` (PRD v1.79). Nilai rusak kembali ke bawaan: saklar bawaan
 * aktif, teks terlalu panjang/kosong = null, baris kepala dipotong ke 3 baris sah.
 */
final class PengaturanStrukTenant
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    /** Kunci saklar tampil yang boleh berbeda per merek (D-70); teks isian tetap satu untuk tenant. */
    public const SAKLAR_MEREK = [
        'TampilkanLogo', 'TampilkanAlamat', 'TampilkanTelepon', 'TampilkanNpwp',
        'TampilkanKasir', 'TampilkanPelanggan', 'TampilkanHemat', 'TampilkanStrukDigital',
    ];

    /**
     * Pengaturan struk tenant; dengan `$idMerek`, saklar tampil merek itu menimpa saklar tenant (D-70), teks tidak.
     */
    public function Ambil(?int $idTenant = null, ?int $idMerek = null): DataPengaturanStruk
    {
        $pengaturan = Tenant::query()->whereKey($idTenant ?? $this->konteks->Wajib())->firstOrFail()->Pengaturan ?? [];
        $struk = is_array($pengaturan['Struk'] ?? null) ? $pengaturan['Struk'] : [];

        foreach (self::AmbilTimpaanMerek($pengaturan, $idMerek) as $kunci => $nilai) {
            if (in_array($kunci, self::SAKLAR_MEREK, true) && is_bool($nilai)) {
                $struk[$kunci] = $nilai;
            }
        }
        $saklar = static fn (string $kunci): bool => ($struk[$kunci] ?? true) !== false;

        return new DataPengaturanStruk(
            tampilkanLogo: $saklar('TampilkanLogo'),
            namaDicetak: self::AmbilTeks($struk['NamaDicetak'] ?? null, DataPengaturanStruk::PANJANG_BARIS_MAKSIMAL),
            teksKepala: self::AmbilKepala($struk['TeksKepala'] ?? null),
            tampilkanAlamat: $saklar('TampilkanAlamat'),
            tampilkanTelepon: $saklar('TampilkanTelepon'),
            tampilkanNpwp: $saklar('TampilkanNpwp'),
            tampilkanKasir: $saklar('TampilkanKasir'),
            tampilkanPelanggan: $saklar('TampilkanPelanggan'),
            tampilkanHemat: $saklar('TampilkanHemat'),
            catatanKaki: self::AmbilTeks($struk['CatatanKaki'] ?? null, DataPengaturanStruk::PANJANG_CATATAN_KAKI_MAKSIMAL),
            teksPenutup: self::AmbilTeks($struk['TeksPenutup'] ?? null, DataPengaturanStruk::PANJANG_BARIS_MAKSIMAL),
            tampilkanStrukDigital: $saklar('TampilkanStrukDigital'),
        );
    }

    /**
     * Path logo usaha yang dicetak di struk: null bila tenant tanpa logo atau logo dimatikan di pengaturan struk.
     * Dipakai `data-awal` (`AdaLogo`) dan `GET /api/pos/v1/logo-struk` agar keduanya selalu sepakat.
     */
    public function AmbilPathLogo(int $idTenant, ?int $idMerek = null): ?string
    {
        if (! $this->Ambil($idTenant, $idMerek)->tampilkanLogo) {
            return null;
        }

        $pengaturan = Tenant::query()->whereKey($idTenant)->firstOrFail()->Pengaturan ?? [];
        $logoMerek = self::AmbilTimpaanMerek($pengaturan, $idMerek)['PathLogo'] ?? null;

        if (is_string($logoMerek) && $logoMerek !== '') {
            return $logoMerek;
        }

        return is_string($pengaturan['PathLogo'] ?? null) ? $pengaturan['PathLogo'] : null;
    }

    /**
     * Path logo struk khusus merek (null = merek memakai logo usaha). Untuk halaman pengaturan.
     */
    public function AmbilPathLogoMerek(int $idTenant, int $idMerek): ?string
    {
        $pengaturan = Tenant::query()->whereKey($idTenant)->firstOrFail()->Pengaturan ?? [];
        $path = self::AmbilTimpaanMerek($pengaturan, $idMerek)['PathLogo'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * @param  array<string, mixed>  $pengaturan
     * @return array<string, mixed>
     */
    private static function AmbilTimpaanMerek(array $pengaturan, ?int $idMerek): array
    {
        $semua = $pengaturan['StrukMerek'] ?? null;
        $milikMerek = $idMerek === null || ! is_array($semua) ? null : ($semua[(string) $idMerek] ?? null);

        return is_array($milikMerek) ? $milikMerek : [];
    }

    private static function AmbilTeks(mixed $nilai, int $panjangMaksimal): ?string
    {
        if (! is_string($nilai)) {
            return null;
        }

        $teks = trim($nilai);

        return $teks === '' || mb_strlen($teks) > $panjangMaksimal ? null : $teks;
    }

    /**
     * @return list<string>
     */
    private static function AmbilKepala(mixed $nilai): array
    {
        if (! is_array($nilai)) {
            return [];
        }

        $baris = [];

        foreach ($nilai as $teks) {
            $teks = self::AmbilTeks($teks, DataPengaturanStruk::PANJANG_BARIS_MAKSIMAL);

            if ($teks !== null && count($baris) < DataPengaturanStruk::JUMLAH_TEKS_KEPALA_MAKSIMAL) {
                $baris[] = $teks;
            }
        }

        return $baris;
    }
}
