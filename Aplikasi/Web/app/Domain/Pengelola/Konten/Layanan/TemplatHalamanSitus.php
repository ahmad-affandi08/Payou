<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Layanan;

/**
 * D-63: kerangka halaman siap pakai untuk "Halaman baru". Isinya teks contoh yang jelas perlu diganti pengelola; tidak
 * ada angka, testimoni, atau klaim karangan. Semua blok lolos `ValidatorBagianSitus` (dijaga test).
 */
final class TemplatHalamanSitus
{
    public const KOSONG = 'Kosong';

    /**
     * @return array<string, array{Nama: string, Deskripsi: string}>
     */
    public static function Daftar(): array
    {
        return [
            'Kosong' => ['Nama' => 'Kosong', 'Deskripsi' => 'Hanya satu blok pembuka. Susun sendiri dari awal.'],
            'Solusi' => ['Nama' => 'Halaman solusi / jenis usaha', 'Deskripsi' => 'Pembuka, tiga keunggulan, tanya jawab, dan ajakan daftar.'],
            'Harga' => ['Nama' => 'Halaman harga', 'Deskripsi' => 'Pembuka, harga paket otomatis dari konsol, tanya jawab, dan ajakan.'],
            'Kontak' => ['Nama' => 'Halaman kontak & demo', 'Deskripsi' => 'Kontak, formulir minta demo, dan unduh aplikasi.'],
            'Teks' => ['Nama' => 'Halaman teks (tentang, kebijakan)', 'Deskripsi' => 'Judul dan satu blok teks bebas.'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function AmbilBagian(string $kunci, string $judul): array
    {
        $cta = [
            'Jenis' => 'Cta',
            'Judul' => 'Siap mencoba?',
            'Teks' => 'Daftar gratis lalu mulai jual hari itu juga.',
            'TombolUtama' => ['Label' => 'Coba gratis', 'Tautan' => '@daftar'],
        ];
        $hero = [
            'Jenis' => 'Hero',
            'Judul' => $judul,
            'Subjudul' => 'Tulis satu atau dua kalimat yang menjelaskan manfaat halaman ini.',
            'TombolUtama' => ['Label' => 'Coba gratis', 'Tautan' => '@daftar'],
        ];

        return match ($kunci) {
            'Solusi' => [
                $hero,
                [
                    'Jenis' => 'Keunggulan',
                    'Judul' => 'Yang terbantu dengan Payoung',
                    'Item' => [
                        ['Ikon' => 'WifiOff', 'Judul' => 'Keunggulan pertama', 'Teks' => 'Jelaskan satu manfaat nyata untuk jenis usaha ini.'],
                        ['Ikon' => 'Boxes', 'Judul' => 'Keunggulan kedua', 'Teks' => 'Jelaskan satu manfaat nyata untuk jenis usaha ini.'],
                        ['Ikon' => 'Landmark', 'Judul' => 'Keunggulan ketiga', 'Teks' => 'Jelaskan satu manfaat nyata untuk jenis usaha ini.'],
                    ],
                ],
                [
                    'Jenis' => 'Faq',
                    'Judul' => 'Pertanyaan yang sering muncul',
                    'Item' => [['Pertanyaan' => 'Tulis pertanyaan calon pelanggan.', 'Jawaban' => 'Tulis jawabannya di sini.']],
                ],
                $cta,
            ],
            'Harga' => [
                $hero,
                ['Jenis' => 'Harga', 'Judul' => 'Pilih paket', 'TampilkanTahunan' => true, 'TeksTombol' => 'Mulai'],
                [
                    'Jenis' => 'Faq',
                    'Judul' => 'Soal harga',
                    'Item' => [['Pertanyaan' => 'Tulis pertanyaan soal harga.', 'Jawaban' => 'Tulis jawabannya di sini.']],
                ],
                $cta,
            ],
            'Kontak' => [
                ['Jenis' => 'Kontak', 'Judul' => 'Hubungi kami', 'Subjudul' => 'Untuk demo, pertanyaan paket, dan bantuan.'],
                ['Jenis' => 'FormulirProspek', 'Judul' => 'Minta demo gratis', 'JenisProspek' => 'Demo', 'TeksTombol' => 'Kirim permintaan demo'],
                ['Jenis' => 'UnduhAplikasi', 'Judul' => 'Unduh aplikasi Payoung'],
            ],
            'Teks' => [
                ['Jenis' => 'TeksBebas', 'Judul' => $judul, 'Isi' => "Tulis isi halaman di sini.\n\n## Subjudul\nParagraf berikutnya."],
            ],
            default => [['Jenis' => 'Hero', 'Judul' => $judul]],
        };
    }
}
