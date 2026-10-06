<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;
use App\Domain\Tenant\Model\PersetujuanDokumenLegal;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * P-06: membuat **draf versi berikutnya** dari naskah bawaan (`database/data/legal`) untuk jenis yang sudah punya versi
 * terbit tetapi isinya berbeda dari naskah terbaru. Versi terbit tidak pernah disentuh (BR-P06.1); jenis yang sudah punya
 * draf atau yang isinya sudah sama dilewati, jadi aman dijalankan berulang.
 *
 * Perubahan dianggap materiil hanya bila sudah ada pengguna yang pernah menyetujui versi lama (ada yang terdampak).
 * Materiil wajib berlaku minimal 30 hari setelah terbit (BR-P06.3); selain itu bisa berlaku hari berikutnya.
 */
final class SiapkanDrafPembaruanDokumenLegal
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @return list<DokumenLegal> draf yang dibuat
     */
    public function Jalankan(): array
    {
        $dibuat = [];

        foreach (JenisDokumenLegal::AmbilWajibRegistrasi() as $jenis) {
            $sejenis = DokumenLegal::query()->where('Jenis', $jenis->value)->orderByDesc('Versi')->get();
            $terakhir = $sejenis->first();

            // Belum ada versi sama sekali: itu urusan `legal:siapkan-bawaan`. Sudah ada draf: satu draf per jenis (BR-P06.4).
            if ($terakhir === null || $sejenis->contains(fn (DokumenLegal $dokumen) => $dokumen->Status === StatusDokumenLegal::Draf)) {
                continue;
            }

            $naskah = $this->BacaNaskah($jenis);

            if (trim($terakhir->Isi) === $naskah) {
                continue;
            }

            $terbitTerakhir = $sejenis->filter(fn (DokumenLegal $dokumen) => $dokumen->Status === StatusDokumenLegal::Terbit)
                ->sortByDesc(fn (DokumenLegal $dokumen) => $dokumen->BerlakuMulai->toDateString())
                ->first();
            $terdampak = $terbitTerakhir !== null
                && PersetujuanDokumenLegal::query()->whereIn('IdDokumenLegal', $sejenis->pluck('Id'))->exists();

            $hariIni = now('Asia/Jakarta')->startOfDay();
            $berlaku = $hariIni->copy()->addDays($terdampak ? TerbitkanDokumenLegal::HARI_PENGUMUMAN_MATERIIL : 0);

            // Harus setelah versi terbit sebelumnya (BR-P06.3).
            if ($terbitTerakhir !== null && $berlaku->lte($terbitTerakhir->BerlakuMulai->toDateString())) {
                $berlaku = $terbitTerakhir->BerlakuMulai->copy()->addDay();
            }

            try {
                $draf = DokumenLegal::query()->create([
                    'Jenis' => $jenis,
                    'Versi' => (int) $sejenis->max('Versi') + 1,
                    'Status' => StatusDokumenLegal::Draf,
                    'Judul' => $jenis->AmbilLabel(),
                    'Isi' => $naskah,
                    'RingkasanPerubahan' => 'Penyempurnaan naskah: nama layanan Payoung, dua edisi (Cloud dan Mandiri), pesan ke pelanggan, absensi, masuk dengan Google, dan ketentuan umum.',
                    'Materiil' => $terdampak,
                    'BerlakuMulai' => $berlaku->toDateString(),
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $this->audit->Catat('legal.draf.buat', $draf, nilaiBaru: ['Jenis' => $jenis->value, 'Versi' => $draf->Versi, 'Sumber' => 'bawaan-pembaruan']);
            $dibuat[] = $draf;
        }

        return $dibuat;
    }

    private function BacaNaskah(JenisDokumenLegal $jenis): string
    {
        $berkas = base_path("database/data/legal/{$jenis->value}.md");
        $isi = is_file($berkas) ? file_get_contents($berkas) : false;

        if ($isi === false || trim($isi) === '') {
            throw new RuntimeException("Naskah bawaan {$jenis->AmbilLabel()} tidak ditemukan di database/data/legal.");
        }

        return trim($isi);
    }
}
