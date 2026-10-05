<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Layanan\PencatatAuditAkun;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TokenAksesPengguna;
use Illuminate\Support\Facades\DB;

/**
 * OWN-01: menerbitkan token akses Aplikasi Owner setelah kata sandi (dan kode 2FA bila aktif) terverifikasi. Token acak
 * 256 bit hanya dikembalikan sekali; yang disimpan hash SHA-256-nya. Berlaku `HARI_BERLAKU` hari lalu harus masuk lagi.
 * `masukGoogle` (D-57): token dari Masuk dengan Google, yang menggantikan 2FA. Dicatat `pemilik.masuk` di log audit setiap tenant tempat pengguna menjadi anggota aktif.
 */
final class TerbitkanTokenPengguna
{
    public const HARI_BERLAKU = 30;

    public function __construct(private readonly PencatatAuditAkun $auditAkun) {}

    public function Jalankan(Pengguna $pengguna, string $namaPerangkat, ?string $ip, ?string $agenPengguna, string $kemampuan = TokenAksesPengguna::KEMAMPUAN_PEMILIK, bool $masukGoogle = false): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        DB::transaction(function () use ($pengguna, $namaPerangkat, $ip, $agenPengguna, $kemampuan, $token, $masukGoogle): void {
            $baris = TokenAksesPengguna::query()->create([
                'IdPengguna' => $pengguna->Id,
                'Nama' => mb_substr(trim($namaPerangkat), 0, 100),
                'Kemampuan' => $kemampuan,
                'MasukGoogle' => $masukGoogle,
                'HashToken' => TokenAksesPengguna::BuatHashToken($token),
                'Ip' => $ip,
                'AgenPengguna' => $agenPengguna === null ? null : mb_substr($agenPengguna, 0, 500),
                'KedaluwarsaPada' => now()->addDays(self::HARI_BERLAKU),
            ]);
            $this->auditAkun->Catat('pemilik.masuk', $pengguna, ['NamaPerangkat' => $baris->Nama, 'UuidToken' => $baris->Uuid, 'Metode' => $masukGoogle ? 'Google' : 'KataSandi']);
        });

        return $token;
    }
}
