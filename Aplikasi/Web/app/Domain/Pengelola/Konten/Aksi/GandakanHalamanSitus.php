<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\HalamanSitus;
use Illuminate\Support\Facades\DB;

/**
 * D-63: menggandakan halaman sebagai draf baru (isi = draf saat ini; belum terbit, tersembunyi dari publik sampai
 * diterbitkan). Slug dibuat unik dengan akhiran `-salinan`, `-salinan-2`, …
 */
final class GandakanHalamanSitus
{
    public function __construct(
        private readonly SimpanHalamanSitus $simpan,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, HalamanSitus $asal): HalamanSitus
    {
        return DB::transaction(function () use ($pelaku, $asal): HalamanSitus {
            $dasar = $asal->Slug === HalamanSitus::SLUG_BERANDA ? 'beranda-salinan' : $asal->Slug.'-salinan';
            $slug = $dasar;

            for ($urut = 2; HalamanSitus::query()->where('Slug', $slug)->exists(); $urut++) {
                $slug = $dasar.'-'.$urut;
            }

            $baru = $this->simpan->Jalankan($pelaku, [
                'Slug' => $slug,
                'Judul' => mb_substr($asal->Judul, 0, 135).' (salinan)',
                'JudulSeo' => $asal->JudulSeo,
                'DeskripsiSeo' => $asal->DeskripsiSeo,
                'UuidGambarOg' => $asal->UuidGambarOg,
                'TampilDiSitemap' => $asal->TampilDiSitemap,
                'Bagian' => $asal->BagianDraf,
            ]);

            $this->audit->Catat('situs.halaman.gandakan', $baru, nilaiBaru: ['Asal' => $asal->Slug, 'Slug' => $baru->Slug], idPelaku: $pelaku->Id);

            return $baru;
        });
    }
}
