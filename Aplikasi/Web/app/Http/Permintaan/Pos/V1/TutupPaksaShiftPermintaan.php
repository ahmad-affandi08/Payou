<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/pos/v1/shift/{uuidShift}/tutup-paksa`: shift lama perangkat ini ditutup paksa oleh supervisor
 * (`UuidPenyetuju`, PIN-nya sudah diperiksa di perangkat) dengan alasan tertulis.
 */
final class TutupPaksaShiftPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'UuidPenyetuju' => ['required', 'string', 'size:26'],
            'Alasan' => ['required', 'string', 'min:5', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'UuidPenyetuju.*' => 'Pilih supervisor yang menyetujui.',
            'Alasan.*' => 'Tulis alasan menutup shift lama (5 sampai 150 huruf).',
        ];
    }
}
