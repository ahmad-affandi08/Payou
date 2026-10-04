<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Domain\Pengelola\TimInternal\Aksi\CatatMasukPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PenjagaPerangkatTepercaya;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\Pengelola\SesiPengelola;
use App\Http\Permintaan\Pengelola\MasukPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Masuk & keluar Platform Pengelola (P-01, BR-P01.2, BR-P01.4). Tidak ada halaman daftar publik.
 */
final class SesiKontroler extends Kontroler
{
    private const MAKS_PERCOBAAN = 5;

    /** Audit F-24: batas gagal global per IP (banyak email dari satu IP) & per akun (dari banyak IP), per 15 menit. */
    private const MAKS_GAGAL_PER_IP = 20;

    private const MAKS_GAGAL_PER_AKUN = 10;

    private const DETIK_JENDELA = 900;

    public function TampilkanMasuk(): Response
    {
        return Inertia::render('Pengelola/Masuk');
    }

    public function Masuk(
        MasukPermintaan $permintaan,
        CatatMasukPengelola $catatMasuk,
        PenjagaPerangkatTepercaya $perangkatTepercaya,
        PencatatAuditPengelola $audit,
    ): RedirectResponse {
        $email = Str::lower(trim($permintaan->string('Email')->toString()));
        $kunciBatas = 'pengelola-masuk:'.$email.'|'.$permintaan->ip();
        $kunciIp = 'pengelola-masuk-ip:'.$permintaan->ip();
        $kunciAkun = 'pengelola-masuk-akun:'.hash('sha256', $email);

        foreach ([[$kunciBatas, self::MAKS_PERCOBAAN], [$kunciIp, self::MAKS_GAGAL_PER_IP], [$kunciAkun, self::MAKS_GAGAL_PER_AKUN]] as [$kunci, $maks]) {
            if (RateLimiter::tooManyAttempts($kunci, $maks)) {
                if ($kunci !== $kunciBatas) {
                    Log::warning('Percobaan masuk konsol pengelola diblokir sementara (dugaan penebakan kata sandi).', [
                        'Batas' => $kunci === $kunciIp ? 'PerIp' : 'PerAkun',
                        'Ip' => $permintaan->ip(),
                    ]);
                }

                throw ValidationException::withMessages([
                    'Email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.',
                ]);
            }
        }

        $berhasil = Auth::guard(SesiPengelola::GUARD)->attempt([
            'Email' => $email,
            'password' => $permintaan->string('KataSandi')->toString(),
            'Aktif' => true,
        ]);

        if (! $berhasil) {
            RateLimiter::hit($kunciBatas);
            RateLimiter::hit($kunciIp, self::DETIK_JENDELA);
            RateLimiter::hit($kunciAkun, self::DETIK_JENDELA);

            throw ValidationException::withMessages(['Email' => 'Email atau kata sandi salah.']);
        }

        RateLimiter::clear($kunciBatas);

        $sesi = $permintaan->session();
        $sesi->regenerate();
        $sesi->forget(SesiPengelola::DUA_FAKTOR_TERVERIFIKASI);
        $sesi->put(SesiPengelola::TERAKHIR_AKTIF, now()->getTimestamp());

        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        if ($pengguna instanceof PenggunaPengelola) {
            $catatMasuk->Jalankan($pengguna);
            $this->PakaiPerangkatTepercaya($permintaan, $pengguna, $perangkatTepercaya, $audit);
        }

        return redirect()->intended(route('pengelola.beranda'));
    }

    /**
     * D-42: browser yang sudah dipercaya akun ini melewati langkah kode 2FA. Cookie yang tidak berlaku lagi
     * (dicabut, kedaluwarsa, milik akun lain) dihapus supaya tidak dicoba terus.
     */
    private function PakaiPerangkatTepercaya(
        MasukPermintaan $permintaan,
        PenggunaPengelola $pengguna,
        PenjagaPerangkatTepercaya $perangkatTepercaya,
        PencatatAuditPengelola $audit,
    ): void {
        $namaCookie = (string) config('pengelola.CookiePerangkatTepercaya');
        $nilaiCookie = $permintaan->cookie($namaCookie);

        if (! is_string($nilaiCookie) || ! $pengguna->CekDuaFaktorAktif()) {
            return;
        }

        $perangkat = $perangkatTepercaya->Cocokkan($pengguna, $nilaiCookie, $permintaan->ip());

        if ($perangkat === null) {
            Cookie::queue(Cookie::forget($namaCookie));

            return;
        }

        $permintaan->session()->put(SesiPengelola::DUA_FAKTOR_TERVERIFIKASI, true);
        $audit->Catat('sesi.masuk-perangkat-tepercaya', $perangkat, nilaiBaru: ['Keterangan' => $perangkat->Keterangan], idPelaku: $pengguna->Id);
    }

    public function Keluar(Request $permintaan, PencatatAuditPengelola $audit): RedirectResponse
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        if ($pengguna instanceof PenggunaPengelola) {
            $audit->Catat('sesi.keluar', $pengguna, idPelaku: $pengguna->Id);
        }

        Auth::guard(SesiPengelola::GUARD)->logout();
        $permintaan->session()->invalidate();
        $permintaan->session()->regenerateToken();

        return redirect()->route('pengelola.masuk');
    }
}
