<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

/**
 * Nomor pesanan tagihan QRIS dinamis di gerbang (order id / reference id, F-08): `PY{IdTenant basis-36}-{Uuid}`.
 * Seperti `KodeStrukDigital`, bagian tenant hanya menetapkan scope pencarian saat webhook masuk (tanpa query lintas
 * tenant); keaslian notifikasi dijamin tanda tangan gerbang. Panjang maksimal 2 + 13 + 1 + 26 = 42 karakter (aman
 * untuk batas nomor faktur gerbang).
 */
final class NomorPesananQris
{
    public const POLA = 'PY[0-9a-z]{1,13}-[0-9A-HJKMNP-TV-Z]{26}';

    public static function Buat(int $idTenant, string $uuid): string
    {
        return 'PY'.base_convert((string) $idTenant, 10, 36).'-'.strtoupper($uuid);
    }

    /**
     * @return array{IdTenant: int, Uuid: string}|null
     */
    public static function Urai(string $nomor): ?array
    {
        if (preg_match('/^'.self::POLA.'$/', $nomor) !== 1) {
            return null;
        }

        [$tenant, $uuid] = explode('-', substr($nomor, 2), 2);
        $idTenant = (int) base_convert($tenant, 36, 10);

        return $idTenant > 0 ? ['IdTenant' => $idTenant, 'Uuid' => $uuid] : null;
    }
}
