<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Domain\Pengelola\TimInternal\Layanan\PenjagaPerangkatTepercaya;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\PerangkatTepercayaPengelola;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\Pengelola\SesiPengelola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Keamanan akun konsol (D-42): perangkat tepercaya milik sendiri, cabut satu atau semua, dan tautan ganti
 * kata sandi. Mencabut tidak memerlukan konfirmasi 2FA karena hanya membuat akun lebih ketat.
 */
final class KeamananKontroler extends Kontroler
{
    public function Tampilkan(Request $permintaan): Response
    {
        $pengguna = $this->PenggunaMasuk();
        $uuidIni = PenjagaPerangkatTepercaya::AmbilUuid($this->NilaiCookie($permintaan));

        $perangkat = PerangkatTepercayaPengelola::query()
            ->where('IdPenggunaPengelola', $pengguna->Id)
            ->whereNull('DicabutPada')
            ->where('BerlakuSampai', '>', now())
            ->orderByDesc('TerakhirDipakaiPada')
            ->get()
            ->map(fn (PerangkatTepercayaPengelola $baris): array => [
                'Uuid' => $baris->Uuid,
                'Keterangan' => $baris->Keterangan,
                'AlamatIp' => $baris->AlamatIp,
                'DibuatPada' => $baris->DibuatPada?->toIso8601String(),
                'TerakhirDipakaiPada' => $baris->TerakhirDipakaiPada?->toIso8601String(),
                'BerlakuSampai' => $baris->BerlakuSampai->toIso8601String(),
                'PerangkatIni' => $baris->Uuid === $uuidIni,
            ])
            ->values();

        return Inertia::render('Pengelola/Keamanan', [
            'Perangkat' => $perangkat,
            'HariPerangkatTepercaya' => PenjagaPerangkatTepercaya::HariBerlaku(),
            'MenitKonfirmasi' => (int) config('pengelola.MenitKonfirmasiDuaFaktor'),
        ]);
    }

    public function CabutPerangkat(Request $permintaan, string $perangkat, PenjagaPerangkatTepercaya $penjaga): RedirectResponse
    {
        $pengguna = $this->PenggunaMasuk();
        $baris = PerangkatTepercayaPengelola::query()
            ->where('IdPenggunaPengelola', $pengguna->Id)
            ->where('Uuid', $perangkat)
            ->firstOrFail();

        $penjaga->Cabut($baris, $pengguna->Id);

        if ($baris->Uuid === PenjagaPerangkatTepercaya::AmbilUuid($this->NilaiCookie($permintaan))) {
            Cookie::queue(Cookie::forget((string) config('pengelola.CookiePerangkatTepercaya')));
        }

        return back()->with('Kilat', "{$baris->Keterangan} tidak lagi dipercaya. Login berikutnya di sana wajib kode 2FA.");
    }

    public function CabutSemuaPerangkat(PenjagaPerangkatTepercaya $penjaga): RedirectResponse
    {
        $jumlah = $penjaga->CabutSemua($this->PenggunaMasuk(), 'Dicabut sendiri dari halaman Keamanan akun.');
        Cookie::queue(Cookie::forget((string) config('pengelola.CookiePerangkatTepercaya')));

        return back()->with('Kilat', $jumlah > 0 ? "{$jumlah} perangkat tepercaya dicabut." : 'Tidak ada perangkat tepercaya.');
    }

    private function NilaiCookie(Request $permintaan): ?string
    {
        $nilai = $permintaan->cookie((string) config('pengelola.CookiePerangkatTepercaya'));

        return is_string($nilai) ? $nilai : null;
    }

    private function PenggunaMasuk(): PenggunaPengelola
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();
        abort_unless($pengguna instanceof PenggunaPengelola, 403);

        return $pengguna;
    }
}
