<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pelanggan\Aksi\JalankanKampanyePesan;
use App\Domain\Pelanggan\Enum\StatusKampanye;
use App\Domain\Pelanggan\Model\KampanyePesan;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CRM-07: tiap 5 menit memulai kampanye `Dijadwalkan` yang waktunya sudah tiba. Bila masih ada kampanye lain berjalan,
 * dicoba lagi putaran berikutnya. Kampanye yang tidak bisa dimulai (tanpa penerima, kanal mati) dibatalkan dengan
 * catatan di log (tanpa data pelanggan). Kegagalan satu tenant tidak menghentikan tenant lain.
 */
final class JalankanKampanyeTerjadwalPerintah extends Command
{
    protected $signature = 'pelanggan:jalankan-kampanye-terjadwal';

    protected $description = 'Memulai kampanye pesan pelanggan yang jadwal kirimnya sudah tiba (CRM-07).';

    public function handle(KonteksPengelola $pengelola, KonteksTenant $konteks, JalankanKampanyePesan $jalankan): int
    {
        $sebelumnya = $konteks->Ambil();
        $mulai = 0;
        $galat = 0;

        try {
            // Hanya tenant yang punya kampanye dijadwalkan yang sudah tiba waktunya (audit kinerja skala besar).
            foreach ($pengelola->IdTenantDenganPekerjaan(KampanyePesan::class, fn ($q) => $q->where('Status', StatusKampanye::Dijadwalkan->value)->where('DijadwalkanPada', '<=', now())) as $idTenant) {
                $konteks->Atur($idTenant);

                foreach (KampanyePesan::query()->where('Status', StatusKampanye::Dijadwalkan->value)->where('DijadwalkanPada', '<=', now())->orderBy('DijadwalkanPada')->get() as $kampanye) {
                    try {
                        $jalankan->Mulai($kampanye, null, CarbonImmutable::now());
                        $mulai++;
                    } catch (PelanggaranAturanBisnis $e) {
                        if ($e->kode === 'KampanyeLainBerjalan') {
                            continue;
                        }

                        $kampanye->refresh();

                        if ($kampanye->Status === StatusKampanye::Dijadwalkan) {
                            $kampanye->UbahStatus(StatusKampanye::Dibatalkan);
                            $kampanye->SelesaiPada = now();
                            $kampanye->save();
                        }

                        Log::warning('Kampanye terjadwal dibatalkan.', ['IdTenant' => $idTenant, 'IdKampanyePesan' => $kampanye->Id, 'Kode' => $e->kode]);
                    } catch (Throwable $e) {
                        $galat++;
                        report($e);
                    }
                }
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line("{$mulai} kampanye dimulai, {$galat} gagal.");

        return $galat === 0 ? self::SUCCESS : self::FAILURE;
    }
}
