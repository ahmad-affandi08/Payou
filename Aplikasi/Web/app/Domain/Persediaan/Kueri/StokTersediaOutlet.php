<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Persediaan\Layanan\PemeriksaStokMinus;
use App\Domain\Persediaan\Model\SaldoStok;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use Generator;

/**
 * F-07 + F-05 BR-05.2 di POS: sisa stok produk berstok yang TIDAK BOLEH MINUS di lokasi stok Toko outlet, supaya
 * kasir offline-first bisa menahan penjualan saat stok kosong. Produk yang boleh minus (Produk.BolehMinus ?? pengaturan
 * tenant, hanya bila tanpa pelacakan batch/seri, lewat `PemeriksaStokMinus`), jasa, paket, resep, dan induk varian
 * tidak ikut: ketiadaannya di daftar berarti tanpa batas. Produk tanpa baris `SaldoStok` = "0.0000". Sisa dalam satuan
 * dasar, string desimal 4 digit, boleh nol atau negatif. Dialirkan per potongan agar memori tetap.
 */
final class StokTersediaOutlet
{
    private const UKURAN_POTONGAN = 500;

    public function __construct(
        private readonly InfoProdukStok $produk,
        private readonly OutletPenjualan $outlet,
        private readonly PengaturanPersediaanTenant $pengaturan,
        private readonly PemeriksaStokMinus $pemeriksa,
    ) {}

    /**
     * @return Generator<int, array{UuidProduk: string, Tersedia: string}>
     */
    public function Ambil(int $idOutlet): Generator
    {
        $idGudang = $this->outlet->AmbilIdGudangToko($idOutlet);

        if ($idGudang === null) {
            return;
        }

        $pengaturan = $this->pengaturan->Ambil();
        /** @var list<DataInfoProdukStok> $potongan */
        $potongan = [];

        foreach ($this->produk->AmbilSemuaBerstok() as $produk) {
            if ($this->pemeriksa->CekBolehMinus($produk, $pengaturan)) {
                continue;
            }

            $potongan[] = $produk;

            if (count($potongan) >= self::UKURAN_POTONGAN) {
                yield from $this->AmbilSaldo($potongan, $idGudang);
                $potongan = [];
            }
        }

        if ($potongan !== []) {
            yield from $this->AmbilSaldo($potongan, $idGudang);
        }
    }

    /**
     * @param  list<DataInfoProdukStok>  $produk
     * @return Generator<int, array{UuidProduk: string, Tersedia: string}>
     */
    private function AmbilSaldo(array $produk, int $idGudang): Generator
    {
        $saldo = SaldoStok::query()
            ->where('IdGudang', $idGudang)
            ->whereIn('IdProduk', array_map(fn (DataInfoProdukStok $p): int => $p->id, $produk))
            ->pluck('JumlahTersedia', 'IdProduk')
            ->all();

        foreach ($produk as $p) {
            yield [
                'UuidProduk' => $p->uuid,
                'Tersedia' => Kuantitas::Dari($saldo[$p->id] ?? '0')->KeString(),
            ];
        }
    }
}
