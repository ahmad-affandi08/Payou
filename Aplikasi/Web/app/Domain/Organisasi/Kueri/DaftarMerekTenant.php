<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\Merek;

/**
 * Daftar merek tenant untuk domain lain (pengaturan struk per merek, D-70). Lewat scope `MilikTenant`.
 */
final class DaftarMerekTenant
{
    /**
     * @return list<array{Id: int, Uuid: string, Nama: string}>
     */
    public function Ambil(): array
    {
        return array_values(Merek::query()->orderBy('Id')->get(['Id', 'Uuid', 'Nama'])
            ->map(static fn (Merek $m): array => ['Id' => (int) $m->Id, 'Uuid' => $m->Uuid, 'Nama' => $m->Nama])
            ->all());
    }
}
