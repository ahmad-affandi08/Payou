<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Permintaan tagihan QRIS dinamis. [nomorPesanan] unik di gerbang (dipakai sebagai order id / reference id);
 * jumlah dalam rupiah penuh (QRIS tidak mengenal sen).
 */
final readonly class PermintaanQris
{
    public function __construct(
        public string $nomorPesanan,
        public Uang $jumlah,
        public string $keterangan,
        public CarbonImmutable $kedaluwarsaPada,
        public string $urlNotifikasi,
        public string $namaPelanggan = 'Pelanggan',
        public string $emailPelanggan = 'pelanggan@payoung.id',
        public string $teleponPelanggan = '080000000000',
    ) {}

    public function AmbilJumlahBulat(): int
    {
        return (int) $this->jumlah->KeString();
    }
}
