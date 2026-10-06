<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Situs\Kueri\PenyusunHalamanSitus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Editor visual (D-63): pratinjau draf situs ditampilkan dalam bingkai di konsol pengelola (host berbeda dari situs).
 * Hanya rute pratinjau bertanda tangan yang mengizinkan bingkai, dan hanya dari asal konsol; semua rute lain tetap
 * `frame-ancestors 'self'` (lihat `PasangHeaderKeamanan`). Browser modern memprioritaskan `frame-ancestors` di atas
 * `X-Frame-Options`, jadi `SAMEORIGIN` bawaan tidak menghalangi bingkai ini.
 */
final class PasangBingkaiPratinjau
{
    public function handle(Request $request, Closure $next): Response
    {
        $respons = $next($request);
        $respons->headers->set('Content-Security-Policy', "frame-ancestors 'self' ".PenyusunHalamanSitus::AmbilAsalEditor()."; base-uri 'self'; object-src 'none'");
        $respons->headers->set('Cache-Control', 'no-store, private');

        return $respons;
    }
}
