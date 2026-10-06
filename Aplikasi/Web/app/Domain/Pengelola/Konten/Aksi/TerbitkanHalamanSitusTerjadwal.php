<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\HalamanSitus;

/**
 * D-63: menerbitkan halaman yang jadwal terbitnya sudah tiba (dipanggil `situs:terbitkan-terjadwal` tiap menit).
 * Penerbit tercatat = pengelola yang membuat jadwal. Bila akunnya sudah tidak ada, jadwal dibatalkan dan dilewati.
 */
final class TerbitkanHalamanSitusTerjadwal
{
    public function __construct(private readonly TerbitkanHalamanSitus $terbitkan) {}

    /** @return int jumlah halaman yang diterbitkan */
    public function Jalankan(): int
    {
        $jumlah = 0;

        HalamanSitus::query()->whereNotNull('JadwalTerbitPada')->where('JadwalTerbitPada', '<=', now())->orderBy('Id')
            ->each(function (HalamanSitus $halaman) use (&$jumlah): void {
                $pelaku = $halaman->IdPenggunaPengelolaPenjadwal === null ? null : PenggunaPengelola::query()->find($halaman->IdPenggunaPengelolaPenjadwal);

                if ($pelaku === null) {
                    $halaman->forceFill(['JadwalTerbitPada' => null, 'IdPenggunaPengelolaPenjadwal' => null])->save();

                    return;
                }

                $this->terbitkan->Jalankan($pelaku, $halaman);
                $jumlah++;
            });

        return $jumlah;
    }
}
