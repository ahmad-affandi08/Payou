<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Billing;

/**
 * Nomor pesanan (order id) tagihan langganan di gerbang billing platform: `{IdTenant basis-36}-{Uuid pembayaran}`.
 *
 * Bagian `IdTenant` ada demi alasan yang sama seperti token webhook gerbang tenant (`PencariGerbangWebhook`):
 * notifikasi webhook datang tanpa sesi, jadi tenant harus bisa ditetapkan **sebelum** ada query. Dengan begitu
 * pembayaran dicari lewat scope `MilikTenant` seperti biasa, tanpa query lintas tenant (CLAUDE.md #11).
 *
 * `IdTenant` di sini hanya menentukan lingkup pencarian, bukan bukti kepemilikan: keaslian notifikasi dijamin tanda
 * tangan HMAC-SHA256 dari secret key DOKU platform, dan pembayaran hanya ditemukan bila `RefGateway`-nya cocok persis.
 *
 * Panjangnya aman untuk batas 64 karakter `invoice_number` DOKU: 13 + 1 + 26 (ULID) = 40, hanya huruf, angka, dan tanda hubung.
 */
final class NomorPesananBilling
{
    /** ULID memakai Crockford base32 huruf besar, sedangkan basis-36 PHP huruf kecil, sehingga tidak ambigu. */
    public const POLA = '/^([0-9a-z]{1,13})-([0-9A-HJKMNP-TV-Z]{26})$/';

    public static function Buat(int $idTenant, string $uuidPembayaran): string
    {
        return base_convert((string) $idTenant, 10, 36).'-'.$uuidPembayaran;
    }

    /**
     * @return array{IdTenant: int, Uuid: string}|null
     */
    public static function Urai(string $nomor): ?array
    {
        if (preg_match(self::POLA, $nomor, $cocok) !== 1) {
            return null;
        }

        $idTenant = (int) base_convert($cocok[1], 36, 10);

        return $idTenant > 0 ? ['IdTenant' => $idTenant, 'Uuid' => $cocok[2]] : null;
    }
}
