<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Kueri\CariPenjualanPos;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Permintaan\Pos\V1\CariPenjualanPermintaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * `GET /api/pos/v1/penjualan/cari?nomor=` (F-09 fase 1): struk asal untuk retur di aplikasi POS (perlu online).
 * Hanya penjualan outlet perangkat; tidak ada = 404 `PenjualanTidakDitemukan`. `nomor` boleh nomor struk utuh atau
 * isi QR struk digital. `GET penjualan/kandidat?kata=`: daftar penjualan yang bisa diretur untuk dipilih tanpa
 * mengetik nomor utuh.
 */
final class PenjualanKontroler extends Kontroler
{
    public function Cari(CariPenjualanPermintaan $permintaan, CariPenjualanPos $cari): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $hasil = $cari->Ambil($permintaan->AmbilNomor(), $perangkat->IdOutlet);

        if ($hasil === null) {
            throw new PelanggaranAturanBisnis('PenjualanTidakDitemukan', 'Penjualan dengan nomor ini tidak ditemukan di outlet ini.', 'nomor', 404);
        }

        return response()->json($hasil);
    }

    public function Kandidat(Request $permintaan, CariPenjualanPos $cari): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $kata = Str::limit(trim((string) $permintaan->query('kata', '')), 80, '');

        return response()->json(['Penjualan' => $cari->CariKandidat($kata, $perangkat->IdOutlet)]);
    }
}
