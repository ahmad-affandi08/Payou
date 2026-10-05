<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Autentikasi;

use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * D-22: ganti kata sandi pengguna tenant (wajib setelah kata sandi awal dibuat admin).
 */
final class GantiKataSandiPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // D-57: akun yang kata sandinya dibuat sistem (pendaftaran Google) tidak punya kata sandi lama untuk diketik.
            'KataSandiLama' => [Rule::requiredIf(fn (): bool => ! ($this->user() instanceof Pengguna && $this->user()->KataSandiOtomatis)), 'nullable', 'string', 'max:100'],
            'KataSandi' => ['required', 'string', Password::min(8)->letters()->numbers(), 'max:100'],
            'KonfirmasiKataSandi' => ['required', 'same:KataSandi'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['KonfirmasiKataSandi.same' => 'Konfirmasi kata sandi tidak sama.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['KataSandiLama' => 'kata sandi saat ini', 'KataSandi' => 'kata sandi baru'];
    }
}
