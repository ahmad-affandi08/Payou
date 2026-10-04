<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use Illuminate\Support\Carbon;

/**
 * Karyawan (F-18, EMP-01), opsional tertaut ke pengguna tenant (akun + PIN kasir). Karyawan tanpa akun tetap bisa
 * dijadwalkan dan (bagian 2) menerima komisi, tetapi tidak bisa absen di POS.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int|null $IdPengguna
 * @property int|null $IdOutlet
 * @property string $Nama
 * @property string|null $Jabatan
 * @property string|null $LevelStaf
 * @property string|null $GajiPokok
 * @property string|null $TarifLemburPerJam F-18 bagian 5: tarif lembur per jam, potongan per menit terlambat, potongan per hari tidak masuk (null = tidak dihitung)
 * @property string|null $PotonganTerlambatPerMenit
 * @property string|null $PotonganTidakMasukPerHari
 * @property StatusKaryawan $Status
 * @property string|null $TokenAbsen F-18 bagian 4: tautan absen HP pribadi (terenkripsi; ditampilkan ulang ke pengelola)
 * @property string|null $HashTokenAbsen
 * @property Carbon|null $TokenAbsenDibuatPada
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 */
final class Karyawan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Karyawan';

    /** @var array<string, mixed> */
    protected $attributes = ['Status' => 'Aktif', 'TokenAbsen' => null, 'HashTokenAbsen' => null, 'TokenAbsenDibuatPada' => null];

    /** @var list<string> */
    protected $hidden = ['TokenAbsen', 'HashTokenAbsen'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'GajiPokok' => 'decimal:2',
            'TarifLemburPerJam' => 'decimal:2',
            'PotonganTerlambatPerMenit' => 'decimal:2',
            'PotonganTidakMasukPerHari' => 'decimal:2',
            'Status' => StatusKaryawan::class,
            'TokenAbsen' => 'encrypted',
            'TokenAbsenDibuatPada' => 'datetime',
        ];
    }
}
