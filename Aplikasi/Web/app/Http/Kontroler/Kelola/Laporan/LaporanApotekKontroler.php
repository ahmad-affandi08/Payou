<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Laporan;

use App\Domain\Bersama\Laporan\JenisKolom;
use App\Domain\Bersama\Laporan\KolomLaporan;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Laporan\Kueri\LaporanApotek;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Penjualan\Kueri\DaftarPenjualanObatResep;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Laporan apotek (Sektor Apotek bagian 1, PRD §9.5; `/kelola/laporan/apotek`, izin `laporan.penjualan.lihat`, outlet
 * akses pelaku):
 * - penjualan obat keras, psikotropika & narkotika (TabelData mode server + ekspor CSV sesuai saringan); nama & alamat
 *   pasien utuh hanya untuk pemegang `apotek.resep.lihat`, selain itu disamarkan;
 * - data pendukung SIPNAP per bulan per produk psikotropika/narkotika dari ledger stok + ekspor CSV.
 * Obat mendekati kedaluwarsa sudah ada di Laporan stok › Kedaluwarsa (F-05g).
 */
final class LaporanApotekKontroler extends DasarKelolaKontroler
{
    public function Halaman(Request $permintaan, DaftarPenjualanObatResep $daftar, LaporanApotek $laporan, PetaUuidOutlet $outlet, TanggalBisnisOutlet $tanggal): Response|JsonResponse
    {
        $tabel = self::BacaTabel($permintaan);
        [$bulan, $uuidOutlet, $idOutlet] = $this->BacaSaringSipnap($permintaan, $outlet, $tanggal->Hitung(null));
        $tab = $permintaan->query('tab') === 'sipnap' ? 'sipnap' : 'resep';

        return ResponsTabel::Kirim($permintaan, 'Kelola/Laporan/Apotek', 'Penjualan', fn (): array => $daftar->Ambil($tabel, $this->IdOutletBoleh(), $this->CekLihatPasien()), fn (): array => [
            'Tab' => $tab,
            'OpsiGolongan' => DaftarPenjualanObatResep::OpsiGolongan(),
            'LihatPasien' => $this->CekLihatPasien(),
            'Sipnap' => [
                'Bulan' => $bulan->format('Y-m'),
                'Outlet' => $uuidOutlet,
                'Baris' => $tab === 'sipnap' ? $laporan->DataSipnap($bulan, $idOutlet) : [],
            ],
            'OpsiOutlet' => array_map(fn (array $o): array => ['Nilai' => $o['Uuid'], 'Label' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh())),
        ]);
    }

    /** Ekspor penjualan obat wajib resep sesuai saringan & cari tabel (maks. 5.000 baris terbaru). */
    public function EksporResep(Request $permintaan, DaftarPenjualanObatResep $daftar): SymfonyResponse
    {
        $baris = $daftar->AmbilSemua(self::BacaTabel($permintaan), $this->IdOutletBoleh(), $this->CekLihatPasien());
        $kolom = [
            new KolomLaporan('Tanggal'), new KolomLaporan('Nomor penjualan'), new KolomLaporan('Status', JenisKolom::Teks, 14),
            new KolomLaporan('Produk', JenisKolom::Teks, 30), new KolomLaporan('Golongan', JenisKolom::Teks, 18),
            new KolomLaporan('Obat Wajib Apotek', JenisKolom::Teks, 12), new KolomLaporan('Jumlah', JenisKolom::Kuantitas, 10),
            new KolomLaporan('Satuan', JenisKolom::Teks, 10), new KolomLaporan('Batch', JenisKolom::Teks, 16),
            new KolomLaporan('Dengan resep', JenisKolom::Teks, 12), new KolomLaporan('Nomor resep'), new KolomLaporan('Tanggal resep'),
            new KolomLaporan('Dokter'), new KolomLaporan('No. SIP dokter'), new KolomLaporan('Pasien'),
            new KolomLaporan('Umur pasien', JenisKolom::Teks, 12), new KolomLaporan('Alamat pasien', JenisKolom::Teks, 32),
            new KolomLaporan('Apoteker'), new KolomLaporan('Kasir'),
        ];
        $isi = array_map(fn (array $b): array => [
            self::Teks($b['Tanggal']), self::Teks($b['Nomor']), self::Teks($b['Status']), self::Teks($b['NamaProduk']),
            self::Teks($b['LabelGolongan']), $b['ObatWajibApotek'] === true ? 'Ya' : 'Tidak', self::Teks($b['Jumlah']),
            self::Teks($b['SimbolSatuan']), self::Teks($b['Batch']), $b['DenganResep'] === true ? 'Ya' : 'Tidak',
            self::Teks($b['NomorResep']), self::Teks($b['TanggalResep']), self::Teks($b['NamaDokter']), self::Teks($b['NoSipDokter']),
            self::Teks($b['NamaPasien']), self::Teks($b['UmurPasien']), self::Teks($b['AlamatPasien']), self::Teks($b['NamaApoteker']),
            self::Teks($b['NamaKasir']),
        ], $baris);

        return $this->SajikanLaporan($permintaan, 'Laporan Obat Wajib Resep', 'laporan-obat-wajib-resep', $kolom, $isi);
    }

    /** Ekspor data pendukung SIPNAP satu bulan (bukan laporan resmi; diisikan apoteker ke SIPNAP). */
    public function EksporSipnap(Request $permintaan, LaporanApotek $laporan, PetaUuidOutlet $outlet, TanggalBisnisOutlet $tanggal): SymfonyResponse
    {
        [$bulan, , $idOutlet] = $this->BacaSaringSipnap($permintaan, $outlet, $tanggal->Hitung(null));
        $kolom = [
            new KolomLaporan('Bulan', JenisKolom::Teks, 10), new KolomLaporan('Kode produk (SKU)'), new KolomLaporan('Nama obat', JenisKolom::Teks, 30),
            new KolomLaporan('Golongan', JenisKolom::Teks, 18), new KolomLaporan('Prekursor', JenisKolom::Teks, 11), new KolomLaporan('Satuan', JenisKolom::Teks, 10),
            new KolomLaporan('Stok awal', JenisKolom::Kuantitas), new KolomLaporan('Pemasukan dari pemasok', JenisKolom::Kuantitas),
            new KolomLaporan('Pemasukan lain', JenisKolom::Kuantitas), new KolomLaporan('Pengeluaran penjualan', JenisKolom::Kuantitas),
            new KolomLaporan('Pengeluaran lain', JenisKolom::Kuantitas), new KolomLaporan('Stok akhir', JenisKolom::Kuantitas),
        ];
        $isi = array_map(fn (array $b): array => [
            $bulan->format('Y-m'), $b['Sku'] ?? '', $b['NamaProduk'], $b['LabelGolongan'], $b['Prekursor'] ? 'Ya' : 'Tidak', $b['SimbolSatuan'],
            $b['StokAwal'], $b['PemasukanPemasok'], $b['PemasukanLain'], $b['PengeluaranPenjualan'], $b['PengeluaranLain'], $b['StokAkhir'],
        ], $laporan->DataSipnap($bulan, $idOutlet));

        return $this->SajikanLaporan(
            $permintaan,
            'Data Pendukung SIPNAP',
            "data-pendukung-sipnap-{$bulan->format('Y-m')}",
            $kolom,
            $isi,
            [['Periode', $this->LabelPeriode($bulan, $bulan->endOfMonth())]],
        );
    }

    private function CekLihatPasien(): bool
    {
        return app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::ApotekResepLihat);
    }

    private static function BacaTabel(Request $permintaan): DataPermintaanTabel
    {
        return DataPermintaanTabel::Dari($permintaan->query(), DaftarPenjualanObatResep::KOLOM_URUT, DaftarPenjualanObatResep::URUT_BAWAAN, DaftarPenjualanObatResep::KOLOM_SARING);
    }

    /**
     * Bulan `YYYY-MM` (bawaan bulan berjalan, tidak boleh setelahnya) dan outlet opsional dalam akses pelaku.
     *
     * @return array{0: CarbonImmutable, 1: string, 2: list<int>|null}
     */
    private function BacaSaringSipnap(Request $permintaan, PetaUuidOutlet $outlet, CarbonImmutable $hariIni): array
    {
        $teks = $permintaan->query('bulan');
        $bulan = is_string($teks) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $teks) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $teks) ?: $hariIni->startOfMonth()
            : $hariIni->startOfMonth();
        $bulan = $bulan->greaterThan($hariIni) ? $hariIni->startOfMonth() : $bulan->startOfMonth();
        $uuidOutlet = is_string($permintaan->query('outlet')) ? $permintaan->query('outlet') : '';
        $boleh = $this->IdOutletBoleh();
        $idOutlet = $boleh;

        if ($uuidOutlet !== '') {
            $id = $outlet->AmbilIdDariUuid([$uuidOutlet])[$uuidOutlet] ?? null;
            $idOutlet = $id !== null && ($boleh === null || in_array($id, $boleh, true)) ? [$id] : [];
        }

        return [$bulan, $uuidOutlet, $idOutlet];
    }

    private static function Teks(mixed $nilai): string
    {
        return is_string($nilai) ? $nilai : '';
    }
}
