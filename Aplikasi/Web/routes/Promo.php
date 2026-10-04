<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Kontroler\Kelola\Promo\KlaimPemasokKontroler;
use App\Http\Kontroler\Kelola\Promo\PromoKontroler;
use App\Http\Kontroler\Kelola\Promo\VoucherKontroler;
use App\Http\Perantara\SiapkanAuditTenant;
use App\Http\Perantara\WajibIzinTenant;
use Illuminate\Support\Facades\Route;

/*
 * Rute back-office F-16c promo (PRD "Rincian F-16c", D-06). Didaftarkan dari routes/web.php di dalam grup `/kelola`.
 * Lihat: `pelanggan.lihat`; tambah/ubah/arsip & pengaturan: `pelanggan.kelola`. Promo tenant lain = 404 (`MilikTenant`).
 * F-16c bagian 2: voucher per promo (lihat: `pelanggan.lihat`; tambah, nonaktifkan, ekspor CSV: `pelanggan.kelola`).
 * F-16c bagian 4b: klaim promo pemasok (lihat: `pelanggan.lihat`; catat penerimaan: `akuntansi.kelola`).
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
$ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

Route::middleware([SiapkanAuditTenant::class, $izin(IzinTenant::PelangganLihat)])->prefix('promo')->group(function () use ($izin, $ulid): void {
    Route::get('/', [PromoKontroler::class, 'Daftar'])->name('kelola.promo.daftar');
    Route::get('/{promo}/voucher', [VoucherKontroler::class, 'Daftar'])->where('promo', $ulid)->name('kelola.promo.voucher.daftar');
    Route::get('/klaim-pemasok', [KlaimPemasokKontroler::class, 'Daftar'])->name('kelola.promo.klaim-pemasok');
    Route::get('/{promo}/efektivitas', [PromoKontroler::class, 'Efektivitas'])->where('promo', $ulid)->name('kelola.promo.efektivitas');
    Route::post('/klaim-pemasok/penerimaan', [KlaimPemasokKontroler::class, 'Terima'])
        ->middleware([$izin(IzinTenant::AkuntansiKelola), 'throttle:60,1'])
        ->name('kelola.promo.klaim-pemasok.terima');

    Route::middleware($izin(IzinTenant::PelangganKelola))->group(function () use ($ulid): void {
        Route::get('/buat', [PromoKontroler::class, 'Buat'])->name('kelola.promo.buat');
        Route::post('/', [PromoKontroler::class, 'Simpan'])->name('kelola.promo.simpan');
        Route::put('/pengaturan', [PromoKontroler::class, 'SimpanPengaturan'])->name('kelola.promo.pengaturan');
        Route::post('/massal', [PromoKontroler::class, 'Massal'])->name('kelola.promo.massal');
        Route::post('/{promo}/voucher/massal', [VoucherKontroler::class, 'Massal'])->where('promo', $ulid)->name('kelola.promo.voucher.massal');
        Route::post('/{promo}/voucher/nonaktifkan-kedaluwarsa', [VoucherKontroler::class, 'NonaktifkanKedaluwarsa'])->where('promo', $ulid)->name('kelola.promo.voucher.nonaktifkan-kedaluwarsa');
        Route::get('/{promo}/ubah', [PromoKontroler::class, 'Ubah'])->where('promo', $ulid)->name('kelola.promo.ubah');
        Route::put('/{promo}', [PromoKontroler::class, 'Perbarui'])->where('promo', $ulid)->name('kelola.promo.perbarui');
        Route::post('/{promo}/arsipkan', [PromoKontroler::class, 'Arsipkan'])->where('promo', $ulid)->name('kelola.promo.arsipkan');
        Route::post('/{promo}/pulihkan', [PromoKontroler::class, 'Pulihkan'])->where('promo', $ulid)->name('kelola.promo.pulihkan');
        Route::post('/{promo}/voucher', [VoucherKontroler::class, 'Simpan'])->where('promo', $ulid)->name('kelola.promo.voucher.simpan');
        Route::get('/{promo}/voucher/ekspor', [VoucherKontroler::class, 'Ekspor'])->where('promo', $ulid)->name('kelola.promo.voucher.ekspor');
        Route::post('/voucher/{voucher}/nonaktifkan', [VoucherKontroler::class, 'Nonaktifkan'])->where('voucher', $ulid)->name('kelola.promo.voucher.nonaktifkan');
        Route::post('/voucher/{voucher}/aktifkan', [VoucherKontroler::class, 'Aktifkan'])->where('voucher', $ulid)->name('kelola.promo.voucher.aktifkan');
    });
});
