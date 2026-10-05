<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Organisasi\Galat\IdentitasSudahTerdaftar;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;

/**
 * Membuat akun pengguna Owner beserta keanggotaannya di tenant baru (F-00 langkah 3, BR-00.1). Dipanggil di dalam
 * transaksi pendaftaran. Email & nomor WhatsApp unik per pengguna; email belum terverifikasi (BR-00.5).
 * Identitas yang sudah dipakai ditolak lewat `IdentitasSudahTerdaftar` tanpa membuka mana yang cocok (§25 no. 18).
 *
 * Pendaftaran lewat Google (D-57, `DataPemilikBaru::googleSub`): email sudah dibuktikan Google, kata sandi acak.
 *
 * `emailTerverifikasi` (D-35 edisi Lisensi): email diketik sendiri oleh pemasang server pembeli, dianggap terverifikasi
 * seperti email yang diisi admin (D-22); server baru belum tentu sudah punya pengirim email.
 */
final class BuatPemilikTenant
{
    public function Jalankan(int $idTenant, DataPemilikBaru $data, bool $emailTerverifikasi = false): Pengguna
    {
        $identitasPerPengguna = [];

        foreach (['Email' => 'email', 'NoHp' => 'nomor WhatsApp'] as $kolom => $label) {
            $idPemilik = Pengguna::query()->where($kolom, $kolom === 'Email' ? $data->email : $data->noHp)->value('Id');

            if (is_numeric($idPemilik)) {
                $identitasPerPengguna[(int) $idPemilik][] = $label;
            }
        }

        if ($identitasPerPengguna !== []) {
            throw new IdentitasSudahTerdaftar($identitasPerPengguna);
        }

        $pengguna = Pengguna::query()->create([
            'Nama' => $data->nama,
            'Email' => $data->email,
            'NoHp' => $data->noHp,
            'KataSandi' => $data->kataSandi,
            'EmailDiverifikasiPada' => $emailTerverifikasi || $data->googleSub !== null ? now() : null,
            'GoogleSub' => $data->googleSub,
            'GoogleDitautkanPada' => $data->googleSub !== null ? now() : null,
            'KataSandiOtomatis' => $data->googleSub !== null,
        ]);

        TenantPengguna::query()->create(['IdTenant' => $idTenant, 'IdPengguna' => $pengguna->Id, 'Pemilik' => true]);

        return $pengguna;
    }
}
