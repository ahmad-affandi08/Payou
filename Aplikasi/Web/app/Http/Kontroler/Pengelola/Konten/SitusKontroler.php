<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Konten;

use App\Domain\Bersama\Web\AlamatDomain;
use App\Domain\Pengelola\Konten\Aksi\GandakanHalamanSitus;
use App\Domain\Pengelola\Konten\Aksi\HapusGambarSitus;
use App\Domain\Pengelola\Konten\Aksi\HapusHalamanSitus;
use App\Domain\Pengelola\Konten\Aksi\JadwalkanTerbitHalamanSitus;
use App\Domain\Pengelola\Konten\Aksi\PulihkanRevisiHalamanSitus;
use App\Domain\Pengelola\Konten\Aksi\SiapkanHalamanSitusBawaan;
use App\Domain\Pengelola\Konten\Aksi\SimpanHalamanSitus;
use App\Domain\Pengelola\Konten\Aksi\SimpanPengaturanSitus;
use App\Domain\Pengelola\Konten\Aksi\TerbitkanHalamanSitus;
use App\Domain\Pengelola\Konten\Aksi\UbahAktifHalamanSitus;
use App\Domain\Pengelola\Konten\Aksi\UbahGambarSitus;
use App\Domain\Pengelola\Konten\Aksi\UnggahGambarSitus;
use App\Domain\Pengelola\Konten\Kueri\DaftarKontenSitus;
use App\Domain\Pengelola\Konten\Kueri\PratinjauLangsungHalamanSitus;
use App\Domain\Pengelola\Konten\Layanan\TemplatHalamanSitus;
use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Domain\Situs\Kueri\PengaturanSitusBerlaku;
use App\Domain\Situs\Kueri\PenyusunHalamanSitus;
use App\Domain\Situs\Layanan\KontenSitusBawaan;
use App\Domain\Situs\Layanan\SkemaBagianSitus;
use App\Domain\Situs\Model\GambarSitus;
use App\Domain\Situs\Model\HalamanSitus;
use App\Domain\Situs\Model\RevisiHalamanSitus;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Konten\SimpanPengaturanSitusPermintaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * D-21 Situs pemasaran di konsol: pengaturan situs, halaman berblok (draf → terbit, pratinjau), dan pustaka gambar.
 * Lihat: `situs.lihat`; ubah, terbitkan, unggah: `situs.kelola`.
 */
final class SitusKontroler extends Kontroler
{
    /** Aturan simpan draf, dipakai simpan manual & simpan otomatis penyunting visual. */
    private const ATURAN_SIMPAN = [
        'Slug' => ['required', 'string', 'max:100'],
        'Judul' => ['required', 'string', 'max:150'],
        'JudulSeo' => ['nullable', 'string', 'max:70'],
        'DeskripsiSeo' => ['nullable', 'string', 'max:170'],
        'UuidGambarOg' => ['nullable', 'string', 'size:26'],
        'TampilDiSitemap' => ['boolean'],
        'Bagian' => ['present', 'array'],
    ];

    use PelakuPengelola;

    public function Pengaturan(PengaturanSitusBerlaku $pengaturan, DaftarKontenSitus $daftar): Response
    {
        return Inertia::render('Pengelola/Situs/Pengaturan', [
            'Pengaturan' => $pengaturan->Ambil(),
            'Gambar' => $daftar->AmbilGambar(),
            'PintasanTautan' => SkemaBagianSitus::PINTASAN_TAUTAN,
            'UrlSitus' => AlamatDomain::BuatUrlAbsolutPemasaran('/'),
            'Izin' => $this->AmbilIzin(),
        ]);
    }

    public function SimpanPengaturan(SimpanPengaturanSitusPermintaan $permintaan, SimpanPengaturanSitus $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilNilai());

        return back()->with('Kilat', 'Pengaturan situs disimpan dan langsung berlaku.');
    }

    public function DaftarHalaman(DaftarKontenSitus $daftar, SiapkanHalamanSitusBawaan $siapkan): Response
    {
        if ($this->AmbilIzin()['Kelola']) {
            $siapkan->Jalankan();
        }

        return Inertia::render('Pengelola/Situs/Halaman/Daftar', [
            'Halaman' => $daftar->AmbilHalaman(),
            'Templat' => collect(TemplatHalamanSitus::Daftar())->map(fn (array $t, string $kunci): array => ['Kunci' => $kunci, ...$t])->values()->all(),
            'Izin' => $this->AmbilIzin(),
        ]);
    }

    public function BuatHalaman(Request $permintaan, SimpanHalamanSitus $simpan): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Slug' => ['required', 'string', 'max:100'],
            'Judul' => ['required', 'string', 'max:150'],
            'Templat' => ['nullable', 'string', 'in:'.implode(',', array_keys(TemplatHalamanSitus::Daftar()))],
        ], attributes: ['Slug' => 'slug', 'Judul' => 'judul']);
        $halaman = $simpan->Jalankan($this->AmbilPelaku(), [
            'Slug' => (string) $valid['Slug'],
            'Judul' => (string) $valid['Judul'],
            'Bagian' => TemplatHalamanSitus::AmbilBagian((string) ($valid['Templat'] ?? TemplatHalamanSitus::KOSONG), (string) $valid['Judul']),
        ]);

        return redirect()->route('pengelola.situs.halaman.ubah', $halaman)->with('Kilat', 'Halaman dibuat sebagai draf. Susun bloknya lalu terbitkan.');
    }

    public function UbahHalaman(HalamanSitus $halamanSitus, DaftarKontenSitus $daftar, PengaturanSitusBerlaku $pengaturan): Response
    {
        return Inertia::render('Pengelola/Situs/Halaman/Ubah', [
            'Halaman' => [
                ...$daftar->PetakanHalaman($halamanSitus),
                'JudulSeo' => $halamanSitus->JudulSeo,
                'DeskripsiSeo' => $halamanSitus->DeskripsiSeo,
                'UuidGambarOg' => $halamanSitus->UuidGambarOg,
                'TampilDiSitemap' => $halamanSitus->TampilDiSitemap,
                'Bagian' => $halamanSitus->BagianDraf,
                'Bawaan' => array_key_exists($halamanSitus->Slug, KontenSitusBawaan::AmbilHalaman()),
            ],
            'Gambar' => $daftar->AmbilGambar(),
            'LabelBlok' => SkemaBagianSitus::LABEL,
            'Skema' => SkemaBagianSitus::AmbilSkema(),
            'Ikon' => SkemaBagianSitus::IKON,
            'PintasanTautan' => SkemaBagianSitus::PINTASAN_TAUTAN,
            'NamaSitus' => (string) ($pengaturan->Ambil()['NamaSitus'] ?? 'Payoung'),
            'AlamatSitus' => AlamatDomain::BuatUrlAbsolutPemasaran('/'),
            'JadwalTerbitPada' => $halamanSitus->JadwalTerbitPada?->toIso8601ZuluString(),
            'Revisi' => $daftar->AmbilRevisi($halamanSitus),
            'UrlPratinjauEditor' => route('pengelola.situs.halaman.kanvas', ['halamanSitus' => $halamanSitus->Uuid], false),
            'Izin' => $this->AmbilIzin(),
        ]);
    }

    public function SimpanHalaman(Request $permintaan, HalamanSitus $halamanSitus, SimpanHalamanSitus $simpan): RedirectResponse
    {
        $valid = $permintaan->validate(self::ATURAN_SIMPAN, attributes: ['JudulSeo' => 'judul SEO', 'DeskripsiSeo' => 'deskripsi SEO']);
        $simpan->Jalankan($this->AmbilPelaku(), $this->DataSimpan($permintaan, $valid), $halamanSitus);

        return back()->with('Kilat', 'Draf disimpan. Situs publik belum berubah sampai diterbitkan.');
    }

    /** Editor visual: pratinjau dari draf yang belum disimpan (tidak menulis apa pun). */
    public function PratinjauLangsung(Request $permintaan, HalamanSitus $halamanSitus, PratinjauLangsungHalamanSitus $kueri): JsonResponse
    {
        $data = $permintaan->validate([
            'Judul' => ['nullable', 'string', 'max:150'],
            'JudulSeo' => ['nullable', 'string', 'max:200'],
            'DeskripsiSeo' => ['nullable', 'string', 'max:400'],
            'UuidGambarOg' => ['nullable', 'string', 'max:26'],
            'Bagian' => ['nullable', 'array'],
        ]);

        return response()->json($kueri->Susun($halamanSitus, $data));
    }

    /** Editor visual: simpan draf otomatis; jawaban JSON (tanpa dialog hasil). Aturannya sama dengan simpan manual. */
    public function SimpanOtomatis(Request $permintaan, HalamanSitus $halamanSitus, SimpanHalamanSitus $simpan): JsonResponse
    {
        $valid = $permintaan->validate(self::ATURAN_SIMPAN, attributes: ['JudulSeo' => 'judul SEO', 'DeskripsiSeo' => 'deskripsi SEO']);
        $halaman = $simpan->Jalankan($this->AmbilPelaku(), $this->DataSimpan($permintaan, $valid), $halamanSitus);

        return response()->json(['DisimpanPada' => now()->toIso8601String(), 'AdaPerubahan' => $halaman->CekAdaPerubahan()]);
    }

    public function TerbitkanHalaman(HalamanSitus $halamanSitus, TerbitkanHalamanSitus $terbitkan): RedirectResponse
    {
        $terbitkan->Jalankan($this->AmbilPelaku(), $halamanSitus);

        return back()->with('Kilat', "Halaman {$halamanSitus->Judul} diterbitkan.");
    }

    public function GandakanHalaman(HalamanSitus $halamanSitus, GandakanHalamanSitus $gandakan): RedirectResponse
    {
        $baru = $gandakan->Jalankan($this->AmbilPelaku(), $halamanSitus);

        return redirect()->route('pengelola.situs.halaman.ubah', $baru)->with('Kilat', 'Salinan dibuat sebagai draf. Ubah slug & judulnya, lalu terbitkan.');
    }

    public function JadwalkanHalaman(Request $permintaan, HalamanSitus $halamanSitus, JadwalkanTerbitHalamanSitus $jadwalkan): RedirectResponse
    {
        $valid = $permintaan->validate(['JadwalTerbitPada' => ['nullable', 'date']]);
        $waktu = isset($valid['JadwalTerbitPada']) ? Carbon::parse((string) $valid['JadwalTerbitPada']) : null;
        $jadwalkan->Jalankan($this->AmbilPelaku(), $halamanSitus, $waktu);

        return back()->with('Kilat', $waktu === null ? 'Jadwal terbit dibatalkan.' : 'Terbit terjadwal. Draf pada saat itu yang akan diterbitkan.');
    }

    public function PulihkanRevisi(HalamanSitus $halamanSitus, RevisiHalamanSitus $revisiHalamanSitus, PulihkanRevisiHalamanSitus $pulihkan): RedirectResponse
    {
        $pulihkan->Jalankan($this->AmbilPelaku(), $halamanSitus, $revisiHalamanSitus);

        return back()->with('Kilat', 'Revisi dipulihkan ke draf. Situs publik belum berubah sampai diterbitkan.');
    }

    public function UbahAktifHalaman(Request $permintaan, HalamanSitus $halamanSitus, UbahAktifHalamanSitus $ubah): RedirectResponse
    {
        $aktif = (bool) $permintaan->validate(['Aktif' => ['required', 'boolean']])['Aktif'];
        $ubah->Jalankan($this->AmbilPelaku(), $halamanSitus, $aktif);

        return back()->with('Kilat', $aktif ? 'Halaman ditampilkan lagi.' : 'Halaman disembunyikan dari situs.');
    }

    public function HapusHalaman(HalamanSitus $halamanSitus, HapusHalamanSitus $hapus): RedirectResponse
    {
        $hapus->Jalankan($this->AmbilPelaku(), $halamanSitus);

        return redirect()->route('pengelola.situs.halaman.daftar')->with('Kilat', 'Halaman dihapus.');
    }

    /** Tautan pratinjau draf bertanda tangan di domain pemasaran (berlaku `situs.MenitPratinjau` menit). */
    /**
     * Kanvas editor visual (D-73): halaman situs dirender dengan bundle situs tetapi dilayani dari asal konsol, supaya
     * bingkainya sama-asal dengan editor (tanpa tanda tangan, kedaluwarsa, CSP lintas asal, atau cookie pihak ketiga).
     * Asal pesan diambil dari permintaan ini, bukan konfigurasi, sehingga tetap cocok di host mana pun konsol dibuka.
     */
    public function Kanvas(Request $permintaan, HalamanSitus $halamanSitus, PenyusunHalamanSitus $penyusun): Response
    {
        Inertia::setRootView('Situs');
        Inertia::share('Situs', fn (): array => $penyusun->AmbilDataBersama());
        Inertia::share('ProspekTerkirim', false);

        return Inertia::render('Situs/Halaman', [
            'Halaman' => [...$penyusun->AmbilDraf($halamanSitus), 'AsalEditor' => $permintaan->getSchemeAndHttpHost()],
        ]);
    }

    public function PratinjauHalaman(HalamanSitus $halamanSitus): RedirectResponse
    {
        return redirect()->away($this->BuatUrlPratinjau($halamanSitus, (int) config('situs.MenitPratinjau')));
    }

    public function DaftarGambar(DaftarKontenSitus $daftar): Response
    {
        return Inertia::render('Pengelola/Situs/Gambar', ['Gambar' => $daftar->AmbilGambar(), 'Izin' => $this->AmbilIzin()]);
    }

    public function UnggahGambar(Request $permintaan, UnggahGambarSitus $unggah): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Berkas' => ['required', 'file', 'max:'.(int) config('situs.UkuranGambarMaksKb'), 'mimetypes:'.implode(',', (array) config('situs.TipeGambar'))],
            'TeksAlternatif' => ['nullable', 'string', 'max:150'],
        ], attributes: ['Berkas' => 'gambar', 'TeksAlternatif' => 'teks alternatif']);
        $gambar = $unggah->Jalankan($this->AmbilPelaku(), $permintaan->file('Berkas'), $valid['TeksAlternatif'] ?? null);

        return back()->with('Kilat', "Gambar {$gambar->NamaBerkas} diunggah.");
    }

    public function UbahGambar(Request $permintaan, GambarSitus $gambarSitus, UbahGambarSitus $ubah): RedirectResponse
    {
        $valid = $permintaan->validate(['TeksAlternatif' => ['nullable', 'string', 'max:150']]);
        $ubah->Jalankan($this->AmbilPelaku(), $gambarSitus, $valid['TeksAlternatif'] ?? null);

        return back()->with('Kilat', 'Teks alternatif gambar disimpan.');
    }

    public function HapusGambar(GambarSitus $gambarSitus, HapusGambarSitus $hapus): RedirectResponse
    {
        $hapus->Jalankan($this->AmbilPelaku(), $gambarSitus);

        return back()->with('Kilat', 'Gambar dihapus.');
    }

    /** Tautan pratinjau draf bertanda tangan di domain pemasaran, berlaku [menit] menit. */
    private function BuatUrlPratinjau(HalamanSitus $halaman, int $menit): string
    {
        $relatif = URL::temporarySignedRoute('situs.pratinjau', now()->addMinutes($menit), ['halamanSitus' => $halaman->Uuid], false);

        return AlamatDomain::BuatUrlAbsolutPemasaran($relatif);
    }

    /**
     * @param  array<string, mixed>  $valid
     * @return array{Slug: string, Judul: string, JudulSeo?: string|null, DeskripsiSeo?: string|null, UuidGambarOg?: string|null, TampilDiSitemap?: bool, Bagian: mixed}
     */
    private function DataSimpan(Request $permintaan, array $valid): array
    {
        return [
            'Slug' => (string) $valid['Slug'],
            'Judul' => (string) $valid['Judul'],
            'JudulSeo' => $valid['JudulSeo'] ?? null,
            'DeskripsiSeo' => $valid['DeskripsiSeo'] ?? null,
            'UuidGambarOg' => $valid['UuidGambarOg'] ?? null,
            'TampilDiSitemap' => $permintaan->boolean('TampilDiSitemap', true),
            'Bagian' => $valid['Bagian'],
        ];
    }

    /**
     * @return array{Kelola: bool}
     */
    private function AmbilIzin(): array
    {
        return ['Kelola' => $this->AmbilPelaku()->PunyaIzin(IzinPengelola::SitusKelola)];
    }
}
