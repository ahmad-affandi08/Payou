<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Outlet;

/**
 * Outlet aktif tenant untuk pilihan di halaman Pengaturan struk (D-76): saklar tampil dan logo struk diatur per outlet.
 * Lewat scope `MilikTenant`.
 */
final class DaftarOutletStruk
{
    /**
     * @return list<array{Id: int, Uuid: string, Nama: string}>
     */
    public function Ambil(): array
    {
        return array_values(Outlet::query()
            ->where('Status', StatusOrganisasi::Aktif->value)
            ->orderBy('Id')
            ->get(['Id', 'Uuid', 'Nama'])
            ->map(static fn (Outlet $o): array => ['Id' => (int) $o->Id, 'Uuid' => $o->Uuid, 'Nama' => $o->Nama])
            ->all());
    }
}
