<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /api/pos/v1/penjualan/cari?nomor=` (F-09 fase 1): nomor struk lengkap (`INV/...`) dicocokkan persis, atau isi QR struk digital (`.../s/{kode}`).
 */
final class CariPenjualanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nomor' => ['required', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['nomor.*' => 'Isi nomor struk penjualan, misal INV/UTAMA/260924/UTAMA-K01-0042.'];
    }

    public function AmbilNomor(): string
    {
        return trim($this->string('nomor')->toString());
    }
}
