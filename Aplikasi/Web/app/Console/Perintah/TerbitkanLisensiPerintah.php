<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Lisensi\Data\DataLisensi;
use App\Domain\Lisensi\Galat\LisensiTidakSah;
use App\Domain\Lisensi\Layanan\PenandaLisensi;
use Illuminate\Console\Command;

/**
 * D-35: menerbitkan berkas lisensi untuk satu pembeli (satu usaha, satu domain, berlaku selamanya). Dijalankan pemilik
 * produk di mesin yang menyimpan kunci privat. Berkas hasilnya diverifikasi ulang dengan kunci publik di
 * `config/lisensi.php` sebelum ditulis, supaya lisensi yang diserahkan pasti bisa dipasang.
 */
final class TerbitkanLisensiPerintah extends Command
{
    protected $signature = 'lisensi:terbitkan
        {--nomor= : Nomor lisensi, misal PAYOUNG-L-2026-0001}
        {--pemegang= : Nama pembeli (badan usaha atau perorangan)}
        {--domain= : Domain dashboard pembeli, misal kasir.tokoabc.com}
        {--batas-outlet= : Jumlah outlet maksimal (kosong = tak terbatas)}
        {--batas-perangkat= : Perangkat kasir per outlet maksimal (kosong = tak terbatas)}
        {--batas-pengguna= : Pengguna maksimal (kosong = tak terbatas)}
        {--pembaruan-sampai= : Akhir masa pembaruan & dukungan YYYY-MM-DD (bawaan 1 tahun sejak terbit, D-36)}
        {--kunci-privat= : Berkas kunci privat penerbit}
        {--keluaran= : Berkas lisensi yang dihasilkan, misal payoung-tokoabc.lisensi}';

    protected $description = 'Menerbitkan berkas lisensi Payoung bertanda tangan untuk satu pembeli (D-35).';

    public function handle(PenandaLisensi $penanda): int
    {
        $berkasKunci = $this->option('kunci-privat');
        $kunciPrivat = is_string($berkasKunci) && is_readable($berkasKunci) ? file_get_contents($berkasKunci) : false;
        $keluaran = $this->option('keluaran');

        if ($kunciPrivat === false || ! is_string($keluaran) || $keluaran === '') {
            $this->error('Isi --kunci-privat (berkas yang bisa dibaca) dan --keluaran.');

            return self::FAILURE;
        }

        // D-36: hak pakai selamanya, pembaruan & dukungan gratis 1 tahun. Perpanjangan pemeliharaan = terbitkan ulang
        // lisensi bernomor sama dengan tanggal baru, lalu pembeli menjalankan lisensi:pasang.
        $hariIni = now('Asia/Jakarta');
        $pembaruanSampai = $this->option('pembaruan-sampai');

        try {
            $data = DataLisensi::DariArray([
                'Nomor' => $this->option('nomor'),
                'NamaPemegang' => $this->option('pemegang'),
                'Domain' => $this->option('domain'),
                'BatasOutlet' => $this->AmbilBatas('batas-outlet'),
                'BatasPerangkatPerOutlet' => $this->AmbilBatas('batas-perangkat'),
                'BatasPengguna' => $this->AmbilBatas('batas-pengguna'),
                'DiterbitkanPada' => $hariIni->toDateString(),
                'PembaruanSampai' => is_string($pembaruanSampai) && $pembaruanSampai !== '' ? $pembaruanSampai : $hariIni->addYear()->subDay()->toDateString(),
            ]);
            $isi = $penanda->Tandatangani($data, $kunciPrivat);
            $penanda->Baca($isi);
        } catch (LisensiTidakSah $galat) {
            $this->error($galat->getMessage());

            return self::FAILURE;
        }

        if (file_exists($keluaran)) {
            $this->error("Berkas {$keluaran} sudah ada. Pilih nama lain agar lisensi lama tidak tertimpa.");

            return self::FAILURE;
        }

        if (file_put_contents($keluaran, $isi) === false) {
            $this->error("Tidak bisa menulis {$keluaran}.");

            return self::FAILURE;
        }

        $this->info("Lisensi {$data->nomor} untuk {$data->namaPemegang} ({$data->domain}) ditulis ke {$keluaran}. Pembaruan & dukungan sampai {$data->pembaruanSampai}.");

        return self::SUCCESS;
    }

    private function AmbilBatas(string $opsi): int|string|null
    {
        $nilai = $this->option($opsi);

        if (! is_string($nilai) || trim($nilai) === '') {
            return null;
        }

        return ctype_digit(trim($nilai)) ? (int) trim($nilai) : $nilai;
    }
}
