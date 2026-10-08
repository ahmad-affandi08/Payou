<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Persediaan\Kueri\StokTersediaOutlet;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-07 + F-05 BR-05.2 di POS: `GET /api/pos/v1/stok-tersedia` mengembalikan sisa stok di lokasi stok Toko outlet
 * perangkat untuk produk berstok yang tidak boleh minus: `{WaktuServer, Produk: [{UuidProduk, Tersedia}]}`. Produk
 * yang tidak ada di daftar = tanpa batas (boleh minus, jasa, paket, resep). Hanya baca; penerimaan penjualan di server
 * tidak berubah (tetap menerima dan menandai tinjauan `StokTidakCukup`).
 */
final class StokTersediaKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, StokTersediaOutlet $kueri): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $waktu = now()->utc()->toIso8601ZuluString();

        return response()->json([
            'WaktuServer' => $waktu,
            'Produk' => iterator_to_array($kueri->Ambil($perangkat->IdOutlet), false),
        ]);
    }
}
