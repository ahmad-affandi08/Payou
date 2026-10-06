<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Integrasi\Billing\GerbangBillingPlatform;
use App\Domain\Tenant\Aksi\TerimaNotifikasiBillingLangganan;
use App\Http\Kontroler\Kontroler;
use App\Http\Respons\GalatApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BR-P08.11: notifikasi gerbang billing platform untuk tagihan langganan Payoung (tanpa login/CSRF, dibatasi laju).
 *
 * Berbeda dari `WebhookGerbangPembayaranKontroler` (QRIS milik toko, D-19) yang URL-nya bertoken per tenant: akun
 * gerbang di sini milik platform, jadi URL-nya tunggal dan tenant ditentukan dari nomor pesanan di dalam notifikasi.
 *
 * Aksinya berada di domain Tenant, bukan Pengelola: endpoint ini publik tanpa login, dan test arsitektur
 * `PengelolaTes` melarang kode di luar Platform Pengelola memakai domain Pengelola. Tenant ditetapkan dari nomor
 * pesanan sehingga pembayaran dicari lewat scope `MilikTenant` seperti biasa.
 *
 * - Tanda tangan tidak sah, gerbang belum dikonfigurasi, atau nomor pesanan bukan format Payoung = 401. Midtrans akan
 *   mengulang, yang memang diinginkan bila penyebabnya kredensial yang belum terpasang.
 * - Nomor pesanan yang sah tetapi tidak dikenal dijawab 200 `{Diterima: false}` supaya gerbang berhenti mengulang.
 */
final class WebhookBillingKontroler extends Kontroler
{
    public function Terima(Request $permintaan, GerbangBillingPlatform $gerbang, TerimaNotifikasiBillingLangganan $terima): JsonResponse
    {
        // Audit PAY-P2-07: tanda tangan diperiksa lebih dulu untuk SEMUA notifikasi, termasuk uji coba. Dulu balasan
        // "siap menerima" untuk order_id kosong/`test*`/`sample*` keluar tanpa bukti asal sehingga endpoint publik ini
        // bisa dipakai siapa saja untuk menguji keberadaannya. Notifikasi uji dari dasbor Midtrans ("Test notification
        // URL") ditandatangani dengan kunci server yang sama, jadi tetap lolos.
        if (! $gerbang->CekTandaTanganSah($permintaan)) {
            return GalatApi::Buat('TandaTanganTidakSah', 'Tanda tangan notifikasi tidak sah.', 401);
        }

        $nomor = (string) $permintaan->input('order_id');

        if ($nomor === '' || str_starts_with(strtolower($nomor), 'test') || str_starts_with(strtolower($nomor), 'sample')) {
            return response()->json(['Diterima' => true, 'Pesan' => 'Endpoint webhook billing Payoung siap menerima notifikasi.']);
        }

        $notifikasi = $gerbang->UraiNotifikasi($permintaan);

        if ($notifikasi === null) {
            return response()->json(['Diterima' => false]);
        }

        return response()->json(['Diterima' => $terima->Jalankan($notifikasi)]);
    }
}
