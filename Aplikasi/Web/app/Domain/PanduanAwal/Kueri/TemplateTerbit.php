<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Kueri;

use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;

/**
 * Template sektor yang bisa dipilih tenant (F-01 langkah 2): hanya versi Terbit dari template yang tidak dinonaktifkan
 * (BR-P03.1, D-47). Versi yang sudah diterapkan tetap bisa dibaca meski template kemudian dinonaktifkan atau versinya
 * Usang (dipakai produk contoh & flow lain).
 */
final class TemplateTerbit
{
    /**
     * @return list<array{Kode: string, Nama: string, Keterangan: string|null, IdVersi: int, Versi: int, Isi: array<string, mixed>}>
     */
    public function AmbilSemua(): array
    {
        $hasil = [];

        foreach (TemplateSektor::query()->whereNull('DinonaktifkanPada')->with('VersiTerbit')->orderBy('Nama')->get() as $template) {
            $versi = $template->VersiTerbit;

            if ($versi === null) {
                continue;
            }

            $hasil[] = [
                'Kode' => $template->Kode,
                'Nama' => $template->Nama,
                'Keterangan' => $template->Keterangan,
                'IdVersi' => $versi->Id,
                'Versi' => $versi->Versi,
                'Isi' => $versi->Isi,
            ];
        }

        return $hasil;
    }

    /**
     * @return list<string>
     */
    public function AmbilKode(): array
    {
        return array_column($this->AmbilSemua(), 'Kode');
    }

    public function Cari(string $kode): ?TemplateSektorVersi
    {
        return TemplateSektorVersi::query()
            ->with('TemplateSektor')
            ->where('Status', StatusTemplateSektor::Terbit->value)
            ->whereHas('TemplateSektor', fn ($kueri) => $kueri->where('Kode', $kode)->whereNull('DinonaktifkanPada'))
            ->first();
    }

    /** Versi yang pernah diterapkan ke outlet (Terbit atau sudah Usang). */
    public function CariVersi(?int $idVersi): ?TemplateSektorVersi
    {
        return $idVersi === null ? null : TemplateSektorVersi::query()
            ->with('TemplateSektor')
            ->whereKey($idVersi)
            ->whereIn('Status', [StatusTemplateSektor::Terbit->value, StatusTemplateSektor::Usang->value])
            ->first();
    }
}
