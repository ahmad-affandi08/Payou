<?php

declare(strict_types=1);

namespace App\Domain\Situs\Layanan;

/**
 * Kerangka HTML dari server untuk halaman situs pemasaran (D-21, tanpa SSR Node di hosting bersama).
 *
 * Halaman situs dirender React di peramban. Sebelum bundelnya selesai dimuat (puluhan modul kecil), atau bila
 * satu modul gagal diambil, `<div id="app">` kosong: pengunjung melihat layar putih, dan perayap/pratinjau tautan
 * yang tidak menjalankan JavaScript tidak menemukan isi apa pun. Kerangka ini mengisi `#app` dengan blok pertama
 * (judul, pengantar, tombol, gambar pertama) memakai kelas Tailwind yang sama dengan komponen React-nya, dan
 * `<noscript>` memuat ringkasan teks blok lainnya. React `createRoot` mengganti isi `#app` saat siap, jadi
 * kerangka tidak pernah ikut tampil berdampingan dengan halaman sebenarnya.
 *
 * Semua teks di-escape oleh Blade (`{{ }}`) atau {@see self::H()}; tidak ada HTML dari data konsol yang dilewatkan mentah.
 */
final class KerangkaHalamanSitus
{
    /** Spesimen → berkas statis (tablet 1600 × 1000, ponsel 540 × 1080). Selaras `Komponen/Situs/SpesimenSitus.tsx`. */
    private const FOTO = [
        'Kasir' => ['kasir-jual', 1600, 1000],
        'KasirBayar' => ['kasir-bayar', 1600, 1000],
        'KasirBerhasil' => ['kasir-berhasil', 1600, 1000],
        'KasirShift' => ['kasir-shift', 1600, 1000],
        'KasirRiwayat' => ['kasir-riwayat', 1600, 1000],
        'KasirKas' => ['kasir-kas', 1600, 1000],
        'KasirHp' => ['kasir-hp-jual', 540, 1080],
        'Pemilik' => ['pemilik-beranda', 540, 1080],
        'PemilikDuo' => ['pemilik-beranda', 540, 1080],
        'PemilikLaporan' => ['pemilik-laporan', 540, 1080],
        'PemilikPersetujuan' => ['pemilik-persetujuan', 540, 1080],
        'PemilikKaryawan' => ['pemilik-karyawan', 540, 1080],
        'PemilikInsight' => ['pemilik-insight', 540, 1080],
    ];

    private const KUNCI_JUDUL = ['Judul', 'Nama', 'Pertanyaan'];

    private const KUNCI_PARAGRAF = ['Subjudul', 'Teks', 'Isi', 'Jawaban', 'Keterangan', 'Catatan', 'Kutipan'];

    private const KUNCI_DAFTAR = ['Sorotan', 'Item', 'Kelompok', 'Paket', 'Poin', 'Fitur'];

    /**
     * Isi pembuka dari blok pertama halaman, atau null bila halaman tanpa blok.
     *
     * @param  list<array<string, mixed>>  $bagian
     * @return array{Label: ?string, Judul: string, Teks: ?string, TombolUtama: ?array<string, string>, TombolKedua: ?array<string, string>, Gambar: ?array{Url: string, Lebar: ?int, Tinggi: ?int, Alt: string}}|null
     */
    public static function AmbilPembuka(array $bagian): ?array
    {
        $blok = $bagian[0] ?? null;

        if (! is_array($blok)) {
            return null;
        }

        $sumber = ($blok['Jenis'] ?? null) === 'HeroGeser' ? ($blok['Sorotan'][0] ?? null) : $blok;

        if (! is_array($sumber) || ! is_string($sumber['Judul'] ?? null)) {
            return null;
        }

        $gambar = null;

        if (is_array($sumber['Gambar'] ?? null) && is_string($sumber['Gambar']['Url'] ?? null)) {
            $gambar = [
                'Url' => $sumber['Gambar']['Url'],
                'Lebar' => is_int($sumber['Gambar']['Lebar'] ?? null) ? $sumber['Gambar']['Lebar'] : null,
                'Tinggi' => is_int($sumber['Gambar']['Tinggi'] ?? null) ? $sumber['Gambar']['Tinggi'] : null,
                'Alt' => (string) ($sumber['Gambar']['Alt'] ?? ''),
            ];
        } elseif (is_string($sumber['Spesimen'] ?? null) && isset(self::FOTO[$sumber['Spesimen']])) {
            [$berkas, $lebar, $tinggi] = self::FOTO[$sumber['Spesimen']];
            $gambar = ['Url' => "/situs/produk/{$berkas}.webp", 'Lebar' => $lebar, 'Tinggi' => $tinggi, 'Alt' => 'Tangkapan layar aplikasi Payoung'];
        }

        $teks = $sumber['Teks'] ?? $sumber['Subjudul'] ?? null;

        return [
            'Label' => is_string($sumber['Label'] ?? null) ? $sumber['Label'] : null,
            'Judul' => $sumber['Judul'],
            'Teks' => is_string($teks) ? $teks : null,
            'TombolUtama' => self::AmbilTombol($sumber['TombolUtama'] ?? null),
            'TombolKedua' => self::AmbilTombol($sumber['TombolKedua'] ?? null),
            'Gambar' => $gambar,
        ];
    }

    /**
     * Ringkasan teks blok setelah blok pertama sebagai HTML sederhana (untuk `<noscript>`).
     *
     * @param  list<array<string, mixed>>  $bagian
     */
    public static function SusunRingkasan(array $bagian): string
    {
        $html = '';

        foreach (array_slice($bagian, 1) as $blok) {
            if (is_array($blok)) {
                $html .= '<section>'.self::SusunNode($blok, 2).'</section>';
            }
        }

        return $html;
    }

    /** @return array{Label: string, Tautan: string}|null */
    private static function AmbilTombol(mixed $tombol): ?array
    {
        if (is_array($tombol) && is_string($tombol['Label'] ?? null) && is_string($tombol['Tautan'] ?? null) && $tombol['Label'] !== '') {
            return ['Label' => $tombol['Label'], 'Tautan' => $tombol['Tautan']];
        }

        return null;
    }

    /** @param  array<string, mixed>  $node */
    private static function SusunNode(array $node, int $tingkat): string
    {
        $html = '';

        foreach (self::KUNCI_JUDUL as $kunci) {
            if (is_string($node[$kunci] ?? null) && $node[$kunci] !== '') {
                $html .= self::H($node[$kunci], 'h'.min($tingkat, 4));
                break;
            }
        }

        foreach (self::KUNCI_PARAGRAF as $kunci) {
            if (is_string($node[$kunci] ?? null) && $node[$kunci] !== '') {
                $html .= '<p>'.e($node[$kunci]).'</p>';
            }
        }

        foreach (['Tombol', 'TombolUtama', 'TombolKedua'] as $kunci) {
            $tombol = self::AmbilTombol($node[$kunci] ?? null);

            if ($tombol !== null) {
                $html .= '<p><a href="'.e($tombol['Tautan']).'">'.e($tombol['Label']).'</a></p>';
            }
        }

        foreach (self::KUNCI_DAFTAR as $kunci) {
            if (! is_array($node[$kunci] ?? null)) {
                continue;
            }

            $butir = '';

            foreach ($node[$kunci] as $anak) {
                if (is_string($anak) && $anak !== '') {
                    $butir .= '<li>'.e($anak).'</li>';
                } elseif (is_array($anak)) {
                    $isi = self::SusunNode($anak, $tingkat + 1);
                    $butir .= $isi === '' ? '' : '<li>'.$isi.'</li>';
                }
            }

            $html .= $butir === '' ? '' : '<ul>'.$butir.'</ul>';
        }

        return $html;
    }

    private static function H(string $teks, string $tag): string
    {
        return "<{$tag}>".e($teks)."</{$tag}>";
    }
}
