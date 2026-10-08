<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Aksi;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\GerbangPembayaran\ProtokolDoku;
use App\Domain\Integrasi\Model\PendaftaranMerchantPembayaran;
use App\Domain\Integrasi\Tugas\SegarkanPendaftaranMerchantTugas;
use Illuminate\Http\Request;

/**
 * Webhook hasil KYB dari DOKU Partner API (`POST /webhook/pembayaran/doku-partner?ref={token}`).
 *
 * Isi (payload) webhook TIDAK terdokumentasi, maka penerimanya defensif:
 *  - hanya tanda tangan yang diverifikasi (`Client-Id` = Brand ID partner kita, `Signature` atas path & badan mentah;
 *    bila DOKU menandatangani dengan query ikut, variannya juga diterima). Tanda tangan tidak sah = ditolak;
 *  - isi badan TIDAK dipercaya dan tidak dibaca untuk mengubah apa pun, apalagi mengaktifkan;
 *  - callback sah hanya menandai `CallbackDiterimaPada` lalu memicu `SegarkanPendaftaranMerchantTugas`, yang membaca
 *    status lewat Get Business Data (sumber kebenaran). Penyapu 30 menit menjadi jalur cadangan.
 * Tenant ditetapkan dari token `ref` di `callback_url` yang kita kirim saat registrasi (bagian tenant basis-36 hanya
 * menetapkan scope pencarian; token harus cocok persis).
 */
final class TerimaCallbackPendaftaranMerchant
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    /**
     * @return array{Sah: bool, Diterima: bool} `Sah` = tanda tangan benar; `Diterima` = pendaftaran dikenali dan penyegaran dijadwalkan
     */
    public function Jalankan(Request $permintaan): array
    {
        if (! $this->CekTandaTanganSah($permintaan)) {
            return ['Sah' => false, 'Diterima' => false];
        }

        $token = (string) $permintaan->query('ref', '');

        if (preg_match('/^([0-9a-z]{1,13})-[A-Za-z0-9]{40}$/', $token, $cocok) !== 1 || (int) base_convert($cocok[1], 36, 10) <= 0) {
            return ['Sah' => true, 'Diterima' => false];
        }

        $idTenant = (int) base_convert($cocok[1], 36, 10);
        $sebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($idTenant);

        try {
            $pendaftaran = PendaftaranMerchantPembayaran::query()->where('TokenCallback', $token)->first();

            if ($pendaftaran === null || ! hash_equals((string) $pendaftaran->TokenCallback, $token)) {
                return ['Sah' => true, 'Diterima' => false];
            }

            $pendaftaran->update(['CallbackDiterimaPada' => now()]);
        } finally {
            $sebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($sebelumnya);
        }

        SegarkanPendaftaranMerchantTugas::dispatch($idTenant);

        return ['Sah' => true, 'Diterima' => true];
    }

    private function CekTandaTanganSah(Request $permintaan): bool
    {
        $idKlien = trim((string) config('integrasi.PendaftaranMerchant.Pengaturan.IdKlien', ''));
        $kunci = trim((string) config('integrasi.PendaftaranMerchant.Kredensial.KunciRahasia', ''));

        if ($idKlien === '' || $kunci === '') {
            return false;
        }

        if (ProtokolDoku::CekNotifikasiSah($permintaan, $idKlien, $kunci)) {
            return true;
        }

        $query = (string) $permintaan->getQueryString();

        if ($query === '' || $permintaan->header('Client-Id') !== $idKlien) {
            return false;
        }

        $harapan = ProtokolDoku::Tandatangani(
            $idKlien,
            (string) $permintaan->header('Request-Id'),
            (string) $permintaan->header('Request-Timestamp'),
            '/'.ltrim($permintaan->path(), '/').'?'.$query,
            $permintaan->getContent(),
            $kunci,
        );

        return hash_equals($harapan, (string) $permintaan->header('Signature'));
    }
}
