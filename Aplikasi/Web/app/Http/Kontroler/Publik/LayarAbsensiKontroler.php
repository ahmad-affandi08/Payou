<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\LayarAbsensiOutlet;
use App\Domain\Organisasi\Kueri\NamaTampilUsaha;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Kontroler\Kontroler;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * F-18 bagian 4 (D-37) layar QR absensi outlet `/{slugTenant}/layar-absen/{token}`: dibuka di tablet/monitor outlet
 * tanpa masuk akun. Menampilkan QR + 6 angka yang berganti tiap 30 detik; halaman mengambil kode baru lewat `/kode`.
 * Tautan tak dikenal atau dicabut = 404.
 */
final class LayarAbsensiKontroler extends Kontroler
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
        private readonly LayarAbsensiOutlet $layar,
    ) {}

    public function Tampilkan(string $slugTenant, string $token): SymfonyResponse
    {
        $isi = $this->AmbilIsi($slugTenant, $token);
        $namaToko = app(NamaTampilUsaha::class)->UntukTenant($isi['IdTenant']);
        unset($isi['IdTenant']);

        $respons = Inertia::render('Publik/LayarAbsensi', [
            ...$isi,
            'NamaToko' => $namaToko,
            'AlamatKode' => url("/{$slugTenant}/layar-absen/{$token}/kode"),
        ])->toResponse(request());
        $respons->headers->set('Cache-Control', 'no-store');

        return $respons;
    }

    public function Kode(string $slugTenant, string $token): JsonResponse
    {
        $isi = $this->AmbilIsi($slugTenant, $token);
        unset($isi['IdTenant']);

        return response()->json($isi, 200, ['Cache-Control' => 'no-store']);
    }

    /** @return array{IdTenant: int, NamaOutlet: string, WajibQr: bool, Kode: string, BerlakuSampai: string, Qr: string} */
    private function AmbilIsi(string $slugTenant, string $token): array
    {
        $idTenant = $this->profil->CariIdDariSlug($slugTenant);
        abort_if($idTenant === null, 404);
        $this->konteks->Atur($idTenant);

        try {
            $isi = $this->layar->Ambil($token, CarbonImmutable::now());
            abort_if($isi === null, 404);

            return ['IdTenant' => $idTenant, ...$isi];
        } finally {
            $this->konteks->Kosongkan();
        }
    }
}
