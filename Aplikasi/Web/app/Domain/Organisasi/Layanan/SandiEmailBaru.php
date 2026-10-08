<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

/**
 * Menyandikan alamat email baru ke satu segmen jalur URL (base64url) untuk tautan konfirmasi ganti email. Alamat
 * email boleh mengandung titik dan `@`, yang merepotkan sebagai segmen rute; base64url hanya berisi `A-Za-z0-9-_`.
 * Isi tautan ditandatangani oleh `signed`, jadi alamat tidak bisa diubah di tengah jalan.
 */
final class SandiEmailBaru
{
    public function Sandikan(string $email): string
    {
        return rtrim(strtr(base64_encode($email), '+/', '-_'), '=');
    }

    public function Urai(string $sandi): ?string
    {
        $email = base64_decode(strtr($sandi, '-_', '+/'), true);

        return $email === false || $email === '' ? null : $email;
    }
}
