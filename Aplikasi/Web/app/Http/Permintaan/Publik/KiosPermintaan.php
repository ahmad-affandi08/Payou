<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Publik;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-17 bagian 4: hitung dan pesan dari kios. Tanpa data pribadi sama sekali (tanpa nama, nomor HP, atau persetujuan data).
 */
final class KiosPermintaan extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $pesan = $this->routeIs('publik.kios.pesan');

        return [
            'Uuid' => [$pesan ? 'required' : 'sometimes', 'ulid'],
            'JenisSantap' => ['required', 'in:MakanDiTempat,BawaPulang'],
            'MetodePembayaran' => [$pesan ? 'required' : 'sometimes', 'in:BayarSaatAmbil,QrisOnline'],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'Baris' => ['required', 'array', 'min:1', 'max:50'],
            'Baris.*.UuidProduk' => ['required', 'ulid'],
            'Baris.*.UuidVarian' => ['sometimes', 'nullable', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'integer', 'min:1', 'max:99'],
            'Baris.*.Pilihan' => ['sometimes', 'array', 'max:20'],
            'Baris.*.Pilihan.*' => ['ulid'],
            'Baris.*.Catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return list<array{UuidProduk: string, Jumlah: int, Pilihan: list<string>, UuidVarian: string|null}> */
    public function AmbilBaris(): array
    {
        return array_values(array_map(fn (array $b): array => [
            'UuidProduk' => strtoupper((string) $b['UuidProduk']),
            'Jumlah' => (int) $b['Jumlah'],
            'Pilihan' => array_values(array_map(fn (mixed $u): string => strtoupper((string) $u), (array) ($b['Pilihan'] ?? []))),
            'UuidVarian' => is_string($b['UuidVarian'] ?? null) ? strtoupper($b['UuidVarian']) : null,
        ], (array) $this->validated('Baris')));
    }
}
