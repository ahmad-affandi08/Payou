<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Web\AlamatDomain;
use App\Domain\Organisasi\Layanan\PemeriksaAlamatEmail;
use App\Domain\Organisasi\Layanan\PenandaVerifikasiEmail;
use App\Domain\Organisasi\Layanan\SandiEmailBaru;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Surel\GantiEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Meminta ganti email akun (BR-00.5): konfirmasi kata sandi, lalu tautan bertanda tangan dikirim ke **alamat baru**.
 * Email akun baru berubah setelah tautan itu dibuka, sehingga alamat salah ketik tidak pernah menggantikan yang lama.
 *
 * Tautan memuat hash email sekarang: tautan lama tidak berlaku lagi begitu email berubah. Alamat yang sudah dipakai
 * akun lain tidak dibedakan dari yang berhasil (tidak ada email terkirim, jawaban sama) supaya pengguna tidak bisa
 * memeriksa siapa yang terdaftar (§25 no. 18).
 */
final class KirimTautanGantiEmail
{
    public function __construct(
        private readonly PemeriksaAlamatEmail $pemeriksa,
        private readonly PenandaVerifikasiEmail $penanda,
        private readonly SandiEmailBaru $sandi,
    ) {}

    public function Jalankan(Pengguna $pengguna, string $emailBaru, string $kataSandi): void
    {
        if ($pengguna->KataSandiOtomatis) {
            throw new PelanggaranAturanBisnis('KataSandiBelumDiatur', 'Atur kata sandi dulu di halaman ini sebelum mengganti email.', 'KataSandi');
        }

        if (! Hash::check($kataSandi, $pengguna->KataSandi)) {
            throw new PelanggaranAturanBisnis('KataSandiSalah', 'Kata sandi salah.', 'KataSandi');
        }

        $emailBaru = mb_strtolower(trim($emailBaru));

        if ($emailBaru === mb_strtolower((string) $pengguna->Email)) {
            throw new PelanggaranAturanBisnis('EmailSama', 'Email baru sama dengan email Anda saat ini.', 'Email');
        }

        $alasan = $this->pemeriksa->AmbilAlasanTolak($emailBaru);

        if ($alasan !== null) {
            throw new PelanggaranAturanBisnis('EmailTidakValid', $alasan, 'Email');
        }

        if (Pengguna::query()->where('Email', $emailBaru)->exists()) {
            return;
        }

        $jam = (int) config('tenant.JamBerlakuVerifikasiEmail');
        $relatif = URL::temporarySignedRoute('ganti-email', now()->addHours($jam), [
            'pengguna' => $pengguna->Uuid,
            'hash' => $this->penanda->BuatHash($pengguna),
            'email' => $this->sandi->Sandikan($emailBaru),
        ], false);

        Mail::to($emailBaru)->queue(new GantiEmail($pengguna->Nama, AlamatDomain::BuatUrlAbsolutTenant($relatif), $jam));
    }
}
