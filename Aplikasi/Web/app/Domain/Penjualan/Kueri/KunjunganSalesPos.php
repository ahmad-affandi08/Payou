<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Model\KunjunganSales;
use App\Domain\Penjualan\Model\PesananGrosir;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Kunjungan salesman untuk API aplikasi (Modul Salesman bagian 1, §9.7): kunjungan terakhir per pelanggan dan daftar
 * kunjungan seorang salesman pada satu tanggal (zona waktu outlet). Waktu ISO-8601 UTC, koordinat string desimal.
 */
final class KunjunganSalesPos
{
    public function __construct(private readonly IdentitasPelanggan $identitas) {}

    /**
     * @param  list<int>  $idPelanggan
     * @return array<int, string> Id pelanggan → MasukPada terakhir (ISO-8601 UTC)
     */
    public function AmbilTerakhir(array $idPelanggan): array
    {
        if ($idPelanggan === []) {
            return [];
        }

        $hasil = [];

        foreach (KunjunganSales::query()->whereIn('IdPelanggan', $idPelanggan)->groupBy('IdPelanggan')->selectRaw('`IdPelanggan`, MAX(`MasukPada`) AS `Terakhir`')->toBase()->get() as $baris) {
            $hasil[(int) $baris->IdPelanggan] = CarbonImmutable::parse((string) $baris->Terakhir, 'UTC')->toIso8601ZuluString();
        }

        return $hasil;
    }

    /**
     * @return list<array{Uuid: string, UuidPelanggan: string|null, NamaPelanggan: string, MasukPada: string, KeluarPada: string|null, Latitude: string|null, Longitude: string|null, AkurasiMeter: int|null, Hasil: string, LabelHasil: string, Catatan: string|null, UuidPesananGrosir: string|null, NomorPesananGrosir: string|null}>
     */
    public function DaftarHarian(int $idPengguna, CarbonInterface $tanggal): array
    {
        $daftar = KunjunganSales::query()
            ->where('IdPengguna', $idPengguna)
            ->where('Tanggal', $tanggal->format('Y-m-d'))
            ->orderBy('MasukPada')
            ->orderBy('Id')
            ->get();
        $pelanggan = $this->identitas->AmbilNamaBanyak(array_values(array_unique(array_map('intval', $daftar->pluck('IdPelanggan')->all()))));
        $pesanan = PesananGrosir::query()->whereIn('Id', $daftar->pluck('IdPesananGrosir')->filter()->all())->get(['Id', 'Uuid', 'Nomor'])->keyBy('Id');

        return array_values($daftar->map(fn (KunjunganSales $k): array => [
            'Uuid' => $k->Uuid,
            'UuidPelanggan' => $pelanggan[$k->IdPelanggan]['Uuid'] ?? null,
            'NamaPelanggan' => $pelanggan[$k->IdPelanggan]['Nama'] ?? '',
            'MasukPada' => $k->MasukPada->copy()->utc()->toIso8601ZuluString(),
            'KeluarPada' => $k->KeluarPada?->copy()->utc()->toIso8601ZuluString(),
            'Latitude' => $k->Latitude,
            'Longitude' => $k->Longitude,
            'AkurasiMeter' => $k->AkurasiMeter,
            'Hasil' => $k->Hasil->value,
            'LabelHasil' => $k->Hasil->AmbilLabel(),
            'Catatan' => $k->Catatan,
            'UuidPesananGrosir' => $k->IdPesananGrosir === null ? null : $pesanan->get($k->IdPesananGrosir)?->Uuid,
            'NomorPesananGrosir' => $k->IdPesananGrosir === null ? null : $pesanan->get($k->IdPesananGrosir)?->Nomor,
        ])->all());
    }
}
