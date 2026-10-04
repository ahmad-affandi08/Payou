<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\DuaFaktorPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Verifikasi 2FA saat masuk (BR-P01.2; dilewati di perangkat tepercaya, D-42) dan saat konfirmasi aksi berbahaya:
 * kode TOTP 6 digit, atau kode pemulihan sekali pakai.
 */
final class VerifikasiDuaFaktor
{
    public function __construct(
        private readonly DuaFaktorPengelola $duaFaktor,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pengguna, string $kode, bool $konfirmasiAksi = false): void
    {
        if (! $pengguna->CekDuaFaktorAktif() || $pengguna->Rahasia2fa === null) {
            throw new PelanggaranAturanBisnis('DuaFaktorBelumAktif', 'Aktifkan verifikasi dua langkah terlebih dahulu.');
        }

        if ($this->duaFaktor->VerifikasiKode($pengguna->Rahasia2fa, $kode)) {
            $this->audit->Catat($konfirmasiAksi ? 'sesi.dua-faktor.konfirmasi' : 'sesi.masuk', $pengguna, idPelaku: $pengguna->Id);

            return;
        }

        $kodeNormal = Str::upper(trim($kode));
        $sisa = $pengguna->KodePemulihan2fa ?? [];

        if (in_array($kodeNormal, $sisa, true)) {
            DB::transaction(function () use ($pengguna, $sisa, $kodeNormal, $konfirmasiAksi): void {
                $pengguna->update(['KodePemulihan2fa' => array_values(array_diff($sisa, [$kodeNormal]))]);
                $this->audit->Catat(
                    $konfirmasiAksi ? 'sesi.dua-faktor.konfirmasi-kode-pemulihan' : 'sesi.masuk-kode-pemulihan',
                    $pengguna,
                    idPelaku: $pengguna->Id,
                );
            });

            return;
        }

        throw new PelanggaranAturanBisnis('KodeDuaFaktorSalah', 'Kode tidak cocok. Masukkan kode terbaru dari aplikasi autentikator atau kode pemulihan.', 'Kode');
    }
}
