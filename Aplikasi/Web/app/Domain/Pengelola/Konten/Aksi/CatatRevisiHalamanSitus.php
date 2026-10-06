<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Situs\Model\HalamanSitus;
use App\Domain\Situs\Model\RevisiHalamanSitus;

/**
 * D-63: menyimpan salinan isi halaman (draf saat ini) sebagai revisi dan membuang revisi tertua di atas batas.
 */
final class CatatRevisiHalamanSitus
{
    public function Jalankan(HalamanSitus $halaman, string $jenis, ?int $idPelaku): RevisiHalamanSitus
    {
        $revisi = RevisiHalamanSitus::query()->create([
            'IdHalamanSitus' => $halaman->Id,
            'Jenis' => $jenis,
            'Judul' => $halaman->Judul,
            'JudulSeo' => $halaman->JudulSeo,
            'DeskripsiSeo' => $halaman->DeskripsiSeo,
            'UuidGambarOg' => $halaman->UuidGambarOg,
            'Bagian' => $halaman->BagianDraf,
            'IdPenggunaPengelola' => $idPelaku,
        ]);

        $simpan = RevisiHalamanSitus::query()->where('IdHalamanSitus', $halaman->Id)->orderByDesc('Id')
            ->limit(RevisiHalamanSitus::BATAS_PER_HALAMAN)->pluck('Id');

        if ($simpan->count() >= RevisiHalamanSitus::BATAS_PER_HALAMAN) {
            RevisiHalamanSitus::query()->where('IdHalamanSitus', $halaman->Id)->whereNotIn('Id', $simpan)->delete();
        }

        return $revisi;
    }
}
