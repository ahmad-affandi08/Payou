<?php

declare(strict_types=1);

namespace App\Domain\Situs\Kueri;

use App\Domain\Bersama\Web\AlamatDomain;
use App\Domain\Situs\Enum\StatusArtikel;
use App\Domain\Situs\Model\ArtikelSitus;
use App\Domain\Situs\Model\GambarSitus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Situs bagian B2: artikel blog terbit untuk `/blog` (12 per halaman, saring kategori), `/blog/{slug}` (isi + artikel
 * terkait sekategori), dan peta situs. Props memuat `Seo` yang dibaca view root untuk meta & JSON-LD.
 */
final class ArtikelSitusPublik
{
    public const PER_HALAMAN = 12;

    public function __construct(private readonly PengaturanSitusBerlaku $pengaturan) {}

    /**
     * @return array{Artikel: list<array<string, mixed>>, Kategori: list<string>, KategoriAktif: string|null, Halaman: int, JumlahHalaman: int, Seo: array<string, mixed>}
     */
    public function AmbilDaftar(int $halaman, ?string $kategori): array
    {
        $kueri = $this->KueriTerbit();

        if ($kategori !== null && $kategori !== '') {
            $kueri->where('Kategori', $kategori);
        }

        $total = (clone $kueri)->count();
        $jumlahHalaman = max(1, intdiv($total + self::PER_HALAMAN - 1, self::PER_HALAMAN));
        $halaman = min(max(1, $halaman), $jumlahHalaman);
        $baris = $kueri->orderByDesc('DiterbitkanPada')->orderByDesc('Id')
            ->forPage($halaman, self::PER_HALAMAN)->get()->values()->all();
        $gambar = $this->AmbilGambar(array_values(array_filter(array_map(fn (ArtikelSitus $a) => $a->UuidGambarSampul, $baris))));
        $p = $this->pengaturan->Ambil();

        return [
            'Artikel' => array_values(array_map(fn (ArtikelSitus $a): array => $this->PetakanRingkas($a, $gambar), $baris)),
            'Kategori' => array_values(array_filter($this->KueriTerbit()->whereNotNull('Kategori')->distinct()->orderBy('Kategori')->pluck('Kategori')->all(), 'is_string')),
            'KategoriAktif' => $kategori !== null && $kategori !== '' ? $kategori : null,
            'Halaman' => $halaman,
            'JumlahHalaman' => $jumlahHalaman,
            'Seo' => $this->BuatSeo(
                'Blog | '.$p['NamaSitus'],
                'Tips kasir, stok, keuangan, dan pemasaran untuk usaha di Indonesia dari tim '.$p['NamaSitus'].'.',
                '/blog'.($halaman > 1 ? '?halaman='.$halaman : ''),
                null,
                'website',
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function AmbilArtikel(string $slug): ?array
    {
        $artikel = $this->KueriTerbit()->where('Slug', $slug)->first();

        if ($artikel === null) {
            return null;
        }

        $terkait = $this->KueriTerbit()->whereKeyNot($artikel->Id)
            ->when($artikel->Kategori !== null, fn (Builder $k) => $k->where('Kategori', $artikel->Kategori))
            ->orderByDesc('DiterbitkanPada')->limit(3)->get()->all();
        $uuid = array_values(array_filter([$artikel->UuidGambarSampul, ...array_map(fn (ArtikelSitus $a) => $a->UuidGambarSampul, $terkait)]));
        $gambar = $this->AmbilGambar($uuid);
        $p = $this->pengaturan->Ambil();

        return [
            'Artikel' => [
                ...$this->PetakanRingkas($artikel, $gambar),
                'Isi' => $artikel->Isi,
                'DiubahPada' => $artikel->DiubahPada?->toIso8601String(),
            ],
            'Terkait' => array_map(fn (ArtikelSitus $a): array => $this->PetakanRingkas($a, $gambar), $terkait),
            'Seo' => $this->BuatSeo(
                $artikel->JudulSeo ?? $artikel->Judul.' | '.$p['NamaSitus'],
                $artikel->DeskripsiSeo ?? $artikel->Ringkasan ?? $p['DeskripsiSeo'],
                '/blog/'.$artikel->Slug,
                $artikel->UuidGambarSampul !== null ? ($gambar[$artikel->UuidGambarSampul]['UrlAbsolut'] ?? null) : null,
                'article',
                [
                    'Judul' => $artikel->Judul,
                    'Penulis' => $artikel->NamaPenulis ?? $p['NamaSitus'],
                    'DiterbitkanPada' => $artikel->DiterbitkanPada?->toIso8601String(),
                    'DiubahPada' => $artikel->DiubahPada?->toIso8601String(),
                ],
            ),
        ];
    }

    /**
     * Artikel terbit untuk peta situs.
     *
     * @return list<array{Slug: string, DiubahPada: string|null}>
     */
    public function AmbilUntukPetaSitus(): array
    {
        return array_values($this->KueriTerbit()->orderByDesc('DiterbitkanPada')->get(['Slug', 'DiubahPada'])
            ->map(fn (ArtikelSitus $a): array => ['Slug' => $a->Slug, 'DiubahPada' => $a->DiubahPada?->toDateString()])
            ->all());
    }

    /**
     * @return Builder<ArtikelSitus>
     */
    private function KueriTerbit(): Builder
    {
        return ArtikelSitus::query()->where('Status', StatusArtikel::Terbit->value)->whereNotNull('DiterbitkanPada');
    }

    /**
     * @param  array<string, array<string, mixed>>  $gambar
     * @return array<string, mixed>
     */
    private function PetakanRingkas(ArtikelSitus $a, array $gambar): array
    {
        return [
            'Slug' => $a->Slug,
            'Judul' => $a->Judul,
            'Ringkasan' => $a->Ringkasan,
            'Kategori' => $a->Kategori,
            'NamaPenulis' => $a->NamaPenulis,
            'Sampul' => $a->UuidGambarSampul !== null ? ($gambar[$a->UuidGambarSampul] ?? null) : null,
            'DiterbitkanPada' => $a->DiterbitkanPada?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $artikel
     * @return array<string, mixed>
     */
    private function BuatSeo(string $judul, string $deskripsi, string $jalur, ?string $gambar, string $jenis, ?array $artikel = null): array
    {
        $p = $this->pengaturan->Ambil();
        $ogBawaan = is_string($p['UuidGambarOg'] ?? null) ? ($this->AmbilGambar([$p['UuidGambarOg']])[$p['UuidGambarOg']]['UrlAbsolut'] ?? null) : null;

        return [
            'Judul' => mb_substr($judul, 0, 120),
            'Deskripsi' => mb_substr($deskripsi, 0, 300),
            'KataKunci' => $p['KataKunci'],
            'Gambar' => $gambar ?? $ogBawaan,
            'Kanonik' => AlamatDomain::BuatUrlAbsolutPemasaran($jalur),
            'NamaSitus' => $p['NamaSitus'],
            'VerifikasiGoogle' => $p['VerifikasiGoogle'],
            'Jenis' => $jenis,
            'Artikel' => $artikel,
        ];
    }

    /**
     * @param  list<string>  $uuid
     * @return array<string, array{Uuid: string, Url: string, UrlAbsolut: string, Alt: string, Lebar: int|null, Tinggi: int|null}>
     */
    private function AmbilGambar(array $uuid): array
    {
        if ($uuid === []) {
            return [];
        }

        $hasil = [];

        foreach (GambarSitus::query()->whereIn('Uuid', array_unique($uuid))->get() as $g) {
            $hasil[$g->Uuid] = [
                'Uuid' => $g->Uuid,
                'Url' => '/gambar-situs/'.$g->Uuid,
                'UrlAbsolut' => AlamatDomain::BuatUrlAbsolutPemasaran('/gambar-situs/'.$g->Uuid),
                'Alt' => (string) ($g->TeksAlternatif ?? ''),
                'Lebar' => $g->Lebar,
                'Tinggi' => $g->Tinggi,
            ];
        }

        return $hasil;
    }
}
