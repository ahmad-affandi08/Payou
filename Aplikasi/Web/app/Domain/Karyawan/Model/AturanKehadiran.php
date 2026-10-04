<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Aturan kehadiran tenant (F-18 bagian 5, D-44): bagaimana jadwal kerja dipakai oleh absensi. Satu baris per tenant;
 * tanpa baris = bawaan (tanpa penegakan, notifikasi mati).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property bool $WajibJadwal
 * @property int $MasukPalingAwalMenit
 * @property int $ToleransiTerlambatMenit
 * @property int $ToleransiPulangCepatMenit
 * @property int $LemburSetelahMenit
 * @property bool $PengingatShiftAktif
 * @property int $PengingatShiftMenitSebelum
 * @property bool $PeringatanPengelolaAktif
 * @property int $PeringatanPengelolaSetelahMenit
 */
final class AturanKehadiran extends ModelDasar
{
    use MilikTenant;

    protected $table = 'AturanKehadiran';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'WajibJadwal' => false,
        'MasukPalingAwalMenit' => 60,
        'ToleransiTerlambatMenit' => 5,
        'ToleransiPulangCepatMenit' => 5,
        'LemburSetelahMenit' => 30,
        'PengingatShiftAktif' => false,
        'PengingatShiftMenitSebelum' => 30,
        'PeringatanPengelolaAktif' => false,
        'PeringatanPengelolaSetelahMenit' => 15,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'WajibJadwal' => 'boolean',
            'MasukPalingAwalMenit' => 'integer',
            'ToleransiTerlambatMenit' => 'integer',
            'ToleransiPulangCepatMenit' => 'integer',
            'LemburSetelahMenit' => 'integer',
            'PengingatShiftAktif' => 'boolean',
            'PengingatShiftMenitSebelum' => 'integer',
            'PeringatanPengelolaAktif' => 'boolean',
            'PeringatanPengelolaSetelahMenit' => 'integer',
        ];
    }
}
