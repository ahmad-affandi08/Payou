<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use Closure;

/**
 * Menyaring email asal-asalan sebelum disimpan sebagai email akun (BR-00.5): domain yang tidak punya server surat,
 * domain email sekali pakai, domain contoh/uji, dan salah ketik penyedia populer (gmial.com).
 *
 * Tidak ada pemeriksaan yang bisa membuktikan sebuah kotak surat nyata; yang membuktikannya hanya tautan verifikasi.
 * Penyaring ini menutup kesalahan yang paling sering (typo dan isian asal) sebelum akun terbentuk.
 */
final class PemeriksaAlamatEmail
{
    /** Domain contoh/uji yang tidak mungkin milik pengguna sungguhan. */
    private const DOMAIN_CONTOH = [
        'example.com', 'example.org', 'example.net', 'test.com', 'tes.com', 'email.com',
        'mail.com.id', 'domain.com', 'asd.com', 'abc.com', 'xyz.com', 'aaa.com', 'a.com', 'b.com',
    ];

    /** Penyedia email sekali pakai yang paling umum. */
    private const DOMAIN_SEKALI_PAKAI = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org', 'sharklasers.com',
        '10minutemail.com', '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'tempmail.net',
        'throwawaymail.com', 'yopmail.com', 'yopmail.net', 'getnada.com', 'nada.email', 'trashmail.com',
        'trashmail.net', 'maildrop.cc', 'dispostable.com', 'fakeinbox.com', 'mohmal.com', 'emailondeck.com',
        'moakt.com', 'mintemail.com', 'spamgourmet.com', 'tempail.com', 'tempinbox.com', 'burnermail.io',
        'discard.email', 'mailnesia.com', 'mytemp.email', 'tmpmail.org', 'tmpmail.net', 'harakirimail.com',
    ];

    /** Salah ketik populer → penulisan yang benar. */
    private const SALAH_KETIK = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com',
        'gmail.cm' => 'gmail.com', 'gmaill.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gnail.com' => 'gmail.com',
        'gmail.id' => 'gmail.com', 'gmail.co.id' => 'gmail.com', 'gmeil.com' => 'gmail.com',
        'yahooo.com' => 'yahoo.com', 'yaho.com' => 'yahoo.com', 'yahoo.co' => 'yahoo.com', 'yhoo.com' => 'yahoo.com',
        'yahoo.con' => 'yahoo.com', 'hotmal.com' => 'hotmail.com', 'hotmial.com' => 'hotmail.com',
        'hotmail.co' => 'hotmail.com', 'outlok.com' => 'outlook.com', 'outlook.co' => 'outlook.com',
        'iclod.com' => 'icloud.com', 'icloud.co' => 'icloud.com',
    ];

    /** @var Closure(string): bool */
    private readonly Closure $punyaServerSurat;

    /**
     * @param  (Closure(string): bool)|null  $punyaServerSurat  Pencari catatan DNS; diganti saat test.
     */
    public function __construct(?Closure $punyaServerSurat = null)
    {
        $this->punyaServerSurat = $punyaServerSurat
            ?? static fn (string $domain): bool => checkdnsrr($domain.'.', 'MX')
                || checkdnsrr($domain.'.', 'A')
                || checkdnsrr($domain.'.', 'AAAA');
    }

    /** @return string|null Alasan penolakan untuk ditampilkan ke pengguna; null = alamat boleh dipakai. */
    public function AmbilAlasanTolak(string $email): ?string
    {
        $domain = $this->AmbilDomain($email);

        if ($domain === null) {
            return 'Penulisan email belum benar. Contoh: nama@usaha.com.';
        }

        if (isset(self::SALAH_KETIK[$domain])) {
            return 'Maksud Anda ...@'.self::SALAH_KETIK[$domain].'? Periksa penulisan email Anda.';
        }

        if (in_array($domain, self::DOMAIN_CONTOH, true)) {
            return 'Gunakan email asli yang Anda baca, karena tautan verifikasi dan pengingat tagihan dikirim ke sana.';
        }

        if (in_array($domain, self::DOMAIN_SEKALI_PAKAI, true)) {
            return 'Email sekali pakai tidak bisa dipakai. Gunakan email pribadi atau email usaha Anda.';
        }

        if ((bool) config('tenant.PeriksaDnsEmail') && ! ($this->punyaServerSurat)($domain)) {
            return 'Alamat email ini tidak bisa menerima pesan (domain "'.$domain.'" tidak punya server surat). Periksa penulisannya.';
        }

        return null;
    }

    private function AmbilDomain(string $email): ?string
    {
        $posisi = strrpos($email, '@');

        if ($posisi === false) {
            return null;
        }

        $domain = mb_strtolower(trim(substr($email, $posisi + 1)));

        return $domain === '' || ! str_contains($domain, '.') ? null : $domain;
    }
}
