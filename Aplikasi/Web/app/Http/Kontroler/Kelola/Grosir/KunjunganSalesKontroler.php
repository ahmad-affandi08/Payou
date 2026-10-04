<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Grosir;

use App\Domain\Bersama\Laporan\JenisKolom;
use App\Domain\Bersama\Laporan\KolomLaporan;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Penjualan\Kueri\DaftarKunjunganSales;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Kunjungan salesman (Modul Salesman bagian 1, §9.7, Grosir › Kunjungan, `/kelola/grosir/kunjungan`): daftar
 * kunjungan dari aplikasi salesman (hanya baca; catatan lapangan tidak diubah dari back-office) dan ekspor CSV dengan
 * saringan yang sama. Izin `grosir.kelola` dijaga rute; batas outlet pelaku tetap berlaku.
 */
final class KunjunganSalesKontroler extends DasarGrosirKontroler
{
    public function Daftar(Request $permintaan, DaftarKunjunganSales $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarKunjunganSales::KOLOM_URUT, DaftarKunjunganSales::URUT_BAWAAN, DaftarKunjunganSales::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Grosir/Kunjungan/Daftar', 'Kunjungan', fn (): array => $daftar->Ambil($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiSalesman' => $daftar->OpsiSalesman(),
            'OpsiHasil' => DaftarKunjunganSales::OpsiHasil(),
            'Izin' => $this->AmbilIzinGrosir(),
        ]);
    }

    /** Ekspor sesuai saringan & cari yang aktif di tabel (maks. 5.000 baris terbaru). Koordinat desimal bertitik. */
    public function Ekspor(Request $permintaan, DaftarKunjunganSales $daftar): SymfonyResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarKunjunganSales::KOLOM_URUT, DaftarKunjunganSales::URUT_BAWAAN, DaftarKunjunganSales::KOLOM_SARING);
        $kolom = [
            new KolomLaporan('Tanggal', JenisKolom::Teks, 14), new KolomLaporan('Masuk (UTC)', JenisKolom::Teks, 22), new KolomLaporan('Keluar (UTC)', JenisKolom::Teks, 22),
            new KolomLaporan('Durasi (menit)', JenisKolom::Bilangan), new KolomLaporan('Salesman'), new KolomLaporan('Pelanggan'),
            new KolomLaporan('Hasil', JenisKolom::Teks, 18), new KolomLaporan('Pesanan'), new KolomLaporan('Latitude', JenisKolom::Teks, 14),
            new KolomLaporan('Longitude', JenisKolom::Teks, 14), new KolomLaporan('Akurasi (m)', JenisKolom::Bilangan), new KolomLaporan('Catatan', JenisKolom::Teks, 30),
        ];
        $isi = array_map(fn (array $k): array => [
            (string) $k['Tanggal'],
            (string) $k['MasukPada'],
            is_string($k['KeluarPada']) ? $k['KeluarPada'] : '',
            is_int($k['DurasiMenit']) ? $k['DurasiMenit'] : null,
            (string) $k['NamaSalesman'],
            (string) $k['NamaPelanggan'],
            (string) $k['LabelHasil'],
            is_string($k['NomorPesananGrosir']) ? $k['NomorPesananGrosir'] : '',
            is_string($k['Latitude']) ? $k['Latitude'] : '',
            is_string($k['Longitude']) ? $k['Longitude'] : '',
            is_int($k['AkurasiMeter']) ? $k['AkurasiMeter'] : null,
            is_string($k['Catatan']) ? $k['Catatan'] : '',
        ], $daftar->AmbilSemua($tabel, $this->IdOutletBoleh()));

        return $this->SajikanLaporan($permintaan, 'Laporan Kunjungan Salesman', 'kunjungan-salesman', $kolom, $isi);
    }
}
