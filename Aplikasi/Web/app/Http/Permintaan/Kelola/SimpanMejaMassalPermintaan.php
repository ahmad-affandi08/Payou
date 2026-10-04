<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Organisasi\Aksi\SimpanMejaMassal;
use App\Domain\Organisasi\Enum\BentukMeja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /kelola/outlet/{outlet}/meja/massal`: `{Awalan, Mulai, Jumlah, Area?, Kapasitas, Bentuk}`. Izin dijaga rute.
 */
final class SimpanMejaMassalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Awalan' => ['nullable', 'string', 'max:25'],
            'Mulai' => ['required', 'integer', 'min:0', 'max:9999'],
            'Jumlah' => ['required', 'integer', 'min:1', 'max:'.SimpanMejaMassal::MAKS],
            'Area' => ['nullable', 'ulid'],
            'Kapasitas' => ['required', 'integer', 'min:1', 'max:99'],
            'Bentuk' => ['required', Rule::enum(BentukMeja::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Jumlah.max' => 'Paling banyak '.SimpanMejaMassal::MAKS.' meja sekali buat.',
            'Kapasitas.min' => 'Kapasitas minimal 1 orang.',
            'Kapasitas.max' => 'Kapasitas maksimal 99 orang.',
        ];
    }
}
