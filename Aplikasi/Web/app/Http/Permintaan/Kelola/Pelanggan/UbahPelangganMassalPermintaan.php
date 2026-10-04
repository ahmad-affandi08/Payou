<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pelanggan;

use App\Domain\Pelanggan\Aksi\UbahPelangganMassal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /kelola/pelanggan/massal`: `{Aksi, Uuid[], UuidTier?, TierTetap?}`. Izin `pelanggan.kelola` dijaga rute.
 */
final class UbahPelangganMassalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Aksi' => ['required', 'string', Rule::in(UbahPelangganMassal::AKSI)],
            'Uuid' => ['required', 'array', 'min:1', 'max:'.UbahPelangganMassal::MAKS],
            'Uuid.*' => ['required', 'ulid'],
            'UuidTier' => ['nullable', 'ulid'],
            'TierTetap' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Uuid' => 'pelanggan terpilih', 'UuidTier' => 'tier'];
    }
}
