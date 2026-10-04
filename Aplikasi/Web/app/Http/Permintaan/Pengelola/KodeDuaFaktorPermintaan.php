<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola;

use Illuminate\Foundation\Http\FormRequest;

final class KodeDuaFaktorPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Kode' => ['required', 'string', 'max:20'],
            'PercayaiPerangkat' => ['sometimes', 'boolean'],
        ];
    }
}
