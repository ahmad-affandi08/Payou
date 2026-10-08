<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Web\AlamatDomain;
use App\Domain\Organisasi\Layanan\PenandaVerifikasiEmail;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Surel\VerifikasiEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Mengirim tautan verifikasi email bertanda tangan (BR-00.5). Tautan memuat hash email sehingga tidak berlaku lagi
 * bila email diganti.
 *
 * **Tanda tangan relatif** (D-20): jalurnya ditandatangani, domain tenant dipasang belakangan — sama seperti tautan
 * pratinjau situs. Tanda tangan absolut ikut menghitung skema & host, sehingga tautan menjadi "Invalid signature"
 * begitu host yang menandatangani berbeda dari host yang melayani (mis. `payoung.id` dialihkan ke `dashboard.payoung.id`
 * oleh `ArahkanDomainAplikasi`, atau skema terbaca `http` di balik proxy yang tidak dipercaya).
 */
final class KirimVerifikasiEmail
{
    public function __construct(private readonly PenandaVerifikasiEmail $penanda) {}

    /** @return bool false = email sudah terverifikasi, jadi tidak ada yang dikirim (bukan kegagalan). */
    public function Jalankan(Pengguna $pengguna): bool
    {
        if ($pengguna->EmailDiverifikasiPada !== null) {
            return false;
        }

        $jam = (int) config('tenant.JamBerlakuVerifikasiEmail');
        $relatif = URL::temporarySignedRoute('verifikasi-email', now()->addHours($jam), [
            'pengguna' => $pengguna->Uuid,
            'hash' => $this->penanda->BuatHash($pengguna),
        ], false);
        $tautan = AlamatDomain::BuatUrlAbsolutTenant($relatif);

        Mail::to($pengguna->Email)->queue(new VerifikasiEmail($pengguna->Nama, $tautan, $jam));

        return true;
    }
}
