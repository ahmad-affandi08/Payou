<?php

declare(strict_types=1);

use App\Domain\Pengelola\Konten\Aksi\SiapkanHalamanSitusBawaan;
use App\Providers\PenyediaAplikasi;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Routing\Router;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Rute ter-cache (`php artisan optimize`): matcher Symfony hanya memeriksa pola, jadi `ValidatorHalamanSitus` terlewati
 * dan `/{slugHalaman}` menangkap `/masuk` (dashboard dialihkan ke domain pemasaran lalu 404).
 * `PenyediaAplikasi::PakaiKoleksiRuteBiasa` memindahkan rute ter-cache ke koleksi biasa yang menjalankan validator.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    config([
        'app.url' => 'https://dashboard.payoung.test',
        'domain.Pemasaran' => 'payoung.test',
        'domain.Tenant' => 'dashboard.payoung.test',
    ]);
});

/** Memasang rute aplikasi sebagai rute ter-cache (seperti `route:cache`), lalu (opsional) perbaikan Payoung. */
function PasangRuteTerCache(bool $perbaiki): RouteCollectionInterface
{
    $router = app(Router::class);
    $rute = $router->getRoutes();
    if (! $rute instanceof RouteCollection) {
        throw new LogicException('Rute uji sudah ter-cache.');
    }

    $router->setCompiledRoutes($rute->compile());

    if ($perbaiki) {
        PenyediaAplikasi::PakaiKoleksiRuteBiasa($router);
    }

    return $router->getRoutes();
}

function NamaRuteCocok(RouteCollectionInterface $koleksi, string $url): ?string
{
    return $koleksi->match(Request::create($url))->getName();
}

describe('rute ter-cache tetap menjalankan validator halaman situs', function (): void {
    it('rute ter-cache bawaan Laravel salah menangkap /masuk sebagai halaman situs', function (): void {
        expect(NamaRuteCocok(PasangRuteTerCache(false), 'https://dashboard.payoung.test/masuk'))->toBe('situs.halaman');
    });

    it('setelah diperbaiki: /masuk, /daftar, back-office, halaman situs, dan toko online masing-masing ke rute yang benar', function (): void {
        app(SiapkanHalamanSitusBawaan::class)->Jalankan();
        $tenant = BantuanOrganisasi::BuatTenant('Warung Kopi Tebet Jaya');
        $koleksi = PasangRuteTerCache(true);

        expect($koleksi)->toBeInstanceOf(RouteCollection::class)
            ->and(NamaRuteCocok($koleksi, 'https://dashboard.payoung.test/masuk'))->toBe('masuk')
            ->and(NamaRuteCocok($koleksi, 'https://dashboard.payoung.test/daftar'))->toBe('daftar')
            ->and(NamaRuteCocok($koleksi, 'https://payoung.test/fitur'))->toBe('situs.halaman')
            ->and(NamaRuteCocok($koleksi, 'https://dashboard.payoung.test/'.$tenant['Tenant']->Slug))->toBe('publik.toko-online')
            ->and(NamaRuteCocok($koleksi, 'https://dashboard.payoung.test/kelola/produk'))->toBe('kelola.produk.daftar');
    });

    it('setelah diperbaiki, /masuk dashboard tampil (bukan dialihkan ke payoung.id)', function (): void {
        PasangRuteTerCache(true);

        $this->get('https://dashboard.payoung.test/masuk')->assertOk();
    });
});
