<?php

declare(strict_types=1);

use App\Domain\Integrasi\Layanan\PencariGerbangWebhook;
use App\Domain\Lisensi\Enum\EdisiAplikasi;
use App\Http\Kontroler\Publik\LaporanCspKontroler;
use App\Http\Kontroler\Publik\WebhookBillingKontroler;
use App\Http\Kontroler\Publik\WebhookGerbangPembayaranKontroler;
use Illuminate\Support\Facades\Route;

/*
 * Webhook masuk dari layanan luar (PRD §16.6), didaftarkan dari bootstrap/app.php dengan grup `api` (tanpa sesi/CSRF).
 * Keaslian diverifikasi per penyedia (tanda tangan/token), bukan lewat login.
 */

// Hanya DOKU (keputusan pemilik produk, menggantikan D-19): kode penyedia lain tidak cocok dengan rute = 404.
$penyedia = 'doku';

// BR-P08.11: notifikasi gerbang billing platform (tagihan langganan Payoung sendiri). Akun gerbangnya milik platform,
// jadi URL-nya tunggal tanpa token dan tenant ditentukan dari nomor pesanan. Didaftarkan lebih dulu agar tidak pernah
// tertangkap pola `/webhook/{penyedia}/{tokenWebhook}` gerbang tenant di bawahnya. D-35: tidak ada di edisi Lisensi.
if (! EdisiAplikasi::CekLisensi()) {
    Route::post('/webhook/billing/midtrans', [WebhookBillingKontroler::class, 'Terima'])
        ->middleware('throttle:webhook')
        ->name('webhook.billing.midtrans');
}

// F-08 BR-08.5, v2.06: notifikasi gerbang pembayaran QRIS dinamis milik tenant (kode adaptor huruf kecil + token
// webhook tenant). URL ini ditampilkan di back-office tenant untuk disalin ke dasbor penyedia.
Route::post('/webhook/{penyedia}/{tokenWebhook}', [WebhookGerbangPembayaranKontroler::class, 'TerimaTenant'])
    ->where(['penyedia' => $penyedia, 'tokenWebhook' => PencariGerbangWebhook::POLA_TOKEN])
    ->middleware('throttle:webhook')
    ->name('webhook.gerbang-pembayaran.tenant');

// Rute lama gerbang tingkat platform (sebelum v2.06): selalu 404 `PenyediaTidakAktif`.
Route::post('/webhook/{penyedia}', [WebhookGerbangPembayaranKontroler::class, 'Terima'])
    ->where('penyedia', $penyedia)
    ->middleware('throttle:webhook')
    ->name('webhook.gerbang-pembayaran');

// Audit PAY-P1-04: laporan pelanggaran CSP Report-Only dari peramban (`report-uri`), publik & tanpa sesi.
Route::post('/laporan-csp', [LaporanCspKontroler::class, 'Terima'])
    ->middleware('throttle:laporan-csp')
    ->name('publik.laporan-csp');
