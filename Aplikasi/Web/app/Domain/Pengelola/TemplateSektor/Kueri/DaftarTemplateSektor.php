<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Kueri;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Laporan\Enum\LaporanUnggulan;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;
use App\Domain\Pengelola\TemplateSektor\Enum\JenisProdukContoh;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Penjualan\Enum\ModeKasir;
use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Referensi\Model\SatuanStandar;
use App\Domain\Tenant\Model\Fitur;

/**
 * Kueri halaman template sektor Platform Pengelola (P-03).
 */
final class DaftarTemplateSektor
{
    /**
     * @return list<array<string, mixed>>
     */
    public function AmbilDaftar(): array
    {
        return array_values(TemplateSektor::query()
            ->with(['Versi' => fn ($kueri) => $kueri->orderByDesc('Versi')])
            ->orderBy('Kode')
            ->get()
            ->map(function (TemplateSektor $template): array {
                $terbit = $template->Versi->first(fn (TemplateSektorVersi $versi) => $versi->Status === StatusTemplateSektor::Terbit);
                $draf = $template->Versi->first(fn (TemplateSektorVersi $versi) => $versi->Status === StatusTemplateSektor::Draf);

                return [
                    'Kode' => $template->Kode,
                    'Nama' => $template->Nama,
                    'Keterangan' => $template->Keterangan,
                    'DinonaktifkanPada' => $template->DinonaktifkanPada?->toIso8601String(),
                    'VersiTerbit' => $terbit === null ? null : ['Versi' => $terbit->Versi, 'DiterbitkanPada' => $terbit->DiterbitkanPada?->toIso8601String()],
                    'VersiDraf' => $draf === null ? null : ['Versi' => $draf->Versi, 'Lolos' => $draf->CekLolosValidasi(), 'SudahDivalidasi' => $draf->HasilValidasi !== null],
                    'VersiTerbaru' => $template->Versi->first()?->Versi,
                ];
            })
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function AmbilVersi(TemplateSektor $template, TemplateSektorVersi $versi): array
    {
        return [
            'Template' => ['Kode' => $template->Kode, 'Nama' => $template->Nama, 'Keterangan' => $template->Keterangan, 'DinonaktifkanPada' => $template->DinonaktifkanPada?->toIso8601String()],
            'Versi' => [
                'Versi' => $versi->Versi,
                'Status' => $versi->Status->value,
                'Isi' => self::AmbilIsiTampil($versi->Isi),
                'HasilValidasi' => $versi->HasilValidasi,
                'DivalidasiPada' => $versi->DivalidasiPada?->toIso8601String(),
                'DiterbitkanPada' => $versi->DiterbitkanPada?->toIso8601String(),
                'VersiAsal' => $versi->IdVersiAsal === null ? null : TemplateSektorVersi::query()->whereKey($versi->IdVersiAsal)->value('Versi'),
            ],
            'DaftarVersi' => array_values($template->Versi()->orderByDesc('Versi')->get(['Versi', 'Status', 'DiterbitkanPada'])->map(
                fn (TemplateSektorVersi $baris): array => [
                    'Versi' => $baris->Versi,
                    'Status' => $baris->Status->value,
                    'DiterbitkanPada' => $baris->DiterbitkanPada?->toIso8601String(),
                ],
            )->all()),
            'AdaDraf' => $template->Versi()->where('Status', StatusTemplateSektor::Draf->value)->exists(),
        ];
    }

    /**
     * Pilihan untuk editor terstruktur (bukan JSON mentah, P-03 langkah 2).
     *
     * @return array<string, mixed>
     */
    public function AmbilPilihanEditor(): array
    {
        return [
            'ModeKasir' => array_map(fn (ModeKasir $mode) => ['Nilai' => $mode->value, 'Label' => $mode->AmbilLabel()], ModeKasir::cases()),
            'TipeAkun' => array_map(fn (TipeAkun $tipe) => [
                'Nilai' => $tipe->value,
                'Label' => $tipe->AmbilLabel(),
                'DigitAwal' => $tipe->AmbilDigitAwalKode(),
                'SaldoNormal' => $tipe->AmbilSaldoNormal()->value,
            ], TipeAkun::cases()),
            'SaldoNormal' => array_map(fn (SaldoNormal $saldo) => ['Nilai' => $saldo->value, 'Label' => $saldo->value], SaldoNormal::cases()),
            'PeranAkun' => array_map(fn (PeranAkun $peran) => [
                'Nilai' => $peran->value,
                'Label' => $peran->AmbilLabel(),
                'Tipe' => $peran->AmbilTipeAkun()->value,
                'WajibKontra' => $peran->CekWajibKontra(),
            ], PeranAkun::cases()),
            'ArahPembulatan' => array_map(fn (ArahPembulatan $arah) => ['Nilai' => $arah->value, 'Label' => $arah->AmbilLabel()], ArahPembulatan::cases()),
            'MetodeHpp' => array_map(fn (MetodeHpp $metode) => ['Nilai' => $metode->value, 'Label' => $metode->AmbilLabel()], MetodeHpp::cases()),
            'DasarPengenaan' => array_map(fn (DasarPengenaanPajak $dasar) => ['Nilai' => $dasar->value, 'Label' => $dasar->AmbilLabel()], DasarPengenaanPajak::cases()),
            'LaporanUnggulan' => array_map(fn (LaporanUnggulan $laporan) => ['Nilai' => $laporan->value, 'Label' => $laporan->AmbilLabel()], LaporanUnggulan::cases()),
            'Fitur' => array_values(Fitur::query()->orderBy('Modul')->orderBy('Kunci')->get()->map(
                fn (Fitur $fitur) => ['Nilai' => $fitur->Kunci, 'Label' => $fitur->Nama, 'Kelompok' => $fitur->Modul],
            )->all()),
            'Satuan' => array_values(SatuanStandar::query()->where('Aktif', true)->orderBy('Kode')->get()->map(
                fn (SatuanStandar $satuan) => ['Nilai' => $satuan->Kode, 'Label' => "{$satuan->Nama} ({$satuan->Simbol})"],
            )->all()),
            'JenisPajak' => array_values(JenisPajak::query()->orderBy('Kode')->get()->map(
                fn (JenisPajak $jenis) => ['Nilai' => $jenis->Kode, 'Label' => $jenis->Nama],
            )->all()),
            'JenisProdukContoh' => array_map(fn (JenisProdukContoh $jenis) => ['Nilai' => $jenis->value, 'Label' => $jenis->AmbilLabel()], JenisProdukContoh::cases()),
        ];
    }

    /**
     * Isi untuk editor. Versi lama bisa belum punya `ProdukContoh` atau masih memakai kunci peran lama; keduanya
     * dirapikan hanya untuk tampilan, isi tersimpan tidak diubah (BR-P03.4).
     *
     * @param  array<string, mixed>  $isi
     * @return array<string, mixed>
     */
    private static function AmbilIsiTampil(array $isi): array
    {
        $isi['ProdukContoh'] = is_array($isi['ProdukContoh'] ?? null) ? $isi['ProdukContoh'] : [];

        if (is_array($isi['PemetaanAkun'] ?? null)) {
            $isi['PemetaanAkun'] = PeranAkun::NormalisasiPemetaan($isi['PemetaanAkun']);
        }

        return $isi;
    }
}
