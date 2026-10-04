<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Domain\Bersama\Laporan\JenisKolom;
use App\Domain\Bersama\Laporan\KolomLaporan;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Kueri\RiwayatNomorSeri;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * F-05h riwayat nomor seri/IMEI (`/kelola/persediaan/kartu-stok/nomor-seri`, rumahnya di entri menu Kartu stok), izin
 * `persediaan.lihat`: cari potongan nomor atau nama/SKU produk (`?cari=`), saring status (`?status=`) atau satu produk
 * (`?produk={uuid}`), lalu pilih satu unit (`?unit={uuid}`) untuk melihat riwayatnya dari masuk sampai terjual atau
 * diretur. Satu hasil, atau nomor yang persis sama, langsung dibuka. Nomor yang tidak pernah bergerak di lokasi stok
 * outlet pelaku atau milik tenant lain = tidak ditemukan.
 *
 * Pembeli (nama & tautan) hanya tampil bagi yang berizin `pelanggan.lihat`, dan tautan penjualan/produk bagi yang
 * berizin laporan penjualan/produk: halaman ini untuk petugas gudang, jadi tidak boleh membocorkan data pelanggan.
 */
final class NomorSeriKontroler extends DasarPersediaanKontroler
{
    public function Tampilkan(Request $permintaan, RiwayatNomorSeri $riwayat, InfoProdukStok $infoProduk): Response|JsonResponse
    {
        $cari = trim($permintaan->string('cari')->toString());

        // Pencarian cepat (Ctrl+K): JSON `{Data}` seperti sumber TabelData lain, hanya mencocokkan nomor.
        if (ResponsTabel::MintaData($permintaan)) {
            return response()->json(['Data' => $this->SaringIzin($riwayat->Cari($cari, $this->IdOutletBoleh(), termasukProduk: false)['Baris'])]);
        }

        [$status, $uuidProduk] = $this->BacaSaring($permintaan);
        $uuid = trim($permintaan->string('unit')->toString());
        $hasil = $riwayat->Cari($cari, $this->IdOutletBoleh(), $status, $uuidProduk);
        $baris = $this->SaringIzin($hasil['Baris']);

        // Tanpa pilihan eksplisit: nomor yang persis sama (abaikan huruf besar/kecil) atau satu-satunya hasil langsung dibuka.
        if ($uuid === '' && $baris !== []) {
            $persis = array_values(array_filter($baris, fn (array $b): bool => mb_strtolower((string) $b['Nomor']) === mb_strtolower($cari)));
            $uuid = count($persis) === 1 ? (string) $persis[0]['Uuid'] : (count($baris) === 1 ? (string) $baris[0]['Uuid'] : '');
        }

        $detail = $uuid === '' ? null : $riwayat->Ambil($uuid, $this->IdOutletBoleh());

        if ($detail !== null) {
            $detail['Unit'] = $this->SaringIzin([$detail['Unit']])[0];
        }

        $produk = $uuidProduk === null ? null : ($infoProduk->AmbilDariUuid([$uuidProduk])[$uuidProduk] ?? null);

        return Inertia::render('Kelola/Persediaan/NomorSeri', [
            'Saring' => ['Cari' => $cari, 'Status' => $status ?? '', 'Produk' => $uuidProduk ?? '', 'Unit' => $detail === null ? '' : $uuid],
            'Produk' => $produk === null ? null : ['Uuid' => $produk->uuid, 'Nama' => $produk->nama, 'Sku' => $produk->sku],
            'Hasil' => $baris,
            'TotalHasil' => $hasil['Total'],
            'Detail' => $detail,
            'BatasHasil' => RiwayatNomorSeri::BATAS_HASIL,
            'OpsiStatus' => array_map(fn (StatusNomorSeri $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusNomorSeri::cases()),
            'Izin' => $this->AmbilIzinTautan(),
        ]);
    }

    /** Unduh hasil pencarian sesuai saring halaman (sampai `BATAS_EKSPOR` baris). */
    public function Ekspor(Request $permintaan, RiwayatNomorSeri $riwayat): SymfonyResponse
    {
        [$status, $uuidProduk] = $this->BacaSaring($permintaan);
        $baris = $this->SaringIzin($riwayat->Cari(trim($permintaan->string('cari')->toString()), $this->IdOutletBoleh(), $status, $uuidProduk, RiwayatNomorSeri::BATAS_EKSPOR)['Baris']);
        $kolom = [
            new KolomLaporan('Nomor seri', JenisKolom::Teks, 24), new KolomLaporan('Produk', JenisKolom::Teks, 30), new KolomLaporan('SKU'),
            new KolomLaporan('Status', JenisKolom::Teks, 14), new KolomLaporan('Lokasi stok'), new KolomLaporan('Nomor penjualan'),
            new KolomLaporan('Tanggal jual', JenisKolom::Teks, 14), new KolomLaporan('Pembeli'), new KolomLaporan('Garansi sampai', JenisKolom::Teks, 14),
        ];
        $isi = array_map(fn (array $b): array => [
            $b['Nomor'], $b['NamaProduk'], $b['Sku'], $b['LabelStatus'], $b['NamaGudang'], $b['NomorPenjualan'], $b['TanggalJual'], $b['NamaPelanggan'], $b['GaransiSampai'],
        ], $baris);

        return $this->SajikanLaporan($permintaan, 'Riwayat Nomor Seri', 'nomor-seri', $kolom, $isi);
    }

    /** Alamat lama sebelum riwayat nomor seri pindah ke bawah Kartu stok; tautan tersimpan & tombol lama tetap bekerja. */
    public function Alihkan(Request $permintaan): RedirectResponse
    {
        $query = $permintaan->getQueryString();

        return redirect('/kelola/persediaan/kartu-stok/nomor-seri'.($query === null || $query === '' ? '' : "?{$query}"), 301);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function BacaSaring(Request $permintaan): array
    {
        $status = trim($permintaan->string('status')->toString());
        $produk = trim($permintaan->string('produk')->toString());

        return [StatusNomorSeri::tryFrom($status)?->value, $produk === '' ? null : $produk];
    }

    /** @return array{Pelanggan: bool, Penjualan: bool, Produk: bool} */
    private function AmbilIzinTautan(): array
    {
        return [
            'Pelanggan' => $this->CekIzin(IzinTenant::PelangganLihat),
            'Penjualan' => $this->CekIzin(IzinTenant::LaporanPenjualanLihat),
            'Produk' => $this->CekIzin(IzinTenant::ProdukLihat),
        ];
    }

    /**
     * Menghapus pembeli (nama & uuid) dan uuid penjualan bila pelaku tidak berhak melihatnya.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private function SaringIzin(array $baris): array
    {
        $izin = $this->AmbilIzinTautan();

        return array_map(function (array $b) use ($izin): array {
            if (! $izin['Pelanggan']) {
                $b['NamaPelanggan'] = null;
                $b['UuidPelanggan'] = null;
            }

            if (! $izin['Penjualan']) {
                $b['UuidPenjualan'] = null;
            }

            return $b;
        }, $baris);
    }
}
