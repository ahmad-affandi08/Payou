<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Surel\UpayaPendaftaranAkunTerdaftar;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Memberi tahu pemilik akun bahwa email/nomor WhatsApp-nya dipakai untuk mendaftar lagi (BR-00.1, §25 no. 18).
 * Paling banyak satu email per akun per jam agar formulir daftar tidak bisa dipakai untuk membanjiri kotak masuk orang.
 */
final class BeritahuUpayaPendaftaranGanda
{
    public const DETIK_JEDA_PEMBERITAHUAN = 3600;

    /**
     * @param  array<int, list<string>>  $identitasPerPengguna  IdPengguna => label identitas yang cocok
     */
    public function Jalankan(array $identitasPerPengguna): void
    {
        foreach ($identitasPerPengguna as $idPengguna => $identitas) {
            $kunci = 'pemberitahuan-daftar-ganda:'.$idPengguna;

            if (RateLimiter::tooManyAttempts($kunci, 1)) {
                continue;
            }

            $pengguna = Pengguna::query()->find($idPengguna);

            if (! $pengguna instanceof Pengguna) {
                continue;
            }

            RateLimiter::hit($kunci, self::DETIK_JEDA_PEMBERITAHUAN);

            try {
                Mail::to($pengguna->Email)->queue(new UpayaPendaftaranAkunTerdaftar($pengguna->Nama, $identitas));
            } catch (Throwable $galat) {
                // Pendaftar tetap melihat pesan umum yang sama; kegagalan email tidak boleh membuka apa pun.
                Log::error('Email pemberitahuan upaya pendaftaran gagal dikirim.', ['Pesan' => $galat->getMessage()]);
            }
        }
    }
}
