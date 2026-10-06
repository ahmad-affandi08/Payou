<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Pengelola\Konten\Aksi\TerbitkanHalamanSitusTerjadwal;
use Illuminate\Console\Command;

/**
 * D-63: menerbitkan halaman situs yang jadwalnya sudah tiba (terjadwal tiap menit, routes/console.php).
 */
final class TerbitkanHalamanSitusTerjadwalPerintah extends Command
{
    protected $signature = 'situs:terbitkan-terjadwal';

    protected $description = 'Menerbitkan halaman situs pemasaran yang jadwal terbitnya sudah tiba.';

    public function handle(TerbitkanHalamanSitusTerjadwal $terbitkan): int
    {
        $this->info("{$terbitkan->Jalankan()} halaman diterbitkan.");

        return self::SUCCESS;
    }
}
