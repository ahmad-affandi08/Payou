<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Kasir;

use App\Domain\Organisasi\Kueri\DaftarMerekTenant;
use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\Tenant\Aksi\UbahPengaturanStruk;
use App\Domain\Tenant\Kueri\PengaturanStrukTenant;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use App\Domain\Tenant\Layanan\PenyimpanLogoTenant;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Permintaan\Kelola\Kasir\UbahPengaturanStrukPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman pengaturan struk (PLT-06, PRD v1.79), izin `outlet.kelola`: satu pengaturan untuk semua outlet dengan
 * pratinjau struk thermal. Data profil (nama usaha, NPWP, logo) hanya untuk pratinjau; diubah di profil usaha.
 */
final class PengaturanStrukKontroler extends DasarKelolaKontroler
{
    public function Tampilkan(Request $permintaan, PengaturanStrukTenant $pengaturan, ProfilTenant $profilTenant, PemeriksaFiturTenant $fitur, OutletUtama $outletUtama, DaftarMerekTenant $daftarMerek): Response
    {
        $profil = $profilTenant->Ambil($this->IdTenant());
        $semuaMerek = $daftarMerek->Ambil();
        // D-70: pilihan merek hanya muncul bila tenant punya lebih dari satu merek.
        $merek = count($semuaMerek) > 1 ? self::CariMerek($semuaMerek, $permintaan->query('Merek')) ?? $semuaMerek[0] : null;
        $pathLogoMerek = $merek === null ? null : $pengaturan->AmbilPathLogoMerek($this->IdTenant(), $merek['Id']);
        $pathLogo = $pathLogoMerek ?? $profil['PathLogo'];

        return Inertia::render('Kelola/Kasir/Struk', [
            'Pengaturan' => $pengaturan->Ambil(null, $merek['Id'] ?? null)->KeLarik(),
            'Merek' => $merek === null ? [] : array_map(static fn (array $m): array => ['Uuid' => $m['Uuid'], 'Nama' => $m['Nama']], $semuaMerek),
            'UuidMerek' => $merek['Uuid'] ?? null,
            'LogoMerekKhusus' => $pathLogoMerek !== null,
            'Profil' => [
                'NamaUsaha' => $merek['Nama'] ?? $profil['Nama'],
                'Npwp' => $profil['Npwp'],
                'NamaOutlet' => $outletUtama->CariAktifRingkas($outletUtama->AmbilId())?->nama,
                'TautanLogo' => $pathLogo === null ? null : route('kelola.kasir.struk.logo', ['v' => substr(hash('sha256', $pathLogo), 0, 12), 'Merek' => $merek['Uuid'] ?? null]),
                'TandaAir' => ! $fitur->CekAktif($this->IdTenant(), 'struk.tanpa-watermark'),
            ],
        ]);
    }

    /** Logo yang dipakai merek terpilih (logo khusus merek, selain itu logo usaha), untuk pratinjau. */
    public function UnduhLogo(Request $permintaan, PengaturanStrukTenant $pengaturan, ProfilTenant $profilTenant, DaftarMerekTenant $daftarMerek, PenyimpanLogoTenant $penyimpan): StreamedResponse
    {
        $merek = self::CariMerek($daftarMerek->Ambil(), $permintaan->query('Merek'));
        $path = ($merek === null ? null : $pengaturan->AmbilPathLogoMerek($this->IdTenant(), $merek['Id'])) ?? $profilTenant->Ambil($this->IdTenant())['PathLogo'];
        abort_if($path === null, 404);

        return $penyimpan->Unduh($path);
    }

    public function Simpan(UbahPengaturanStrukPermintaan $permintaan, UbahPengaturanStruk $ubah, DaftarMerekTenant $daftarMerek): RedirectResponse
    {
        $merek = self::CariMerek($daftarMerek->Ambil(), $permintaan->input('UuidMerek'));
        $ubah->Jalankan($permintaan->AmbilData(), $merek['Id'] ?? null, $permintaan->file('Logo'), $permintaan->boolean('HapusLogo'));

        return to_route('kelola.kasir.struk', $merek === null ? [] : ['Merek' => $merek['Uuid']])->with('Kilat', 'Pengaturan struk disimpan. Kasir memakainya setelah data diperbarui.');
    }

    /**
     * @param  list<array{Id: int, Uuid: string, Nama: string}>  $semua
     * @return array{Id: int, Uuid: string, Nama: string}|null
     */
    private static function CariMerek(array $semua, mixed $uuid): ?array
    {
        foreach (is_string($uuid) ? $semua : [] as $merek) {
            if ($merek['Uuid'] === $uuid) {
                return $merek;
            }
        }

        return null;
    }
}
