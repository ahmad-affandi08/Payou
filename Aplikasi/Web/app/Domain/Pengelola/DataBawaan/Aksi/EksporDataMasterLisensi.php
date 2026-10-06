<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\DataBawaan\Aksi;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Referensi\Model\HariLibur;

/**
 * D-35: paket data master untuk server edisi Lisensi, diekspor dari server SaaS Payoung. Isinya hanya data yang sudah
 * **terbit** lewat konsol (tarif pajak & hari libur yang lolos four-eyes BR-P02.2), sehingga server pembeli menerima
 * data yang sama persis tanpa ada yang diketik ulang. Hari libur yang dibatalkan ikut ditandai agar pembatalannya
 * sampai juga ke server pembeli.
 */
final class EksporDataMasterLisensi
{
    public const VERSI_FORMAT = 1;

    /**
     * @return array{Format: int, DibuatPada: string, TarifPajak: array<int, array<string, mixed>>, HariLibur: array<int, array<string, mixed>>}
     */
    public function Jalankan(): array
    {
        $tarif = TarifPajak::query()
            ->with('JenisPajak')
            ->where('Status', StatusDataMaster::Terbit->value)
            ->orderBy('IdJenisPajak')
            ->orderBy('BerlakuMulai')
            ->get()
            ->map(fn (TarifPajak $baris): array => [
                'KodeJenisPajak' => $baris->JenisPajak->Kode,
                'Tarif' => $baris->Tarif,
                'PengaliDppPembilang' => $baris->PengaliDppPembilang,
                'PengaliDppPenyebut' => $baris->PengaliDppPenyebut,
                'KodeWilayah' => $baris->KodeWilayah,
                'BiayaLayananMasukDpp' => $baris->BiayaLayananMasukDpp,
                'BerlakuMulai' => $baris->BerlakuMulai->toDateString(),
                'NomorDasarHukum' => $baris->NomorDasarHukum,
                'TautanDasarHukum' => $baris->TautanDasarHukum,
            ])
            ->values()
            ->all();

        $hariLibur = HariLibur::query()
            ->whereIn('Status', [StatusDataMaster::Terbit->value, StatusDataMaster::Dibatalkan->value])
            ->orderBy('Tanggal')
            ->get()
            ->map(fn (HariLibur $baris): array => [
                'Tanggal' => $baris->Tanggal->toDateString(),
                'Nama' => $baris->Nama,
                'Jenis' => $baris->Jenis->value,
                'NomorDasarHukum' => $baris->NomorDasarHukum,
                'Dibatalkan' => $baris->Status === StatusDataMaster::Dibatalkan,
            ])
            ->values()
            ->all();

        return [
            'Format' => self::VERSI_FORMAT,
            'DibuatPada' => now()->toIso8601ZuluString(),
            'TarifPajak' => $tarif,
            'HariLibur' => $hariLibur,
        ];
    }
}
