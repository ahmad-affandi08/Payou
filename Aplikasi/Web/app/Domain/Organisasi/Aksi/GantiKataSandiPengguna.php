<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Support\Facades\Hash;

/**
 * D-22: pengguna tenant mengganti kata sandinya sendiri. Wajib bila kata sandi awal dibuat admin
 * (`WajibGantiKataSandi`), boleh kapan saja. Sesi lain akun ini otomatis keluar (AuthenticateSession, BR-00.9).
 */
final class GantiKataSandiPengguna
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Pengguna $pengguna, string $kataSandiLama, string $kataSandiBaru, ?int $idTenant, ?string $ip, ?string $agenPengguna): void
    {
        // D-57: kata sandi buatan sistem (akun Google) tidak mungkin diketahui pengguna, jadi tidak diminta.
        if (! $pengguna->KataSandiOtomatis && ! Hash::check($kataSandiLama, $pengguna->KataSandi)) {
            throw new PelanggaranAturanBisnis('KataSandiLamaSalah', 'Kata sandi saat ini salah.', 'KataSandiLama');
        }

        if (Hash::check($kataSandiBaru, $pengguna->KataSandi)) {
            throw new PelanggaranAturanBisnis('KataSandiSama', 'Kata sandi baru harus berbeda dari kata sandi saat ini.', 'KataSandi');
        }

        $wajib = $pengguna->WajibGantiKataSandi;
        $pengguna->forceFill(['KataSandi' => $kataSandiBaru, 'WajibGantiKataSandi' => false, 'KataSandiOtomatis' => false])->save();

        $this->audit->CatatSesi('pengguna.ganti-kata-sandi', $idTenant, $pengguna->Id, $ip, $agenPengguna, ['Wajib' => $wajib]);
    }
}
