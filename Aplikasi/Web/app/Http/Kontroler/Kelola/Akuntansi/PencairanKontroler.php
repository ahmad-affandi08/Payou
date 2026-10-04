<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Laporan\JenisKolom;
use App\Domain\Bersama\Laporan\KolomLaporan;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Penjualan\Aksi\BatalkanPencairan;
use App\Domain\Penjualan\Aksi\BuatPencairan;
use App\Domain\Penjualan\Kueri\DaftarPencairan;
use App\Domain\Penjualan\Kueri\DetailPencairan;
use App\Domain\Penjualan\Kueri\PembayaranBelumDicairkan;
use App\Domain\Penjualan\Model\Pencairan;
use App\Http\Permintaan\Kelola\Akuntansi\BatalkanPencairanPermintaan;
use App\Http\Permintaan\Kelola\Akuntansi\BuatPencairanPermintaan;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Pencairan dana non-tunai (F-08, BR-08.4, J-08.1, `/kelola/akuntansi/pencairan`): lihat `laporan.keuangan.lihat`,
 * catat & batalkan `akuntansi.kelola` — sama seperti transaksi kas & bank, karena dampaknya sejenis (uang masuk
 * rekening dan beban baru di laba-rugi).
 *
 * Halaman daftar juga menampilkan **ringkasan yang belum dicairkan** per metode: itu isi akun kliring yang masih
 * menunggu uang, dan satu-satunya tempat di produk ini yang bisa menjawab "uang mana yang belum sampai?".
 */
final class PencairanKontroler extends DasarAkuntansiKontroler
{
    public function Daftar(Request $permintaan, DaftarPencairan $daftar, PembayaranBelumDicairkan $belum): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPencairan::KOLOM_URUT, DaftarPencairan::URUT_BAWAAN, DaftarPencairan::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Akuntansi/Pencairan/Daftar', 'Pencairan', fn (): array => $daftar->Ambil($tabel, $this->IdOutletBoleh()), fn (): array => [
            'BelumDicairkan' => $belum->Ringkasan($this->IdOutletBoleh()),
            'OpsiMetode' => $belum->Metode(),
            'OpsiStatus' => array_map(
                fn (StatusDokumenTerposting $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()],
                StatusDokumenTerposting::cases(),
            ),
            'Izin' => ['Kelola' => $this->CekIzinKelola()],
        ]);
    }

    /**
     * Ekspor rekap potongan platform untuk **saringan yang sama** dengan daftar (tanggal, metode, status dibaca dari
     * query yang sama), supaya angka yang dibawa ke platform persis angka di layar.
     */
    public function EksporRekapPotongan(Request $permintaan, DaftarPencairan $daftar): SymfonyResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPencairan::KOLOM_URUT, DaftarPencairan::URUT_BAWAAN, DaftarPencairan::KOLOM_SARING);
        $rekap = $daftar->RekapPotongan($tabel, $this->IdOutletBoleh());
        $kolom = [
            new KolomLaporan('Metode', JenisKolom::Teks, 28), new KolomLaporan('Jumlah pencairan', JenisKolom::Bilangan, jumlahkan: true),
            new KolomLaporan('Diserahkan', JenisKolom::Uang, jumlahkan: true), new KolomLaporan('Dipotong', JenisKolom::Uang, jumlahkan: true),
            new KolomLaporan('Perkiraan potongan', JenisKolom::Uang, jumlahkan: true), new KolomLaporan('Selisih', JenisKolom::Uang, jumlahkan: true),
            new KolomLaporan('Persen efektif', JenisKolom::Persen),
        ];
        $isi = array_map(fn (array $r): array => [$r['Nama'], (string) $r['Jumlah'], $r['JumlahKotor'], $r['Biaya'], $r['BiayaDiharapkan'], $r['Selisih'], $r['PersenEfektif']], $rekap);

        return $this->SajikanLaporan($permintaan, 'Rekap Potongan Pencairan', 'rekap-potongan-pencairan', $kolom, $isi);
    }

    /**
     * Formulir pencairan. Daftar pembayaran yang bisa dicairkan **hanya** datang dari server (metode + outlet + batas
     * tanggal), supaya yang bisa dipilih operator persis isi akun kliring saat itu.
     */
    public function Buat(Request $permintaan, PembayaranBelumDicairkan $belum, DaftarAkunPilihan $akun): Response
    {
        $uuidMetode = $permintaan->query('metode');
        $uuidOutlet = $permintaan->query('outlet');
        $sampai = $permintaan->query('sampai');
        $opsiOutlet = $this->AmbilOpsiOutlet();
        // Tautan dari Kotak Tindakan hanya membawa metodenya. Kalau tokonya cuma punya satu outlet, memilihnya sendiri
        // membuat daftarnya langsung terisi alih-alih memaksa operator memilih hal yang tidak punya pilihan lain.
        $uuidOutlet = is_string($uuidOutlet) && $uuidOutlet !== ''
            ? $uuidOutlet
            : (count($opsiOutlet) === 1 ? $opsiOutlet[0]['Uuid'] : '');
        $outlet = $uuidOutlet === '' ? null : $this->CariOutlet($uuidOutlet);

        return Inertia::render('Kelola/Akuntansi/Pencairan/Buat', [
            'OpsiMetode' => $belum->Metode(),
            'OpsiOutlet' => $opsiOutlet,
            'OpsiAkun' => $akun->AmbilKasBank(),
            'Terpilih' => [
                'Metode' => is_string($uuidMetode) ? $uuidMetode : '',
                'Outlet' => $outlet->Uuid ?? '',
                'Sampai' => is_string($sampai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai) === 1 ? $sampai : '',
            ],
            'Pembayaran' => is_string($uuidMetode) && $uuidMetode !== '' && $outlet !== null
                ? $belum->Ambil($uuidMetode, $outlet->Id, self::BacaSampai($sampai))
                : ['Data' => [], 'Total' => '0.00', 'Terpotong' => false],
            'HariIni' => CarbonImmutable::today()->toDateString(),
            'MaksimalBaris' => PembayaranBelumDicairkan::MAKS_BARIS,
        ]);
    }

    public function Simpan(BuatPencairanPermintaan $permintaan, BuatPencairan $buat): RedirectResponse
    {
        $pencairan = $buat->Jalankan($permintaan->AmbilData(), $this->Pelaku()->Id);

        return to_route('kelola.akuntansi.pencairan.detail', ['pencairan' => $pencairan->Uuid])
            ->with('Kilat', "Pencairan {$pencairan->Nomor} dicatat: akun kliring dilunasi dan potongan platformnya dibebankan.");
    }

    public function Detail(string $pencairan, DetailPencairan $detail): Response
    {
        $dokumen = $this->CariPencairan($pencairan);
        $kelola = $this->CekIzinKelola();

        return Inertia::render('Kelola/Akuntansi/Pencairan/Detail', [
            ...$detail->Ambil($dokumen),
            'Izin' => ['Kelola' => $kelola],
            'Tindakan' => ['Batalkan' => $kelola && $dokumen->Status === StatusDokumenTerposting::Diposting],
        ]);
    }

    public function Batalkan(BatalkanPencairanPermintaan $permintaan, string $pencairan, BatalkanPencairan $batalkan): RedirectResponse
    {
        $dokumen = $this->CariPencairan($pencairan);
        $hasil = $batalkan->Jalankan($dokumen->Uuid, $permintaan->AmbilAlasan(), $this->Pelaku()->Id);

        return to_route('kelola.akuntansi.pencairan.detail', ['pencairan' => $hasil->Uuid])
            ->with('Kilat', "{$hasil->Nomor} dibatalkan: setorannya ditarik dari buku dan pembayarannya bisa dicairkan ulang.");
    }

    /** Pencairan di outlet di luar akses pelaku = 404, sama seperti transaksi kas & bank. */
    private function CariPencairan(string $uuid): Pencairan
    {
        $dokumen = Pencairan::query()->where('Uuid', $uuid)->firstOrFail();
        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ! in_array($dokumen->IdOutlet, $boleh, true), 404);

        return $dokumen;
    }

    private static function BacaSampai(mixed $nilai): ?CarbonImmutable
    {
        if (! is_string($nilai) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) !== 1) {
            return null;
        }

        $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', $nilai);

        return $tanggal instanceof CarbonImmutable ? $tanggal : null;
    }
}
