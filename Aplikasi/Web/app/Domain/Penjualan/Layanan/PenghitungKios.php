<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Penjualan\Data\DataKonteksPesanSendiri;
use App\Domain\Penjualan\Enum\JenisSantapKios;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Persediaan\Layanan\PencadangStok;

/**
 * F-17 bagian 4: hitung keranjang kios. Peramban tidak pernah menghitung harga: harga kanal (makan di tempat atau
 * bawa pulang), pilihan, promo otomatis tanpa identitas pelanggan, pajak, dan biaya layanan semuanya dihitung server
 * dengan mesin yang sama dengan kasir dan QR meja. Stok diperiksa terhadap stok toko dikurangi cadangan pesanan aktif
 * (`PencadangStok`), jadi kios tidak menjual yang sudah dicadangkan pesanan lain.
 */
final class PenghitungKios
{
    public function __construct(
        private readonly PenghitungPesanSendiri $dasar,
        private readonly PencadangStok $pencadang,
    ) {}

    public static function KanalDari(JenisSantapKios $santap): KanalPenjualan
    {
        return $santap === JenisSantapKios::MakanDiTempat ? KanalPenjualan::MakanDiTempat : KanalPenjualan::BawaPulang;
    }

    /**
     * @param  list<array{UuidProduk: string, Jumlah: int, Pilihan: list<string>, UuidVarian?: string|null}>  $baris
     * @return array<string, mixed>
     */
    public function Hitung(DataKonteksPesanSendiri $konteks, array $baris, JenisSantapKios $santap): array
    {
        $hasil = $this->dasar->Hitung($konteks, $baris, self::KanalDari($santap), false);
        $this->pencadang->Periksa($konteks->idOutlet, PenghitungTokoOnline::BarisCadangan($hasil['Baris']));

        return [...$hasil, 'Total' => $hasil['Perkiraan']['Total']];
    }
}
