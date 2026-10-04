<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Karyawan;

use App\Domain\Bersama\Laporan\JenisKolom;
use App\Domain\Bersama\Laporan\KolomLaporan;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Karyawan\Aksi\SimpanAturanKomisi;
use App\Domain\Karyawan\Aksi\UbahStatusAturanKomisi;
use App\Domain\Karyawan\Data\DataAturanKomisi;
use App\Domain\Karyawan\Enum\CakupanKomisi;
use App\Domain\Karyawan\Enum\JenisKomisi;
use App\Domain\Karyawan\Enum\StatusAturanKomisi;
use App\Domain\Karyawan\Kueri\DaftarAturanKomisi;
use App\Domain\Karyawan\Kueri\LaporanKomisi;
use App\Domain\Karyawan\Model\AturanKomisi;
use App\Domain\Katalog\Kueri\PohonKategori;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Komisi (F-18, EMP-04): aturan komisi (`/kelola/karyawan/komisi`, tambah di halaman penuh `/buat`, ubah/arsip) dan laporan komisi per karyawan
 * (`/kelola/karyawan/komisi/laporan`). Lihat `karyawan.lihat`; ubah aturan `karyawan.kelola`.
 */
final class KomisiKontroler extends DasarKelolaKontroler
{
    public function Aturan(DaftarAturanKomisi $daftar, PohonKategori $kategori): Response
    {
        return Inertia::render('Kelola/Karyawan/Komisi', [
            'Aturan' => $daftar->Ambil(),
            'OpsiKategori' => array_map(fn (array $k): array => ['Uuid' => $k['Uuid'], 'Nama' => $k['Jalur']], $kategori->AmbilOpsi()),
            'Izin' => ['Kelola' => app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::KaryawanKelola)],
        ]);
    }

    /** Halaman penuh "Tambah aturan komisi" (pola sama dengan Tambah produk). Hanya untuk `karyawan.kelola`. */
    public function Buat(PohonKategori $kategori): Response
    {
        return Inertia::render('Kelola/Karyawan/BuatAturanKomisi', [
            'OpsiKategori' => array_map(fn (array $k): array => ['Uuid' => $k['Uuid'], 'Nama' => $k['Jalur']], $kategori->AmbilOpsi()),
        ]);
    }

    public function Simpan(Request $permintaan, SimpanAturanKomisi $simpan): RedirectResponse
    {
        $a = $simpan->Jalankan($this->AmbilData($permintaan));

        return to_route('kelola.karyawan.komisi')->with('Kilat', "Aturan komisi {$a->Nama} ditambahkan.");
    }

    public function Perbarui(Request $permintaan, string $aturan, SimpanAturanKomisi $simpan): RedirectResponse
    {
        $a = $simpan->Jalankan($this->AmbilData($permintaan), $this->Cari($aturan));

        return back()->with('Kilat', "Aturan komisi {$a->Nama} disimpan. Berlaku untuk penjualan berikutnya.");
    }

    public function Arsipkan(string $aturan, UbahStatusAturanKomisi $ubah): RedirectResponse
    {
        $a = $ubah->Jalankan($this->Cari($aturan), StatusAturanKomisi::Diarsipkan, $this->Pelaku()->Id);

        return back()->with('Kilat', "Aturan komisi {$a->Nama} diarsipkan.");
    }

    public function Pulihkan(string $aturan, UbahStatusAturanKomisi $ubah): RedirectResponse
    {
        $a = $ubah->Jalankan($this->Cari($aturan), StatusAturanKomisi::Aktif, $this->Pelaku()->Id);

        return back()->with('Kilat', "Aturan komisi {$a->Nama} aktif kembali.");
    }

    public function Laporan(Request $permintaan, LaporanKomisi $laporan, PetaUuidOutlet $outlet): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), LaporanKomisi::KOLOM_URUT, LaporanKomisi::URUT_BAWAAN, LaporanKomisi::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Karyawan/LaporanKomisi', 'Komisi', fn (): array => $laporan->Ambil($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh())),
        ]);
    }

    /** Ekspor laporan komisi per karyawan sesuai saringan & cari yang aktif di tabel. */
    public function EksporLaporan(Request $permintaan, LaporanKomisi $laporan): SymfonyResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), LaporanKomisi::KOLOM_URUT, LaporanKomisi::URUT_BAWAAN, LaporanKomisi::KOLOM_SARING);
        $idOutlet = $this->IdOutletBoleh();
        $baris = $this->AmbilSemuaBarisTabel($tabel, fn (DataPermintaanTabel $t): array => $laporan->Ambil($t, $idOutlet));
        $kolom = [
            new KolomLaporan('Karyawan', JenisKolom::Teks, 28), new KolomLaporan('Jabatan', JenisKolom::Teks, 20),
            new KolomLaporan('Baris dilayani', JenisKolom::Bilangan, jumlahkan: true), new KolomLaporan('Dasar komisi', JenisKolom::Uang, jumlahkan: true),
            new KolomLaporan('Komisi kotor', JenisKolom::Uang, jumlahkan: true), new KolomLaporan('Dibatalkan', JenisKolom::Uang, jumlahkan: true),
            new KolomLaporan('Komisi bersih', JenisKolom::Uang, jumlahkan: true),
        ];
        $isi = array_map(fn (array $b): array => [$b['Nama'], $b['Jabatan'] ?? '', $b['JumlahBaris'], $b['TotalDasar'], $b['Kotor'], $b['Dibatalkan'], $b['Bersih']], $baris);

        return $this->SajikanLaporan($permintaan, 'Laporan Komisi', 'laporan-komisi', $kolom, $isi);
    }

    private function AmbilData(Request $permintaan): DataAturanKomisi
    {
        $valid = $permintaan->validate([
            'Nama' => ['required', 'string', 'max:100'],
            'Cakupan' => ['required', Rule::enum(CakupanKomisi::class)],
            'UuidProduk' => ['nullable', 'string', 'ulid'],
            'UuidKategori' => ['nullable', 'string', 'ulid'],
            'LevelStaf' => ['nullable', 'string', 'max:40'],
            'Jenis' => ['required', Rule::enum(JenisKomisi::class)],
            'Nilai' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
        ], ['Nilai.regex' => 'Nilai harus angka dengan pemisah desimal titik (maks. 2 desimal).'], ['Nama' => 'nama', 'Nilai' => 'nilai', 'LevelStaf' => 'level staf']);

        return new DataAturanKomisi(
            nama: (string) $valid['Nama'],
            cakupan: CakupanKomisi::from((string) $valid['Cakupan']),
            uuidProduk: is_string($valid['UuidProduk'] ?? null) ? $valid['UuidProduk'] : null,
            uuidKategori: is_string($valid['UuidKategori'] ?? null) ? $valid['UuidKategori'] : null,
            levelStaf: is_string($valid['LevelStaf'] ?? null) ? $valid['LevelStaf'] : null,
            jenis: JenisKomisi::from((string) $valid['Jenis']),
            nilai: Uang::Dari((string) $valid['Nilai']),
            idPengguna: $this->Pelaku()->Id,
        );
    }

    private function Cari(string $uuid): AturanKomisi
    {
        return AturanKomisi::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
