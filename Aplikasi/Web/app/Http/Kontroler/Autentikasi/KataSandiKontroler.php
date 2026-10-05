<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Autentikasi;

use App\Domain\Organisasi\Aksi\GantiKataSandiPengguna;
use App\Domain\Organisasi\Model\Pengguna;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\IdentifikasiTenantSesi;
use App\Http\Permintaan\Autentikasi\GantiKataSandiPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * D-22: ganti kata sandi pengguna tenant. Wajib sebelum membuka back-office bila kata sandi awal dibuat admin.
 */
final class KataSandiKontroler extends Kontroler
{
    public function Tampilkan(Request $permintaan): Response
    {
        $pengguna = $this->Pengguna($permintaan);

        return Inertia::render('Autentikasi/GantiKataSandi', ['Wajib' => $pengguna->WajibGantiKataSandi, 'TanpaKataSandiLama' => $pengguna->KataSandiOtomatis]);
    }

    public function Simpan(GantiKataSandiPermintaan $permintaan, GantiKataSandiPengguna $ganti): RedirectResponse
    {
        $idTenant = $permintaan->session()->get(IdentifikasiTenantSesi::KUNCI_SESI);
        $ganti->Jalankan(
            $this->Pengguna($permintaan),
            $permintaan->string('KataSandiLama')->toString(),
            $permintaan->string('KataSandi')->toString(),
            is_int($idTenant) ? $idTenant : null,
            $permintaan->ip(),
            $permintaan->userAgent(),
        );

        return redirect()->route('kelola.beranda')->with('Kilat', 'Kata sandi diganti.');
    }

    private function Pengguna(Request $permintaan): Pengguna
    {
        $pengguna = $permintaan->user();
        abort_unless($pengguna instanceof Pengguna, 403);

        return $pengguna;
    }
}
