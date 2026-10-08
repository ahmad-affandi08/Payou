<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Autentikasi;

use Illuminate\Foundation\Http\FormRequest;

final class GantiEmailPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Email' => ['required', 'string', 'email:rfc', 'max:191'],
            'KataSandi' => ['required', 'string', 'max:100'],
        ];
    }
}
