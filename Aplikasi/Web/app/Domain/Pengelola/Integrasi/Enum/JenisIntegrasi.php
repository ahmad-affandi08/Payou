<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Enum;

/**
 * Jenis integrasi platform (P-05). Setiap jenis punya satu konfigurasi per lingkungan dengan satu penyedia terpilih
 * dari katalog `PenyediaIntegrasi` (v2.04: gerbang pembayaran & WhatsApp, banyak penyedia email).
 */
enum JenisIntegrasi: string
{
    case Email = 'Email';
    case Captcha = 'Captcha';
    case Penyimpanan = 'Penyimpanan';
    case GerbangPembayaran = 'GerbangPembayaran';
    case Whatsapp = 'Whatsapp';
    case Push = 'Push';
    case GerbangBilling = 'GerbangBilling';
    case LoginSosial = 'LoginSosial';

    /**
     * Jenis yang dikonfigurasi di tingkat platform. v2.06: gerbang pembayaran diatur tiap tenant (akun merchant
     * tenant), platform hanya mengatur katalog penyedianya; baris lama jenis ini diabaikan.
     *
     * @return list<self>
     */
    public static function AmbilJenisPlatform(): array
    {
        return array_values(array_filter(self::cases(), fn (self $jenis): bool => $jenis !== self::GerbangPembayaran));
    }

    /** Penyedia bawaan formulir (konfigurasi lama sebelum v2.04 memakai penyedia ini). */
    public function AmbilPenyedia(): PenyediaIntegrasi
    {
        return match ($this) {
            self::Email => PenyediaIntegrasi::Smtp,
            self::Captcha => PenyediaIntegrasi::Turnstile,
            self::Penyimpanan => PenyediaIntegrasi::S3,
            self::GerbangPembayaran => PenyediaIntegrasi::Midtrans,
            self::Whatsapp => PenyediaIntegrasi::MetaCloud,
            self::Push => PenyediaIntegrasi::Fcm,
            self::GerbangBilling => PenyediaIntegrasi::MidtransBilling,
            self::LoginSosial => PenyediaIntegrasi::Google,
        };
    }

    /**
     * @return list<PenyediaIntegrasi>
     */
    public function AmbilDaftarPenyedia(): array
    {
        return array_values(array_filter(PenyediaIntegrasi::cases(), fn (PenyediaIntegrasi $p): bool => $p->AmbilJenis() === $this));
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Email => 'Email transaksional',
            self::Captcha => 'CAPTCHA',
            self::Penyimpanan => 'Penyimpanan objek',
            self::GerbangPembayaran => 'Gerbang pembayaran (QRIS dinamis)',
            self::Whatsapp => 'WhatsApp',
            self::Push => 'Push notification (aplikasi Pemilik & POS)',
            self::GerbangBilling => 'Gerbang pembayaran tagihan langganan',
            self::LoginSosial => 'Masuk dengan Google',
        };
    }
}
