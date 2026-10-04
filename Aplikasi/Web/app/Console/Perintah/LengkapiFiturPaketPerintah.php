<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Pengelola\Katalog\Aksi\LengkapiFiturPaketBawaan;
use Illuminate\Console\Command;

/**
 * Jalankan setelah memperbarui rilis: menambahkan fitur katalog baru ke paket yang sudah terpasang (hanya menambah,
 * tidak pernah mencabut). `--kering` hanya menampilkan apa yang akan ditambahkan.
 */
final class LengkapiFiturPaketPerintah extends Command
{
    protected $signature = 'katalog:lengkapi-fitur {--kering : Hanya tampilkan, jangan ubah data}';

    protected $description = 'Menambahkan fitur katalog baru dari berkas data rilis ke paket yang sudah ada (tidak mencabut apa pun).';

    public function handle(LengkapiFiturPaketBawaan $lengkapi): int
    {
        $kering = (bool) $this->option('kering');
        $hasil = $lengkapi->Jalankan(! $kering);

        foreach ($hasil['FiturBaru'] as $kunci) {
            $this->line(($kering ? '[kering] ' : '').'fitur baru: '.$kunci);
        }

        foreach ($hasil['Penambahan'] as $baris) {
            $this->line(($kering ? '[kering] ' : '')."paket {$baris['Paket']} + {$baris['Fitur']}");
        }

        $this->info(($kering ? 'Akan menambah ' : 'Menambah ').count($hasil['FiturBaru']).' fitur katalog dan '.count($hasil['Penambahan']).' fitur ke paket.');

        return self::SUCCESS;
    }
}
