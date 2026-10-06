<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Kueri;

use App\Domain\Situs\Kueri\PenyusunHalamanSitus;
use App\Domain\Situs\Layanan\SkemaBagianSitus;
use App\Domain\Situs\Layanan\ValidatorBagianSitus;
use App\Domain\Situs\Model\GambarSitus;
use App\Domain\Situs\Model\HalamanSitus;
use Illuminate\Validation\ValidationException;

/**
 * Editor visual (D-63): menyusun props halaman dari draf yang BELUM disimpan supaya pratinjau berubah saat mengetik.
 * Tiap blok diperiksa sendiri-sendiri: blok yang belum lengkap diganti penanda `BelumLengkap` (urutan tetap, jadi
 * klik di pratinjau tetap menunjuk blok yang benar) dan galatnya dikembalikan dengan kunci `Bagian.{i}.{bidang}`.
 * Tidak menulis apa pun ke basis data.
 */
final class PratinjauLangsungHalamanSitus
{
    public function __construct(
        private readonly ValidatorBagianSitus $validator,
        private readonly PenyusunHalamanSitus $penyusun,
    ) {}

    /**
     * @param  array{Judul?: mixed, JudulSeo?: mixed, DeskripsiSeo?: mixed, UuidGambarOg?: mixed, Bagian?: mixed}  $data
     * @return array{Halaman: array<string, mixed>, Galat: array<string, string>}
     */
    public function Susun(HalamanSitus $halaman, array $data): array
    {
        $blokMasuk = is_array($data['Bagian'] ?? null) && array_is_list($data['Bagian']) ? array_slice($data['Bagian'], 0, SkemaBagianSitus::MAKS_BAGIAN) : [];
        $bagian = [];
        $galat = [];

        foreach ($blokMasuk as $i => $blok) {
            try {
                $bagian[] = $this->validator->Periksa([$blok])[0];
            } catch (ValidationException $e) {
                $jenis = is_array($blok) && is_string($blok['Jenis'] ?? null) ? $blok['Jenis'] : '';
                $bagian[] = ['Jenis' => 'BelumLengkap', 'Label' => SkemaBagianSitus::LABEL[$jenis] ?? 'Blok'];

                foreach ($e->errors() as $kunci => $pesan) {
                    $galat[(string) preg_replace('/^Bagian\.0/', "Bagian.{$i}", (string) $kunci)] = (string) ($pesan[0] ?? 'Belum lengkap.');
                }
            }
        }

        $og = is_string($data['UuidGambarOg'] ?? null) && $data['UuidGambarOg'] !== '' ? strtoupper($data['UuidGambarOg']) : null;

        if ($og !== null && ! GambarSitus::query()->where('Uuid', $og)->exists()) {
            $og = null;
        }

        $judul = is_string($data['Judul'] ?? null) && trim($data['Judul']) !== '' ? trim($data['Judul']) : $halaman->Judul;
        $rapikan = fn (mixed $teks): ?string => is_string($teks) && trim($teks) !== '' ? trim($teks) : null;

        return [
            'Halaman' => $this->penyusun->AmbilDrafSementara($halaman->Slug, $judul, $rapikan($data['JudulSeo'] ?? null), $rapikan($data['DeskripsiSeo'] ?? null), $og, $bagian),
            'Galat' => $galat,
        ];
    }
}
