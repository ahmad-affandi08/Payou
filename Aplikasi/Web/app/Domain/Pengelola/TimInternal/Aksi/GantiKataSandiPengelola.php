<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PenjagaPerangkatTepercaya;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\Hash;

/**
 * D-22: anggota tim mengganti kata sandinya sendiri. Wajib saat kata sandi awal dibuat Super Admin
 * (`WajibGantiKataSandi`), dan boleh kapan saja. Kata sandi lama wajib benar dan kata sandi baru harus berbeda.
 * D-42: semua perangkat tepercaya akun ini ikut dicabut, jadi login berikutnya di mana pun kembali meminta kode 2FA.
 */
final class GantiKataSandiPengelola
{
    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly PenjagaPerangkatTepercaya $perangkatTepercaya,
    ) {}

    public function Jalankan(PenggunaPengelola $pengguna, string $kataSandiLama, string $kataSandiBaru): void
    {
        if (! Hash::check($kataSandiLama, $pengguna->KataSandi)) {
            throw new PelanggaranAturanBisnis('KataSandiLamaSalah', 'Kata sandi saat ini salah.', 'KataSandiLama');
        }

        if (Hash::check($kataSandiBaru, $pengguna->KataSandi)) {
            throw new PelanggaranAturanBisnis('KataSandiSama', 'Kata sandi baru harus berbeda dari kata sandi saat ini.', 'KataSandi');
        }

        $wajib = $pengguna->WajibGantiKataSandi;
        $pengguna->forceFill(['KataSandi' => $kataSandiBaru, 'WajibGantiKataSandi' => false])->save();

        $this->audit->Catat('tim.anggota.ganti-kata-sandi', $pengguna, nilaiBaru: ['Wajib' => $wajib], idPelaku: $pengguna->Id);
        $this->perangkatTepercaya->CabutSemua($pengguna, 'Kata sandi diganti.');
    }
}
