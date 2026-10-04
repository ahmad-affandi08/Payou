<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Layanan;

use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\Laporan\Kueri\LaporanStok;
use Carbon\CarbonImmutable;

/**
 * Kotak Tindakan stok (D-23 C, izin lihat `persediaan.lihat`, dibatasi outlet akses): produk yang stoknya di bawah
 * atau sama dengan stok minimum gudangnya; dan F-05g batch yang sudah lewat atau akan kedaluwarsa dalam 30 hari (`Penting`
 * bila sudah ada yang lewat). Keduanya selesai sendiri saat stok diisi / batch habis dijual atau dibuang.
 */
final class PenyediaTindakanStok implements PenyediaTindakan
{
    public function __construct(private readonly LaporanStok $laporan) {}

    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        if (! $konteks->CekIzin('persediaan.lihat')) {
            return [];
        }

        $kritis = $this->laporan->StokKritis($konteks->idOutletBoleh, batas: DataButirTindakan::BATAS_RINCIAN);
        $butir = [new DataButirTindakan(
            'stok.kritis',
            'Persediaan',
            TingkatTindakan::Perhatian,
            'Stok menipis',
            'Stok di bawah batas minimum. Pesan ke pemasok sebelum habis.',
            (int) $kritis['Jumlah'],
            '/kelola/laporan/stok?tab=kritis',
            'Lihat stok kritis',
            array_map(fn (array $b): DataRincianTindakan => new DataRincianTindakan(
                (string) $b['Kunci'],
                (string) $b['NamaProduk'],
                "Sisa {$b['Saldo']} {$b['SimbolSatuan']}, minimum {$b['StokMinimum']} | {$b['NamaGudang']}",
                null,
                null,
            ), $kritis['Baris']),
        )];

        $kedaluwarsa = $this->laporan->BatchKedaluwarsa(
            $konteks->idOutletBoleh,
            CarbonImmutable::now('Asia/Jakarta')->startOfDay(),
            batas: DataButirTindakan::BATAS_RINCIAN,
        );

        $butir[] = new DataButirTindakan(
            'stok.kedaluwarsa',
            'Persediaan',
            $kedaluwarsa['JumlahLewat'] > 0 ? TingkatTindakan::Penting : TingkatTindakan::Perhatian,
            'Barang mendekati kedaluwarsa',
            $kedaluwarsa['JumlahLewat'] > 0
                ? "{$kedaluwarsa['JumlahLewat']} batch sudah lewat kedaluwarsa. Tarik dari rak dan catat sebagai bahan terbuang atau penyesuaian."
                : 'Batch yang kedaluwarsa dalam 30 hari. Jual lebih dulu atau beri promo sebelum lewat tanggal.',
            (int) $kedaluwarsa['Jumlah'],
            '/kelola/laporan/stok?tab=kedaluwarsa',
            'Lihat batch kedaluwarsa',
            array_map(fn (array $b): DataRincianTindakan => new DataRincianTindakan(
                (string) $b['Kunci'],
                "{$b['NamaProduk']} | batch {$b['NomorBatch']}",
                "Sisa {$b['Sisa']} {$b['SimbolSatuan']} | {$b['NamaGudang']} | ".($b['SisaHari'] < 0 ? 'lewat '.abs($b['SisaHari']).' hari' : ($b['SisaHari'] === 0 ? 'kedaluwarsa hari ini' : "kedaluwarsa {$b['SisaHari']} hari lagi")),
                $b['TanggalKedaluwarsa'],
                null,
            ), $kedaluwarsa['Baris']),
        );

        return $butir;
    }

    public function AmbilJenisDokumen(): array
    {
        return [];
    }

    public function SaringDokumen(string $jenisDokumen, array $uuid): array
    {
        return [];
    }
}
