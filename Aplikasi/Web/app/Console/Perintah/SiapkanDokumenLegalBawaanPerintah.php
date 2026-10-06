<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Pengelola\Konten\Aksi\SiapkanDrafDokumenLegalBawaan;
use Illuminate\Console\Command;

/**
 * P-06: membuat draf awal Syarat & Ketentuan, Kebijakan Privasi, dan Perjanjian Pemrosesan Data bila belum ada.
 * Hanya draf; terbitkan lewat konsol setelah ditinjau penasihat hukum.
 */
final class SiapkanDokumenLegalBawaanPerintah extends Command
{
    protected $signature = 'legal:siapkan-bawaan';

    protected $description = 'Membuat draf awal dokumen legal wajib registrasi bila belum ada (P-06). Hanya draf, tidak diterbitkan.';

    public function handle(SiapkanDrafDokumenLegalBawaan $siapkan): int
    {
        $dibuat = $siapkan->Jalankan();

        if ($dibuat === []) {
            $this->info('Semua dokumen legal wajib sudah punya versi. Tidak ada yang dibuat.');

            return self::SUCCESS;
        }

        foreach ($dibuat as $jenis) {
            $this->line("Draf dibuat: {$jenis->AmbilLabel()}");
        }

        $this->warn('Ini rancangan, bukan nasihat hukum. Tinjau bersama penasihat hukum, lengkapi identitas badan penyelenggara, lalu terbitkan lewat konsol (menu Dokumen legal).');

        return self::SUCCESS;
    }
}
