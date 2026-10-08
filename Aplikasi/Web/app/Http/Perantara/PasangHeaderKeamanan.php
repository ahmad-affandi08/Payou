<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit F-14 (PRD §20): header keamanan di lapisan aplikasi untuk semua respons (tidak bergantung pada Cloudflare/web
 * server). `Content-Security-Policy` (penegak) berisi arahan yang aman untuk seluruh halaman (bingkai, `<base>`, plugin);
 * kebijakan ketat berisi daftar sumber & tujuan formulir dikirim sebagai `Content-Security-Policy-Report-Only` (lihat
 * `CSP_KETAT`) sampai laporannya bersih. HSTS hanya lewat HTTPS.
 * Header yang sudah diatur respons (misal unduhan) tidak ditimpa.
 */
final class PasangHeaderKeamanan
{
    public const CSP = "frame-ancestors 'self'; base-uri 'self'; object-src 'none'";

    /**
     * Kebijakan CSP ketat yang dikirim sebagai **Report-Only** (audit PAY-P1-04): tidak memblokir apa pun, tetapi setiap
     * pelanggaran dilaporkan ke `/laporan-csp` (dicatat di log). Daftar sumber mengikuti yang benar-benar dipakai:
     * GA4 & Meta Pixel (hanya setelah persetujuan cookie), Turnstile (CAPTCHA), YouTube nocookie (blok video situs).
     * Bayar langganan lewat DOKU Checkout hanya mengalihkan peramban ke halaman DOKU, jadi tidak butuh sumber tambahan. Gaya `'unsafe-inline'` diperlukan atribut `style=` React/Radix; skrip tidak
     * memakai `'unsafe-inline'`, jadi XSS yang menyuntik `<script>`/`onerror=` akan tampak di laporan. Setelah laporan
     * bersih beberapa minggu, arahan ini dijadikan `Content-Security-Policy` sungguhan.
     */
    public const CSP_KETAT = "default-src 'self'; script-src 'self' https://www.googletagmanager.com https://connect.facebook.net https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self' https://www.google-analytics.com https://*.google-analytics.com https://www.facebook.com; frame-src https://challenges.cloudflare.com https://www.youtube-nocookie.com; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; report-uri /laporan-csp";

    public const IZIN_FITUR = 'camera=(self), microphone=(), geolocation=(self), payment=(), usb=(), interest-cohort=()';

    public function handle(Request $request, Closure $next): Response
    {
        $respons = $next($request);
        $header = $respons->headers;

        foreach ([
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Permissions-Policy' => self::IZIN_FITUR,
            'Content-Security-Policy' => self::CSP,
            // Vite dev server (`npm run dev`) memuat skrip dari host lain; laporan lokal hanya akan berisik.
            ...(Vite::isRunningHot() ? [] : ['Content-Security-Policy-Report-Only' => self::CSP_KETAT]),
        ] as $nama => $nilai) {
            if (! $header->has($nama)) {
                $header->set($nama, $nilai);
            }
        }

        if ($request->isSecure() && ! $header->has('Strict-Transport-Security')) {
            $header->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respons;
    }
}
