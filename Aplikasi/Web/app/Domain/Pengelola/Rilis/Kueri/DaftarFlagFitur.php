<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Kueri;

use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\CakupanFlagFitur;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\FlagFitur;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\Tenant;

/** Aturan flag fitur beserta pilihan kunci (katalog fitur), paket, dan tenant untuk halaman Flag fitur (P-10). */
final class DaftarFlagFitur
{
    public const MAKS_TENANT = 1000;

    /**
     * @return array{Aturan: list<array<string, mixed>>, OpsiKunci: list<array{Nilai: string, Label: string}>, OpsiPaket: list<array{Nilai: string, Label: string}>, OpsiTenant: list<array{Nilai: string, Label: string}>}
     */
    public function Ambil(): array
    {
        $aturan = FlagFitur::query()->orderBy('Kunci')->orderBy('Cakupan')->get();
        $paket = Paket::query()->orderBy('Urutan')->get(['Id', 'Uuid', 'Nama']);
        $idTenant = $aturan->where('Cakupan', CakupanFlagFitur::Tenant)->pluck('IdObjek')->filter()->all();
        $tenantAturan = Tenant::query()->whereIn('Id', $idTenant)->get(['Id', 'Uuid', 'Nama'])->keyBy('Id');
        $pengelola = PenggunaPengelola::query()->whereIn('Id', $aturan->pluck('DiubahOleh')->filter()->all())->pluck('Nama', 'Id');

        return [
            'Aturan' => array_values($aturan->map(function (FlagFitur $f) use ($paket, $tenantAturan, $pengelola): array {
                $objek = match ($f->Cakupan) {
                    CakupanFlagFitur::Paket => $paket->firstWhere('Id', $f->IdObjek)?->Nama,
                    CakupanFlagFitur::Tenant => $tenantAturan->get($f->IdObjek)?->Nama,
                    default => null,
                };

                return [
                    'Uuid' => $f->Uuid,
                    'Kunci' => $f->Kunci,
                    'Cakupan' => $f->Cakupan->value,
                    'Objek' => $objek,
                    'Nilai' => $f->Nilai,
                    'Persen' => $f->Persen,
                    'Alasan' => $f->Alasan,
                    'DiubahOleh' => $f->DiubahOleh === null ? null : ($pengelola[$f->DiubahOleh] ?? null),
                    'DiubahPada' => $f->DiubahPada?->toIso8601String(),
                ];
            })->all()),
            'OpsiKunci' => array_values(Fitur::query()->orderBy('Kunci')->get(['Kunci', 'Nama'])->map(fn (Fitur $f): array => ['Nilai' => $f->Kunci, 'Label' => "{$f->Kunci} | {$f->Nama}"])->all()),
            'OpsiPaket' => array_values($paket->map(fn (Paket $p): array => ['Nilai' => $p->Uuid, 'Label' => $p->Nama])->all()),
            'OpsiTenant' => array_values(Tenant::query()->orderBy('Nama')->limit(self::MAKS_TENANT)->get(['Uuid', 'Nama'])->map(fn (Tenant $t): array => ['Nilai' => $t->Uuid, 'Label' => $t->Nama])->all()),
        ];
    }
}
