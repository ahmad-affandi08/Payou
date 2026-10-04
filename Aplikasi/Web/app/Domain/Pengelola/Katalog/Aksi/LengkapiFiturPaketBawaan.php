<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\PaketFitur;
use Illuminate\Support\Facades\DB;

/**
 * Menyusul fitur katalog yang ditambahkan rilis setelah server dipasang (P-04). `SiapkanKatalogBawaan` hanya membuat
 * paket yang belum ada, jadi paket yang sudah terpasang tidak pernah mendapat fitur baru dari `KatalogPaket.json`
 * (contoh: `persetujuan.jarak-jauh` pada Bisnis & Enterprise). Aksi ini **hanya menambah**: baris `Fitur` yang belum ada
 * dibuat, dan fitur yang tercantum di berkas data untuk sebuah paket ditambahkan ke paket itu bila belum ada. Fitur
 * yang sengaja dicabut lewat konsol dari sebuah paket tidak disentuh bila fiturnya tidak tercantum di berkas, dan tidak
 * ada yang pernah dihapus. Idempoten. Paket yang tidak ada di server dilewati (pembuatannya urusan seeder).
 */
final class LengkapiFiturPaketBawaan
{
    /**
     * @return array{FiturBaru: list<string>, Penambahan: list<array{Paket: string, Fitur: string}>}
     */
    public function Jalankan(bool $terapkan = true, ?string $pathFitur = null, ?string $pathPaket = null): array
    {
        $dataFitur = SiapkanKatalogBawaan::BacaJson($pathFitur ?? database_path('Data/KatalogFitur.json'), 'Fitur');
        $dataPaket = SiapkanKatalogBawaan::BacaJson($pathPaket ?? database_path('Data/KatalogPaket.json'), 'Paket');

        return DB::transaction(function () use ($terapkan, $dataFitur, $dataPaket): array {
            $fiturBaru = [];
            $penambahan = [];
            $adaFitur = Fitur::query()->pluck('Kunci')->flip()->all();

            foreach ($dataFitur as $fitur) {
                $kunci = SiapkanKatalogBawaan::AmbilTeks($fitur, 'Kunci');

                if (isset($adaFitur[$kunci])) {
                    continue;
                }

                $fiturBaru[] = $kunci;
                $adaFitur[$kunci] = true;

                if ($terapkan) {
                    Fitur::query()->create(['Kunci' => $kunci, 'Nama' => SiapkanKatalogBawaan::AmbilTeks($fitur, 'Nama'), 'Modul' => SiapkanKatalogBawaan::AmbilTeks($fitur, 'Modul')]);
                }
            }

            foreach ($dataPaket as $data) {
                $kode = SiapkanKatalogBawaan::AmbilTeks($data, 'Kode');
                $paket = Paket::query()->where('Kode', $kode)->first();

                if ($paket === null) {
                    continue;
                }

                $dimiliki = array_flip($paket->AmbilKunciFitur());

                foreach (is_array($data['Fitur'] ?? null) ? $data['Fitur'] : [] as $kunci) {
                    $kunci = (string) $kunci;

                    if (isset($dimiliki[$kunci]) || ! isset($adaFitur[$kunci])) {
                        continue;
                    }

                    $penambahan[] = ['Paket' => $kode, 'Fitur' => $kunci];

                    if ($terapkan) {
                        PaketFitur::query()->create(['IdPaket' => $paket->Id, 'KunciFitur' => $kunci]);
                    }
                }
            }

            return ['FiturBaru' => $fiturBaru, 'Penambahan' => $penambahan];
        });
    }
}
