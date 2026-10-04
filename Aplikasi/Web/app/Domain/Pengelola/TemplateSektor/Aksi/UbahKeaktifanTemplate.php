<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Menonaktifkan atau mengaktifkan kembali template sektor (D-47). Template yang sudah terbit tidak pernah dihapus
 * (BR-P03.2), tetapi bisa ditarik dari pilihan tenant baru. Tenant yang sudah memakainya tidak terpengaruh: versi
 * yang diterapkan ke outlet tetap terbaca. Hanya template yang punya versi terbit yang bisa dinonaktifkan; yang belum
 * pernah terbit cukup dihapus lewat drafnya. Idempoten.
 */
final class UbahKeaktifanTemplate
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, TemplateSektor $template, bool $aktif): TemplateSektor
    {
        return DB::transaction(function () use ($pelaku, $template, $aktif): TemplateSektor {
            $template = TemplateSektor::query()->lockForUpdate()->findOrFail($template->Id);

            if ($aktif === ($template->DinonaktifkanPada === null)) {
                return $template;
            }

            if (! $aktif && ! $template->Versi()->whereIn('Status', ['Terbit', 'Usang'])->exists()) {
                throw new PelanggaranAturanBisnis('TemplateBelumTerbit', 'Template ini belum pernah terbit. Hapus drafnya bila tidak dipakai.');
            }

            $lama = $template->DinonaktifkanPada?->toIso8601String();
            $template->forceFill(['DinonaktifkanPada' => $aktif ? null : now()])->save();

            $this->audit->Catat(
                $aktif ? 'template.aktifkan' : 'template.nonaktifkan',
                $template,
                nilaiLama: ['DinonaktifkanPada' => $lama],
                nilaiBaru: ['Kode' => $template->Kode, 'DinonaktifkanPada' => $template->DinonaktifkanPada?->toIso8601String()],
                idPelaku: $pelaku->Id,
            );

            return $template;
        });
    }
}
