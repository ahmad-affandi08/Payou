<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\Addon;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\HargaPaket;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Katalog fitur, paket, dan add-on awal dari file data (`database/Data/KatalogFitur.json`, `KatalogPaket.json`,
 * `KatalogAddon.json`), P-04, §21, D-11. Add-on dimuat Diarsipkan (belum dijual) sampai Keuangan mengaktifkannya.
 * Idempoten: fitur/paket yang sudah ada tidak diubah. Paket dibuat sebagai DRAF dengan draf harga; harga wajib
 * disetujui (BR-P04.5) dan paket diaktifkan Super Admin (BR-P04.6). Tidak ada harga di kode.
 */
final class SiapkanKatalogBawaan
{
    public function Jalankan(?string $pathFitur = null, ?string $pathPaket = null, ?string $pathAddon = null): void
    {
        $dataFitur = self::BacaJson($pathFitur ?? database_path('Data/KatalogFitur.json'), 'Fitur');
        $dataPaket = self::BacaJson($pathPaket ?? database_path('Data/KatalogPaket.json'), 'Paket');
        $dataAddon = self::BacaJson($pathAddon ?? database_path('Data/KatalogAddon.json'), 'Addon');

        DB::transaction(function () use ($dataFitur, $dataPaket, $dataAddon): void {
            foreach ($dataFitur as $fitur) {
                Fitur::query()->firstOrCreate(
                    ['Kunci' => self::AmbilTeks($fitur, 'Kunci')],
                    ['Nama' => self::AmbilTeks($fitur, 'Nama'), 'Modul' => self::AmbilTeks($fitur, 'Modul')],
                );
            }

            foreach ($dataPaket as $data) {
                $kode = self::AmbilTeks($data, 'Kode');

                if (Paket::query()->where('Kode', $kode)->exists()) {
                    continue;
                }

                $batas = is_array($data['Batas'] ?? null) ? $data['Batas'] : [];
                $paket = Paket::query()->create([
                    'Kode' => $kode,
                    'Nama' => self::AmbilTeks($data, 'Nama'),
                    'Status' => StatusPaket::Draf,
                    'HargaNegosiasi' => ($data['HargaNegosiasi'] ?? false) === true,
                    'MasaTrialHari' => is_int($data['MasaTrialHari'] ?? null) ? $data['MasaTrialHari'] : 0,
                    'Urutan' => is_int($data['Urutan'] ?? null) ? $data['Urutan'] : 0,
                    ...array_map(fn ($nilai) => is_int($nilai) ? $nilai : null, array_intersect_key($batas, array_flip(Paket::KOLOM_BATAS))),
                ]);

                foreach (is_array($data['Fitur'] ?? null) ? $data['Fitur'] : [] as $kunci) {
                    $paket->Fitur()->create(['KunciFitur' => (string) $kunci]);
                }

                $harga = $data['Harga'] ?? null;

                if (is_array($harga)) {
                    HargaPaket::query()->create([
                        'IdPaket' => $paket->Id,
                        'HargaBulanan' => self::AmbilTeks($harga, 'HargaBulanan'),
                        'HargaTahunan' => self::AmbilTeks($harga, 'HargaTahunan'),
                        // Draf: tanggal berlaku disesuaikan penyusun sebelum diajukan (BR-P04.5).
                        'BerlakuMulai' => now('Asia/Jakarta')->toDateString(),
                        'Status' => StatusDataMaster::Draf,
                    ]);
                }
            }

            foreach ($dataAddon as $data) {
                $tambahan = is_array($data['TambahanBatas'] ?? null)
                    ? array_map('intval', array_intersect_key($data['TambahanBatas'], array_flip(Paket::KOLOM_BATAS)))
                    : null;

                Addon::query()->firstOrCreate(['Kode' => self::AmbilTeks($data, 'Kode')], [
                    'Nama' => self::AmbilTeks($data, 'Nama'),
                    'HargaBulanan' => self::AmbilTeks($data, 'HargaBulanan'),
                    'KunciFitur' => is_string($data['KunciFitur'] ?? null) ? $data['KunciFitur'] : null,
                    'TambahanBatas' => $tambahan === [] ? null : $tambahan,
                    'Status' => StatusPaket::Diarsipkan,
                ]);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function BacaJson(string $path, string $kunci): array
    {
        $isi = is_readable($path) ? file_get_contents($path) : false;
        $data = $isi === false ? null : json_decode($isi, true);

        if (! is_array($data) || ! is_array($data[$kunci] ?? null)) {
            throw new RuntimeException("File data katalog tidak valid: {$path}");
        }

        return array_values(array_filter($data[$kunci], 'is_array'));
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function AmbilTeks(array $data, string $kunci): string
    {
        if (! is_string($data[$kunci] ?? null)) {
            throw new RuntimeException("Kolom {$kunci} wajib berupa teks di file data katalog.");
        }

        return $data[$kunci];
    }
}
