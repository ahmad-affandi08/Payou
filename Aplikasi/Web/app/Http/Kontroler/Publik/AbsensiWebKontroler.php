<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Karyawan\Aksi\AturTautanAbsen;
use App\Domain\Karyawan\Aksi\CatatAbsensiWeb;
use App\Domain\Karyawan\Aksi\DaftarkanWajahKaryawan;
use App\Domain\Karyawan\Data\DataAbsensiWeb;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Kueri\StatusAbsensiWeb;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Organisasi\Kueri\NamaTampilUsaha;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Kontroler\Kontroler;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * F-18 bagian 4 (D-37) absensi web `/{slugTenant}/absen/{token}`: karyawan membuka tautan pribadinya di HP, mendaftarkan
 * wajah sekali, lalu absen masuk/keluar dengan lokasi GPS + wajah. Halaman mobile-first & dapat dipasang (PWA; hanya
 * cakupan halaman absen). Kiriman berbentuk JSON dari halaman; galat memakai format galat seragam. Tautan tak dikenal,
 * dicabut, atau karyawan nonaktif = 404 yang sama. Isi kiriman baru divalidasi setelah tautan terbukti sah.
 */
final class AbsensiWebKontroler extends Kontroler
{
    /** Pola derajat desimal (lintang & bujur) dari Geolocation API. */
    private const POLA_KOORDINAT = '/^-?\d{1,3}(\.\d{1,12})?$/';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
        private readonly PencatatAudit $audit,
    ) {}

    public function Tampilkan(string $slugTenant, string $token, StatusAbsensiWeb $status): SymfonyResponse
    {
        return $this->DenganKaryawan($slugTenant, $token, fn (Karyawan $karyawan, int $idTenant): SymfonyResponse => Inertia::render('Publik/Absensi', [
            'NamaToko' => app(NamaTampilUsaha::class)->UntukTenant($idTenant),
            'NamaKaryawan' => $karyawan->Nama,
            'AlamatDasar' => url("/{$slugTenant}/absen/{$token}"),
            'AlamatModelWajah' => asset('model-wajah'),
            ...$status->Ambil($karyawan),
        ])->toResponse(request()));
    }

    /** PWA (D-37): manifest per tautan, supaya ikon di layar utama langsung membuka halaman absen karyawan ini. */
    public function Manifest(string $slugTenant, string $token): SymfonyResponse
    {
        return $this->DenganKaryawan($slugTenant, $token, function (Karyawan $karyawan, int $idTenant) use ($slugTenant, $token): JsonResponse {
            $alamat = "/{$slugTenant}/absen/{$token}";
            $namaToko = app(NamaTampilUsaha::class)->UntukTenant($idTenant);

            return response()->json([
                'name' => "Absen {$namaToko}",
                'short_name' => 'Absen',
                'description' => "Absen masuk & keluar {$karyawan->Nama} di {$namaToko}",
                'lang' => 'id',
                'start_url' => $alamat,
                'scope' => $alamat,
                'id' => $alamat,
                'display' => 'standalone',
                'orientation' => 'portrait',
                // Sama dengan token `--color-brand-gelap` & `--color-permukaan` (D-15).
                'theme_color' => '#22383a',
                'background_color' => '#ffffff',
                'icons' => [
                    ['src' => '/ikon-pwa-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                    ['src' => '/ikon-pwa-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ],
            ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'private, max-age=3600']);
        });
    }

    /** PWA (D-37): service worker di jalur tautan sendiri, jadi cakupannya hanya halaman absen ini. */
    public function PekerjaLayanan(string $slugTenant, string $token): SymfonyResponse
    {
        return $this->DenganKaryawan($slugTenant, $token, fn (): SymfonyResponse => response(view('Pwa.PekerjaAbsensi')->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            // Halaman ada di `/…/absen/{token}` (tanpa garis miring akhir), satu tingkat di atas jalur skrip ini.
            'Service-Worker-Allowed' => "/{$slugTenant}/absen/{$token}",
        ]));
    }

    public function DaftarWajah(Request $permintaan, string $slugTenant, string $token, DaftarkanWajahKaryawan $daftar): SymfonyResponse
    {
        return $this->DenganKaryawan($slugTenant, $token, function (Karyawan $karyawan) use ($permintaan, $daftar): JsonResponse {
            $valid = $permintaan->validate([
                'SidikWajah' => ['required', 'array', 'max:'.(int) config('karyawan.JumlahFotoDaftarWajah')],
                'SidikWajah.*' => ['array', 'max:1024'],
                'SidikWajah.*.*' => ['integer'],
                'Foto' => ['required', 'array', 'max:'.(int) config('karyawan.JumlahFotoDaftarWajah')],
                'Foto.*' => ['string', 'max:450000'],
                'Persetujuan' => ['required', 'boolean'],
            ]);
            $wajah = $daftar->Jalankan($karyawan, array_values((array) $valid['SidikWajah']), array_values((array) $valid['Foto']), (bool) $valid['Persetujuan']);

            return response()->json(['Status' => $wajah->Status->value, 'Pesan' => 'Wajah terdaftar. Tunggu persetujuan pengelola sebelum absen.'], 201);
        });
    }

    public function Masuk(Request $permintaan, string $slugTenant, string $token, CatatAbsensiWeb $catat): SymfonyResponse
    {
        return $this->DenganKaryawan($slugTenant, $token, function (Karyawan $karyawan) use ($permintaan, $catat): JsonResponse {
            $absensi = $catat->Masuk($karyawan, $this->AmbilData($permintaan));

            return response()->json(['Uuid' => $absensi->Uuid, 'MasukPada' => $absensi->MasukPada->toIso8601String(), 'JarakMeter' => $absensi->JarakMasukMeter]);
        });
    }

    public function Keluar(Request $permintaan, string $slugTenant, string $token, CatatAbsensiWeb $catat): SymfonyResponse
    {
        return $this->DenganKaryawan($slugTenant, $token, function (Karyawan $karyawan) use ($permintaan, $catat): JsonResponse {
            $absensi = $catat->Keluar($karyawan, $this->AmbilData($permintaan));

            return response()->json(['Uuid' => $absensi->Uuid, 'KeluarPada' => $absensi->KeluarPada?->toIso8601String(), 'JarakMeter' => $absensi->JarakKeluarMeter]);
        });
    }

    private function AmbilData(Request $permintaan): DataAbsensiWeb
    {
        $valid = $permintaan->validate([
            'Uuid' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'Lintang' => ['required', 'string', 'regex:'.self::POLA_KOORDINAT],
            'Bujur' => ['required', 'string', 'regex:'.self::POLA_KOORDINAT],
            'AkurasiMeter' => ['required', 'integer', 'min:0', 'max:100000'],
            'SidikWajah' => ['required', 'array', 'max:1024'],
            'SidikWajah.*' => ['integer'],
            'Swafoto' => ['required', 'string', 'max:450000'],
            'KodeQr' => ['nullable', 'string', 'regex:/^\d{6}$/'],
        ]);
        $lintang = BigDecimal::of((string) $valid['Lintang']);
        $bujur = BigDecimal::of((string) $valid['Bujur']);

        if ($lintang->abs()->isGreaterThan(90) || $bujur->abs()->isGreaterThan(180)) {
            abort(422, 'Koordinat di luar jangkauan.');
        }

        return new DataAbsensiWeb(
            uuid: (string) $valid['Uuid'],
            lintang: (string) $lintang->toScale(7, RoundingMode::HalfUp),
            bujur: (string) $bujur->toScale(7, RoundingMode::HalfUp),
            akurasiMeter: (int) $valid['AkurasiMeter'],
            sidikWajah: array_values((array) $valid['SidikWajah']),
            swafoto: (string) $valid['Swafoto'],
            kodeQr: is_string($valid['KodeQr'] ?? null) ? $valid['KodeQr'] : null,
        );
    }

    /** @param Closure(Karyawan, int): SymfonyResponse $kerja */
    private function DenganKaryawan(string $slugTenant, string $token, Closure $kerja): SymfonyResponse
    {
        $idTenant = $this->profil->CariIdDariSlug($slugTenant);
        abort_if($idTenant === null || strlen($token) !== AturTautanAbsen::PANJANG_TOKEN, 404);
        $this->konteks->Atur($idTenant);
        // Halaman publik tanpa perantara audit: jejak percobaan (di luar radius, wajah tidak cocok) tetap ber-IP.
        $this->audit->AturKonteks(null, request()->ip(), request()->userAgent());

        try {
            $karyawan = Karyawan::query()->where('HashTokenAbsen', AturTautanAbsen::Hash($token))->first();
            abort_if(! $karyawan instanceof Karyawan || $karyawan->Status !== StatusKaryawan::Aktif, 404);

            return $kerja($karyawan, $idTenant);
        } finally {
            $this->konteks->Kosongkan();
        }
    }
}
