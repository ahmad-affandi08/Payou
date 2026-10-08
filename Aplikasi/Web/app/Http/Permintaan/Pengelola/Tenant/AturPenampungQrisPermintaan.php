<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/** merchantId & terminalId QRIS dari DOKU Dashboard; kosong = hapus nilai. */
final class AturPenampungQrisPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'IdPedagangQris' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'IdTerminalQris' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['regex' => ':attribute hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['IdPedagangQris' => 'ID pedagang QRIS (merchantId)', 'IdTerminalQris' => 'ID terminal QRIS (terminalId)'];
    }

    public function AmbilIdPedagang(): ?string
    {
        return $this->AmbilBersih('IdPedagangQris');
    }

    public function AmbilIdTerminal(): ?string
    {
        return $this->AmbilBersih('IdTerminalQris');
    }

    private function AmbilBersih(string $nama): ?string
    {
        $nilai = trim($this->string($nama)->toString());

        return $nilai === '' ? null : $nilai;
    }
}
