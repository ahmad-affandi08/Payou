<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Pilihan\Kueri;

use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use App\Domain\Katalog\Pilihan\Model\Pilihan;
use App\Domain\Katalog\Pilihan\Model\ProdukKelompokPilihan;

/**
 * Daftar kelompok pilihan tenant untuk halaman `Kelola/KelompokPilihan/Daftar` (F-03 E.9) dan ringkasan kelompok
 * untuk halaman pilihan produk. Angka batas dikirim sebagai string (tipe FE `FormKelompokPilihan`).
 */
final class DaftarKelompokPilihan
{
    /**
     * @return list<array{Uuid: string, Nama: string, MinimalPilih: string, MaksimalPilih: string, Urutan: string, Wajib: bool, JumlahProduk: int, Pilihan: list<array{Uuid: string, Nama: string, Harga: string, Aktif: bool, UuidProdukBahan: string|null, Jumlah: string, NamaProdukBahan: string|null, SimbolSatuanBahan: string|null}>}>
     */
    public function Ambil(): array
    {
        $kelompok = KelompokPilihan::query()->orderBy('Urutan')->orderBy('Nama')->get();
        $pilihan = Pilihan::query()->orderBy('Urutan')->orderBy('Id')->get()->groupBy('IdKelompokPilihan');
        $jumlahProduk = ProdukKelompokPilihan::query()
            ->whereIn('IdProduk', Produk::query()->select('Id'))
            ->selectRaw('IdKelompokPilihan, COUNT(*) AS Jumlah')
            ->groupBy('IdKelompokPilihan')
            ->pluck('Jumlah', 'IdKelompokPilihan');
        $idBahan = $pilihan->flatten()->pluck('IdProduk')->filter()->unique()->values()->all();
        $bahan = Produk::query()->withTrashed()->whereKey($idBahan)->get(['Id', 'Uuid', 'Nama', 'IdSatuanDasar'])->keyBy('Id');
        $simbol = Satuan::query()->whereKey($bahan->pluck('IdSatuanDasar')->unique()->values()->all())->pluck('Simbol', 'Id');

        return array_values($kelompok->map(fn (KelompokPilihan $baris): array => [
            'Uuid' => $baris->Uuid,
            'Nama' => $baris->Nama,
            'MinimalPilih' => (string) $baris->MinimalPilih,
            'MaksimalPilih' => (string) $baris->MaksimalPilih,
            'Urutan' => (string) $baris->Urutan,
            'Wajib' => $baris->MinimalPilih >= 1,
            'JumlahProduk' => (int) $jumlahProduk->get($baris->Id, 0),
            'Pilihan' => array_values(collect($pilihan->get($baris->Id, []))->map(function (Pilihan $isi) use ($bahan, $simbol): array {
                $produkBahan = $isi->IdProduk === null ? null : $bahan->get($isi->IdProduk);

                return [
                    'Uuid' => $isi->Uuid,
                    'Nama' => $isi->Nama,
                    'Harga' => $isi->Harga,
                    'Aktif' => $isi->Aktif,
                    'UuidProdukBahan' => $produkBahan?->Uuid,
                    'Jumlah' => $isi->Jumlah ?? '',
                    'NamaProdukBahan' => $produkBahan?->Nama,
                    'SimbolSatuanBahan' => $produkBahan === null ? null : $simbol->get($produkBahan->IdSatuanDasar),
                ];
            })->all()),
        ])->all());
    }

    /**
     * Ringkasan singkat kelompok, misal "Wajib pilih 1 | 3 pilihan" atau "Opsional, maks. 2 | 4 pilihan".
     */
    public static function BuatRingkasan(KelompokPilihan $kelompok, int $jumlahPilihan): string
    {
        $batas = match (true) {
            $kelompok->MinimalPilih >= 1 && $kelompok->MinimalPilih === $kelompok->MaksimalPilih => "Wajib pilih {$kelompok->MinimalPilih}",
            $kelompok->MinimalPilih >= 1 => "Wajib pilih {$kelompok->MinimalPilih}–{$kelompok->MaksimalPilih}",
            default => "Opsional, maks. {$kelompok->MaksimalPilih}",
        };

        return "{$batas} | {$jumlahPilihan} pilihan";
    }
}
