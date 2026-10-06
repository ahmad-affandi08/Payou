<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * P-06: membuat **draf** awal tiga dokumen legal wajib registrasi (Syarat & Ketentuan, Kebijakan Privasi, Perjanjian
 * Pemrosesan Data) dari naskah di `database/data/legal`, untuk jenis yang belum punya versi apa pun. Idempoten.
 *
 * Hanya draf, tidak pernah terbit sendiri: naskah ini rancangan yang wajib ditinjau penasihat hukum dan dilengkapi
 * identitas badan penyelenggara sebelum diterbitkan lewat konsol (BR-P06.1 sampai BR-P06.4 tetap berlaku).
 */
final class SiapkanDrafDokumenLegalBawaan
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @return list<JenisDokumenLegal> jenis yang dibuatkan draf
     */
    public function Jalankan(): array
    {
        $dibuat = [];

        foreach (JenisDokumenLegal::AmbilWajibRegistrasi() as $jenis) {
            if (DokumenLegal::query()->where('Jenis', $jenis->value)->exists()) {
                continue;
            }

            $naskah = base_path("database/data/legal/{$jenis->value}.md");
            $isi = is_file($naskah) ? file_get_contents($naskah) : false;

            if ($isi === false || trim($isi) === '') {
                throw new RuntimeException("Naskah bawaan {$jenis->AmbilLabel()} tidak ditemukan di database/data/legal.");
            }

            try {
                $draf = DokumenLegal::query()->create([
                    'Jenis' => $jenis,
                    'Versi' => 1,
                    'Status' => StatusDokumenLegal::Draf,
                    'Judul' => $jenis->AmbilLabel(),
                    'Isi' => trim($isi),
                    'RingkasanPerubahan' => 'Naskah awal.',
                    'Materiil' => true,
                    'BerlakuMulai' => now('Asia/Jakarta')->toDateString(),
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $this->audit->Catat('legal.draf.buat', $draf, nilaiBaru: ['Jenis' => $jenis->value, 'Versi' => 1, 'Sumber' => 'bawaan']);
            $dibuat[] = $jenis;
        }

        return $dibuat;
    }
}
