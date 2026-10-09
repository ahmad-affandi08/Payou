<?php

declare(strict_types=1);

use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\PenyediaIntegrasi;
use App\Domain\Pengelola\Integrasi\Layanan\PenerapKonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Migrasi 2026_11_23_000173_HapusDataGerbangBillingMidtrans: tagihan langganan pindah dari Midtrans ke DOKU, jadi baris
 * konfigurasi integrasi `GerbangBilling` bernilai `MidtransBilling` dihapus (nilai yang tidak lagi dikenal enum akan
 * membuat pembacaan baris gagal). Baris DOKU dan jenis lain tidak boleh tersentuh. Idempoten dan aman bila tabel kosong.
 */

function JalankanMigrasiHapusBillingMidtrans(): void
{
    $migrasi = require database_path('migrations/2026_11_23_000173_HapusDataGerbangBillingMidtrans.php');
    $migrasi->up();
}

function SisipkanKonfigurasiBillingMentah(string $jenis, string $lingkungan, string $penyedia): void
{
    DB::table('KonfigurasiIntegrasi')->insert([
        'Uuid' => (string) Str::ulid(),
        'Jenis' => $jenis,
        'Lingkungan' => $lingkungan,
        'Penyedia' => $penyedia,
        'Pengaturan' => '{}',
        'Kredensial' => 'terenkripsi-uji',
        'PetunjukKredensial' => '{}',
        'KredensialDiubahPada' => now(),
        'DibuatPada' => now(),
        'DiubahPada' => now(),
    ]);
}

it('menghapus baris GerbangBilling Midtrans lama saja; DOKU dan jenis lain tidak tersentuh', function (): void {
    SisipkanKonfigurasiBillingMentah('GerbangBilling', 'Staging', 'MidtransBilling');
    SisipkanKonfigurasiBillingMentah('GerbangBilling', 'Produksi', 'DokuBilling');
    SisipkanKonfigurasiBillingMentah('Whatsapp', 'Staging', 'MetaCloud');

    JalankanMigrasiHapusBillingMidtrans();

    expect(DB::table('KonfigurasiIntegrasi')->orderBy('Id')->pluck('Penyedia', 'Jenis')->all())
        ->toBe(['GerbangBilling' => 'DokuBilling', 'Whatsapp' => 'MetaCloud'])
        ->and(KonfigurasiIntegrasi::query()->where('Jenis', JenisIntegrasi::GerbangBilling->value)->sole()->Penyedia)
        ->toBe(PenyediaIntegrasi::DokuBilling);

    // Bisa diulang tanpa efek.
    JalankanMigrasiHapusBillingMidtrans();
    expect(DB::table('KonfigurasiIntegrasi')->count())->toBe(2);
});

it('aman dijalankan bila tabel kosong', function (): void {
    JalankanMigrasiHapusBillingMidtrans();

    expect(DB::table('KonfigurasiIntegrasi')->count())->toBe(0);
});

it('boot aplikasi tidak jatuh oleh baris aktif berpenyedia lama, sehingga `php artisan migrate` bisa berjalan', function (): void {
    // Produksi: baris lama masih ada saat kode baru sudah terpasang dan migrasi pembersih BELUM dijalankan. Penerap
    // konfigurasi membaca baris aktif di boot; nilai yang tidak lagi dikenal enum tidak boleh melempar ValueError.
    DB::table('KonfigurasiIntegrasi')->insert([
        'Uuid' => (string) Str::ulid(),
        'Jenis' => 'GerbangBilling',
        'Lingkungan' => LingkunganIntegrasi::AmbilSaatIni()->value,
        'Penyedia' => 'MidtransBilling',
        'Aktif' => true,
        'Pengaturan' => '{}',
        'Kredensial' => Crypt::encryptString('{}'),
        'PetunjukKredensial' => '{}',
        'KredensialDiubahPada' => now(),
        'DibuatPada' => now(),
        'DiubahPada' => now(),
    ]);
    Cache::flush();

    app(PenerapKonfigurasiIntegrasi::class)->Terapkan();

    expect(config('integrasi.GerbangBilling.Penyedia'))->not->toBe('MidtransBilling');

    JalankanMigrasiHapusBillingMidtrans();
    expect(DB::table('KonfigurasiIntegrasi')->count())->toBe(0);
});
