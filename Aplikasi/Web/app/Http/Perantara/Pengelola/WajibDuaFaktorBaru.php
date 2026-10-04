<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * D-42 (BR-P01.2): aksi berbahaya di konsol (anggota tim, kredensial integrasi, tangguhkan/aktifkan tenant,
 * perangkat tepercaya anggota lain) meminta kode 2FA lagi bila kode terakhir di sesi ini dimasukkan lebih dari
 * `MenitKonfirmasiDuaFaktor` menit lalu. Login lewat perangkat tepercaya tidak dihitung sebagai memasukkan kode.
 * Anggota diarahkan ke halaman konfirmasi lalu kembali ke halaman asal untuk mengulang aksinya.
 */
final class WajibDuaFaktorBaru
{
    public function handle(Request $request, Closure $next): Response
    {
        $sesi = $request->session();
        $kodePada = $sesi->get(SesiPengelola::KODE_DUA_FAKTOR_PADA);
        $batasDetik = (int) config('pengelola.MenitKonfirmasiDuaFaktor') * 60;

        if (is_int($kodePada) && now()->getTimestamp() - $kodePada <= $batasDetik) {
            return $next($request);
        }

        $sesi->put(
            SesiPengelola::KEMBALI_SETELAH_KONFIRMASI,
            $request->isMethod('GET') ? $request->fullUrl() : url()->previous(route('pengelola.beranda')),
        );

        return redirect()->route('pengelola.dua-faktor.konfirmasi');
    }
}
