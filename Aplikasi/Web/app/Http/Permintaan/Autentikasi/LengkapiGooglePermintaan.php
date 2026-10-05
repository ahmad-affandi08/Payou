<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Autentikasi;

use Illuminate\Foundation\Http\FormRequest;

/** D-57: data yang masih dibutuhkan setelah Google membuktikan email: nama, WhatsApp, usaha, paket, dan persetujuan legal. */
final class LengkapiGooglePermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:150'],
            'NoHp' => ['required', 'string', 'regex:'.DaftarPermintaan::POLA_NO_HP],
            'NamaUsaha' => ['required', 'string', 'min:3', 'max:150'],
            'Paket' => ['nullable', 'string', 'max:30'],
            'Setuju' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'NoHp.regex' => 'Nomor WhatsApp diawali 08 atau +628, misal 081234567890.',
            'Setuju.accepted' => 'Centang persetujuan Syarat & Ketentuan dan Kebijakan Privasi.',
        ];
    }
}
