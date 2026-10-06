<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Model\Shift;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Shift yang belum tertutup (terbuka, sedang menutup, dibuka ulang) sampai tanggal bisnis tertentu, untuk syarat tutup
 * buku F-15: periode/hari tidak boleh ditutup selama kasnya belum direkonsiliasi.
 */
final class ShiftBelumDitutup
{
    /**
     * @param  int|null  $idOutlet  null = semua outlet tenant
     */
    public function Hitung(CarbonInterface $sampaiTanggal, ?int $idOutlet = null, ?CarbonInterface $dariTanggal = null): int
    {
        return Shift::query()
            ->whereIn('Status', [StatusShift::Terbuka->value, StatusShift::Menutup->value, StatusShift::DibukaUlang->value])
            ->where('TanggalBisnis', '<=', $sampaiTanggal->toDateString())
            ->when($dariTanggal !== null, fn ($k) => $k->where('TanggalBisnis', '>=', $dariTanggal?->toDateString()))
            ->when($idOutlet !== null, fn ($k) => $k->where('IdOutlet', $idOutlet))
            ->count();
    }

    /**
     * Jumlah shift belum tertutup per outlet per tanggal bisnis dalam rentang, kunci `"{IdOutlet}|{Y-m-d}"`.
     *
     * @param  list<int>|null  $idOutlet  null = semua outlet tenant
     * @return array<string, int>
     */
    public function HitungPerHari(?array $idOutlet, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $hasil = [];

        foreach (Shift::query()
            ->whereIn('Status', [StatusShift::Terbuka->value, StatusShift::Menutup->value, StatusShift::DibukaUlang->value])
            ->whereBetween('TanggalBisnis', [$dari->toDateString(), $sampai->toDateString()])
            ->when($idOutlet !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->groupBy('IdOutlet', 'TanggalBisnis')
            ->toBase()
            ->get(['IdOutlet', 'TanggalBisnis', DB::raw('COUNT(*) AS Jumlah')]) as $b) {
            $hasil[$b->IdOutlet.'|'.substr((string) $b->TanggalBisnis, 0, 10)] = (int) $b->Jumlah;
        }

        return $hasil;
    }
}
