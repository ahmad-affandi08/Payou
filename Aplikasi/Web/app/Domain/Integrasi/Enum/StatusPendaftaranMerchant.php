<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Enum;

/**
 * Status pendaftaran merchant pembayaran tenant di DOKU Partner API.
 *
 * `Draf` = data tersimpan, belum dikirim (atau pengiriman diulang dari awal); `Dikirim` = sedang/akan diproses
 * antrean (unggah berkas + registrasi bisnis); `Ditinjau` = DOKU menerima dan sedang meninjau (KYB, SLA 2x24 jam);
 * `Aktif` = DOKU menyetujui; `Ditolak` = DOKU menolak (ada alasan); `Gagal` = DOKU menolak permintaan kita
 * secara pasti (galat 4xx) sehingga data perlu diperbaiki.
 */
enum StatusPendaftaranMerchant: string
{
    case Draf = 'Draf';
    case Dikirim = 'Dikirim';
    case Ditinjau = 'Ditinjau';
    case Aktif = 'Aktif';
    case Ditolak = 'Ditolak';
    case Gagal = 'Gagal';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Dikirim => 'Sedang dikirim',
            self::Ditinjau => 'Sedang ditinjau',
            self::Aktif => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Gagal => 'Gagal dikirim',
        };
    }

    /** Data dan foto boleh diubah, dan pendaftaran boleh dikirim (lagi). */
    public function CekBisaDiubah(): bool
    {
        return in_array($this, [self::Draf, self::Ditolak, self::Gagal], true);
    }
}
