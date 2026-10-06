<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Kasir\Kueri\PemeriksaanTutupHarian;
use App\Domain\Kasir\Model\TutupHarian;
use App\Domain\Laporan\Aksi\BangunUlangRingkasanPenjualanHarian;
use App\Domain\Laporan\Kueri\RingkasanPenjualanHarianOutlet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-15 tutup harian (End of Day) satu outlet untuk satu tanggal bisnis:
 * 1. tanggal tidak boleh setelah tanggal bisnis outlet saat ini, dan belum pernah ditutup;
 * 2. semua shift tanggal itu sudah ditutup (wajib, `ShiftBelumDitutup`);
 * 3. peringatan (perangkat belum sinkron sejak hari berakhir, penjualan perlu tinjauan) harus diakui penutup
 *    (`$abaikanPeringatan`) dan disimpan di baris tutup harian;
 * 4. `RingkasanPenjualanHarian` outlet itu dihitung ulang dari dokumen sumber, lalu cuplikannya disimpan.
 * Audit `kasir.tutup-harian`. Tutup harian tidak mengunci transaksi: penjualan offline yang terlambat tetap diterima
 * dan memperbarui ringkasan lewat penangan peristiwa.
 */
final class TutupHarianOutlet
{
    public function __construct(
        private readonly PemeriksaanTutupHarian $pemeriksaan,
        private readonly BangunUlangRingkasanPenjualanHarian $bangunUlang,
        private readonly RingkasanPenjualanHarianOutlet $ringkasan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idOutlet, CarbonImmutable $tanggal, int $idPengguna, bool $abaikanPeringatan, bool $otomatis = false): TutupHarian
    {
        $tanggal = $tanggal->startOfDay();
        $label = $tanggal->translatedFormat('j F Y');
        $hasil = $this->pemeriksaan->Periksa($idOutlet, $tanggal);

        if ($hasil['BelumBerjalan']) {
            throw new PelanggaranAturanBisnis('TanggalBelumBerjalan', "Tanggal {$label} belum berjalan di outlet ini.", 'TanggalBisnis');
        }

        if ($hasil['ShiftBelumDitutup'] > 0) {
            throw new PelanggaranAturanBisnis(
                'ShiftBelumDitutup',
                "Masih ada {$hasil['ShiftBelumDitutup']} shift tanggal {$label} yang belum ditutup. Tutup shift di aplikasi kasir dulu.",
                'TanggalBisnis',
                detail: ['Jumlah' => $hasil['ShiftBelumDitutup']],
            );
        }

        if ($hasil['Peringatan'] !== [] && ! $abaikanPeringatan) {
            throw new PelanggaranAturanBisnis(
                'PeringatanTutupHarian',
                'Periksa peringatan tutup harian dulu, lalu centang konfirmasi untuk tetap menutup hari.',
                'AbaikanPeringatan',
                detail: ['Peringatan' => $hasil['Peringatan']],
            );
        }

        return DB::transaction(function () use ($idOutlet, $tanggal, $idPengguna, $label, $hasil, $otomatis): TutupHarian {
            if (TutupHarian::query()->where('IdOutlet', $idOutlet)->where('TanggalBisnis', $tanggal->toDateString())->lockForUpdate()->exists()) {
                throw new PelanggaranAturanBisnis('HariSudahDitutup', "Tanggal {$label} di outlet ini sudah ditutup.", 'TanggalBisnis');
            }

            $this->bangunUlang->Jalankan($tanggal, $idOutlet);
            $ringkasan = $this->ringkasan->Ambil($idOutlet, $tanggal);

            $tutup = TutupHarian::query()->create([
                'IdOutlet' => $idOutlet,
                'TanggalBisnis' => $tanggal->toDateString(),
                'DitutupPada' => now(),
                'DitutupOleh' => $idPengguna,
                'DitutupOtomatis' => $otomatis,
                'JumlahTransaksi' => $ringkasan['JumlahTransaksi'],
                'PenjualanBersih' => $ringkasan['Bersih'],
                'Peringatan' => $hasil['Peringatan'] === [] ? null : $hasil['Peringatan'],
            ]);
            $this->audit->Catat('kasir.tutup-harian', $tutup, nilaiBaru: [
                'IdOutlet' => $idOutlet,
                'TanggalBisnis' => $tanggal->toDateString(),
                'Peringatan' => array_column($hasil['Peringatan'], 'Kode'),
                'Otomatis' => $otomatis,
            ]);

            return $tutup;
        });
    }
}
