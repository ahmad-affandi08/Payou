<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Lisensi\Enum\EdisiAplikasi;
use App\Domain\Lisensi\Kueri\LisensiBerlaku;
use App\Http\Respons\GalatApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * D-35 edisi Lisensi: semua permintaan (back-office, API POS & Pemilik, webhook, halaman publik toko) ditolak 503
 * sampai berkas lisensi yang sah terpasang, dan hanya dilayani di domain yang tertulis di lisensi. Di edisi SaaS
 * perantara ini tidak berbuat apa-apa. Cek kesehatan `/sehat` selalu lolos agar pemantauan server tetap jalan.
 */
final class WajibLisensiSah
{
    public function __construct(private readonly LisensiBerlaku $lisensiBerlaku) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! EdisiAplikasi::CekLisensi() || $request->is('sehat')) {
            return $next($request);
        }

        $lisensi = $this->lisensiBerlaku->Ambil();

        if ($lisensi === null) {
            return $this->Tolak($request, 'LisensiBelumTerpasang', 'Lisensi Payoung belum terpasang di server ini. Jalankan: php artisan lisensi:pasang');
        }

        if (! $lisensi->CekDomainCocok($request->getHost())) {
            return $this->Tolak($request, 'DomainLisensiBerbeda', "Lisensi Payoung ini untuk domain {$lisensi->domain}. Buka aplikasi lewat domain tersebut.");
        }

        return $next($request);
    }

    private function Tolak(Request $request, string $kode, string $pesan): Response
    {
        return $request->is('api/*') || $request->expectsJson()
            ? GalatApi::Buat($kode, $pesan, 503)
            : response($pesan, 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
