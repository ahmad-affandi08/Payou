<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Model\LanggananAddon;
use App\Domain\Tenant\Model\TagihanLangganan;
use App\Domain\Tenant\Model\TagihanLanggananAddon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pemilik berhenti berlangganan add-on atau melanjutkannya lagi (D-49). Berhenti = add-on tetap aktif sampai akhir
 * periode yang sudah dibayar, tidak ditagih lagi di perpanjangan berikutnya, tanpa refund. Melanjutkan hanya selama
 * masih aktif; add-on yang sudah habis dibeli ulang lewat `BeliAddonLangganan`. Idempoten.
 */
final class UbahPerpanjanganAddonLangganan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $uuidAddon, bool $lanjutkan): LanggananAddon
    {
        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant, $uuidAddon, $lanjutkan): LanggananAddon {
            $milik = LanggananAddon::query()->with('Addon')->where('IdTenant', $idTenant)->where('Uuid', $uuidAddon)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('AddonTidakDitemukan', 'Add-on tidak ditemukan.');

            if (! $milik->CekAktifPada(Carbon::now())) {
                throw new PelanggaranAturanBisnis('AddonSudahBerakhir', "Add-on {$milik->Addon->Nama} sudah berakhir. Beli lagi bila ingin memakainya.");
            }

            if ($lanjutkan === ($milik->BerhentiPada === null)) {
                return $milik;
            }

            if (! $lanjutkan) {
                $tagihan = TagihanLangganan::query()
                    ->whereIn('Status', StatusTagihanLangganan::NilaiTerbuka())
                    ->whereIn('Id', TagihanLanggananAddon::query()->where('IdAddon', $milik->IdAddon)->select('IdTagihanLangganan'))
                    ->first();

                if ($tagihan !== null) {
                    throw new PelanggaranAturanBisnis(
                        'AddonSudahDitagih',
                        "Add-on {$milik->Addon->Nama} sudah ada di tagihan {$tagihan->Nomor}. Batalkan tagihan itu lebih dulu, lalu buat ulang tanpa add-on ini.",
                    );
                }
            }

            $lama = ['PerpanjangOtomatis' => $milik->PerpanjangOtomatis, 'BerhentiPada' => $milik->BerhentiPada?->toIso8601ZuluString()];
            $milik->update($lanjutkan
                ? ['PerpanjangOtomatis' => true, 'BerhentiPada' => null]
                : ['PerpanjangOtomatis' => false, 'BerhentiPada' => Carbon::now()]);

            $this->audit->Catat(
                $lanjutkan ? 'langganan.addon-lanjut' : 'langganan.addon-berhenti',
                $milik,
                nilaiLama: $lama,
                nilaiBaru: ['Addon' => $milik->Addon->Kode, 'PerpanjangOtomatis' => $milik->PerpanjangOtomatis, 'AktifSampai' => $milik->SelesaiPada->toIso8601ZuluString()],
            );

            return $milik;
        });
    }
}
