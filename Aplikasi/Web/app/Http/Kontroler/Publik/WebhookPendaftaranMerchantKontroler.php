<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Integrasi\Aksi\TerimaCallbackPendaftaranMerchant;
use App\Http\Kontroler\Kontroler;
use App\Http\Respons\GalatApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Callback hasil KYB dari DOKU Partner API (tanpa login/CSRF, dibatasi laju). URL `callback_url` dikirim otomatis saat
 * registrasi merchant: `/webhook/pembayaran/doku-partner?ref={token pendaftaran}`.
 *
 * Isi webhook tidak terdokumentasi dan tidak dipercaya: tanda tangan tidak sah = 401; callback sah hanya memicu
 * pembacaan status lewat Get Business Data. Pendaftaran tak dikenal dijawab 200 `{Diterima: false}` supaya DOKU
 * berhenti mengulang.
 */
final class WebhookPendaftaranMerchantKontroler extends Kontroler
{
    public function Terima(Request $permintaan, TerimaCallbackPendaftaranMerchant $terima): JsonResponse
    {
        $hasil = $terima->Jalankan($permintaan);

        if (! $hasil['Sah']) {
            return GalatApi::Buat('TandaTanganTidakSah', 'Tanda tangan notifikasi tidak sah.', 401);
        }

        return response()->json(['Diterima' => $hasil['Diterima']]);
    }
}
