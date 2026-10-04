<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Karyawan\Aksi\KirimNotifikasiKehadiran;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * F-18 bagian 5 (D-44): tiap 5 menit mengirim pengingat shift ke karyawan dan peringatan terlambat/belum masuk ke
 * pengelola lewat WhatsApp, untuk tenant yang mengaktifkannya. Kegagalan satu tenant tidak menghentikan tenant lain.
 */
final class KirimNotifikasiKehadiranPerintah extends Command
{
    protected $signature = 'karyawan:kirim-notifikasi-kehadiran {--tenant=* : Id tenant (kosong = semua)}';

    protected $description = 'Mengirim pengingat shift dan peringatan kehadiran (terlambat/belum masuk) lewat WhatsApp (F-18 bagian 5).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, KirimNotifikasiKehadiran $kirim): int
    {
        $diminta = array_values(array_filter(array_map(
            fn (mixed $nilai): int => is_scalar($nilai) ? (int) $nilai : 0,
            (array) $this->option('tenant'),
        ), fn (int $id): bool => $id > 0));
        $semua = $keanggotaan->AmbilSemuaIdTenant();
        $daftar = $diminta === [] ? $semua : array_values(array_intersect(array_unique($diminta), $semua));
        $sebelumnya = $konteks->Ambil();
        $pengingat = 0;
        $peringatan = 0;
        $galat = 0;

        try {
            foreach ($daftar as $idTenant) {
                $konteks->Atur($idTenant);

                try {
                    $hasil = $kirim->Jalankan($idTenant, CarbonImmutable::now('UTC'));
                    $pengingat += $hasil['Pengingat'];
                    $peringatan += $hasil['Peringatan'];
                } catch (Throwable $e) {
                    $galat++;
                    report($e);
                }
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line(count($daftar)." tenant diperiksa, {$pengingat} pengingat shift dan {$peringatan} peringatan terkirim, {$galat} tenant gagal.");

        return $galat === 0 ? self::SUCCESS : self::FAILURE;
    }
}
