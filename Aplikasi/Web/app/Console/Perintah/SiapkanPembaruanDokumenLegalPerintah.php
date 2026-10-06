<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Pengelola\Konten\Aksi\SiapkanDrafPembaruanDokumenLegal;
use Illuminate\Console\Command;

/**
 * P-06: membuat draf versi berikutnya dari naskah bawaan terbaru bagi dokumen legal yang sudah terbit dengan isi lama.
 * Hanya draf; tinjau dan terbitkan lewat konsol.
 */
final class SiapkanPembaruanDokumenLegalPerintah extends Command
{
    protected $signature = 'legal:siapkan-pembaruan';

    protected $description = 'Membuat draf versi berikutnya dari naskah bawaan terbaru untuk dokumen legal yang isinya sudah berbeda (P-06). Hanya draf, tidak diterbitkan.';

    public function handle(SiapkanDrafPembaruanDokumenLegal $siapkan): int
    {
        $dibuat = $siapkan->Jalankan();

        if ($dibuat === []) {
            $this->info('Semua dokumen legal sudah sesuai naskah terbaru (atau sudah punya draf). Tidak ada yang dibuat.');

            return self::SUCCESS;
        }

        foreach ($dibuat as $draf) {
            $materiil = $draf->Materiil ? 'materiil, berlaku minimal 30 hari setelah terbit' : 'tanpa pengguna terdampak';
            $this->line("Draf dibuat: {$draf->Jenis->AmbilLabel()} versi {$draf->Versi} ({$materiil}), tanggal berlaku usulan {$draf->BerlakuMulai->toDateString()}.");
        }

        $this->warn('Ini rancangan, bukan nasihat hukum. Tinjau di konsol (menu Dokumen legal), sesuaikan tanggal berlaku bila perlu, lalu terbitkan.');

        return self::SUCCESS;
    }
}
