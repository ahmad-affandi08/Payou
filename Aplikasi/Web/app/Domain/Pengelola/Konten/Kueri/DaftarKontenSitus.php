<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Kueri;

use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\GambarSitus;
use App\Domain\Situs\Model\HalamanSitus;
use App\Domain\Situs\Model\RevisiHalamanSitus;

/**
 * Daftar halaman & gambar situs pemasaran untuk konsol (D-21). Jumlah kecil (puluhan), jadi dimuat utuh untuk
 * TabelData mode lokal.
 */
final class DaftarKontenSitus
{
    /**
     * @return list<array<string, mixed>>
     */
    public function AmbilHalaman(): array
    {
        return array_values(HalamanSitus::query()->orderByRaw("Slug <> 'beranda'")->orderBy('Slug')->get()
            ->map(fn (HalamanSitus $h): array => $this->PetakanHalaman($h))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function PetakanHalaman(HalamanSitus $h): array
    {
        return [
            'Uuid' => $h->Uuid,
            'Slug' => $h->Slug,
            'Judul' => $h->Judul,
            'Jalur' => $h->Slug === HalamanSitus::SLUG_BERANDA ? '/' : '/'.$h->Slug,
            'Terbit' => $h->CekTerbit(),
            'AdaPerubahan' => $h->CekAdaPerubahan(),
            'Aktif' => $h->Aktif,
            'DiterbitkanPada' => $h->DiterbitkanPada?->toIso8601ZuluString(),
            'DiubahPada' => $h->DiubahPada?->toIso8601ZuluString(),
        ];
    }

    /**
     * Riwayat revisi satu halaman (terbaru dulu) untuk panel Riwayat di penyunting.
     *
     * @return list<array{Uuid: string, Jenis: string, Judul: string, JumlahBlok: int, DibuatPada: string, Pembuat: string|null}>
     */
    public function AmbilRevisi(HalamanSitus $halaman): array
    {
        $revisi = RevisiHalamanSitus::query()->where('IdHalamanSitus', $halaman->Id)->orderByDesc('Id')->limit(RevisiHalamanSitus::BATAS_PER_HALAMAN)->get();
        $nama = PenggunaPengelola::query()->whereIn('Id', $revisi->pluck('IdPenggunaPengelola')->filter()->unique())->pluck('Nama', 'Id');

        return array_values($revisi->map(fn (RevisiHalamanSitus $r): array => [
            'Uuid' => $r->Uuid,
            'Jenis' => $r->Jenis,
            'Judul' => $r->Judul,
            'JumlahBlok' => count($r->Bagian),
            'DibuatPada' => $r->DibuatPada->toIso8601ZuluString(),
            'Pembuat' => $r->IdPenggunaPengelola === null ? null : (string) ($nama[$r->IdPenggunaPengelola] ?? null),
        ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function AmbilGambar(): array
    {
        return array_values(GambarSitus::query()->orderByDesc('Id')->limit(500)->get()
            ->map(fn (GambarSitus $g): array => [
                'Uuid' => $g->Uuid,
                'Url' => '/gambar-situs/'.$g->Uuid,
                'NamaBerkas' => $g->NamaBerkas,
                'TeksAlternatif' => $g->TeksAlternatif,
                'Lebar' => $g->Lebar,
                'Tinggi' => $g->Tinggi,
                'Ukuran' => $g->Ukuran,
                'DibuatPada' => $g->DibuatPada?->toIso8601ZuluString(),
            ])
            ->all());
    }
}
