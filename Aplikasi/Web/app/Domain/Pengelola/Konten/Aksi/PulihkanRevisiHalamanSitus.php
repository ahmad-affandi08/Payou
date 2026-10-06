<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Layanan\ValidatorBagianSitus;
use App\Domain\Situs\Model\HalamanSitus;
use App\Domain\Situs\Model\RevisiHalamanSitus;
use Illuminate\Support\Facades\DB;

/**
 * D-63: memulihkan isi revisi ke DRAF (publik tidak berubah sampai diterbitkan). Draf yang sedang ada disimpan dulu
 * sebagai revisi `SebelumPulih`, jadi pemulihan sendiri bisa dibatalkan. Blok diperiksa ulang karena skema bisa berubah.
 */
final class PulihkanRevisiHalamanSitus
{
    public function __construct(
        private readonly ValidatorBagianSitus $validator,
        private readonly CatatRevisiHalamanSitus $catat,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, HalamanSitus $halaman, RevisiHalamanSitus $revisi): HalamanSitus
    {
        if ($revisi->IdHalamanSitus !== $halaman->Id) {
            throw new PelanggaranAturanBisnis('RevisiBukanMilikHalaman', 'Revisi ini bukan milik halaman tersebut.');
        }

        $bagian = $this->validator->Periksa($revisi->Bagian);

        return DB::transaction(function () use ($pelaku, $halaman, $revisi, $bagian): HalamanSitus {
            $halaman = HalamanSitus::query()->whereKey($halaman->Id)->lockForUpdate()->firstOrFail();
            $this->catat->Jalankan($halaman, RevisiHalamanSitus::JENIS_SEBELUM_PULIH, $pelaku->Id);
            $halaman->fill([
                'Judul' => $revisi->Judul,
                'JudulSeo' => $revisi->JudulSeo,
                'DeskripsiSeo' => $revisi->DeskripsiSeo,
                'UuidGambarOg' => $revisi->UuidGambarOg,
                'BagianDraf' => $bagian,
                'IdPenggunaPengelolaPengubah' => $pelaku->Id,
            ])->save();

            $this->audit->Catat('situs.halaman.pulihkan', $halaman, nilaiBaru: ['Slug' => $halaman->Slug, 'Revisi' => $revisi->Uuid], idPelaku: $pelaku->Id);

            return $halaman;
        });
    }
}
