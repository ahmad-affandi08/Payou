<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pemilik\V1;

use Illuminate\Foundation\Http\FormRequest;

/** `POST /api/pemilik/v1/masuk/google` (D-57): token ID Google dari aplikasi. Menggantikan kode 2FA. */
final class MasukGooglePermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'IdToken' => ['required', 'string', 'max:4096'],
            'NamaPerangkat' => ['required', 'string', 'max:100'],
        ];
    }
}
