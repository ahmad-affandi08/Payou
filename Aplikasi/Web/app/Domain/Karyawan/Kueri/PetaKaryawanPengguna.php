<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Kueri;

use App\Domain\Karyawan\Model\Karyawan;

/**
 * Pengguna ↔ karyawan (F-18): untuk daftar Pengguna yang perlu menunjukkan apakah akunnya sudah tercatat sebagai karyawan.
 * Satu akun satu karyawan per tenant.
 */
final class PetaKaryawanPengguna
{
    /**
     * @param  list<int>  $idPengguna
     * @return array<int, array{Uuid: string, Status: string}> Id pengguna → karyawan tertaut
     */
    public function Ambil(array $idPengguna): array
    {
        if ($idPengguna === []) {
            return [];
        }

        $hasil = [];

        foreach (Karyawan::query()->whereIn('IdPengguna', $idPengguna)->get(['Id', 'Uuid', 'IdPengguna', 'Status']) as $k) {
            $hasil[(int) $k->IdPengguna] = ['Uuid' => $k->Uuid, 'Status' => $k->Status->value];
        }

        return $hasil;
    }
}
