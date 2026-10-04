<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Harga\Aksi\UbahHargaProdukMassal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /kelola/produk/harga-massal`: `{Mode, Nilai, Pembulatan, Uuid[]}`. Izin `produk.harga.ubah` dijaga rute.
 */
final class UbahHargaProdukMassalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Mode' => ['required', 'string', Rule::in(UbahHargaProdukMassal::MODE)],
            'Nilai' => ['required', 'string', 'max:15'],
            'Pembulatan' => ['required', 'integer', Rule::in(UbahHargaProdukMassal::PEMBULATAN)],
            'Uuid' => ['required', 'array', 'min:1', 'max:'.UbahHargaProdukMassal::MAKS],
            'Uuid.*' => ['required', 'ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Uuid' => 'produk terpilih', 'Nilai' => 'nilai perubahan', 'Mode' => 'cara ubah harga'];
    }
}
