<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\HalamanSitus;
use App\Domain\Situs\Model\RevisiHalamanSitus;
use Illuminate\Support\Facades\DB;

/**
 * D-21: menerbitkan draf halaman situs (salin draf ke kolom terbit). Versi terbit sebelumnya digantikan.
 */
final class TerbitkanHalamanSitus
{
    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly CatatRevisiHalamanSitus $revisi,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, HalamanSitus $halaman): HalamanSitus
    {
        return DB::transaction(function () use ($pelaku, $halaman): HalamanSitus {
            $halaman = HalamanSitus::query()->whereKey($halaman->Id)->lockForUpdate()->firstOrFail();
            $halaman->fill([
                'BagianTerbit' => $halaman->BagianDraf,
                'JudulTerbit' => $halaman->Judul,
                'JudulSeoTerbit' => $halaman->JudulSeo,
                'DeskripsiSeoTerbit' => $halaman->DeskripsiSeo,
                'UuidGambarOgTerbit' => $halaman->UuidGambarOg,
                'DiterbitkanPada' => now(),
                'IdPenggunaPengelolaPenerbit' => $pelaku->Id,
                'JadwalTerbitPada' => null,
                'IdPenggunaPengelolaPenjadwal' => null,
            ])->save();
            $this->revisi->Jalankan($halaman, RevisiHalamanSitus::JENIS_TERBIT, $pelaku->Id);

            $this->audit->Catat('situs.halaman.terbitkan', $halaman, nilaiBaru: ['Slug' => $halaman->Slug, 'Judul' => $halaman->Judul], idPelaku: $pelaku->Id);

            return $halaman;
        });
    }
}
