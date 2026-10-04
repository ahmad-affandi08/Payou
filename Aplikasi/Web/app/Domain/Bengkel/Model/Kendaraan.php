<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Kendaraan pelanggan bengkel (§9.10, §15 `Kendaraan`). `NomorPolisi` huruf besar ("AD 1234 XY"), unik per tenant di
 * antara kendaraan aktif (`KunciNomorPolisiAktif` kolom turunan). `KmTerakhir` naik dari KM masuk perintah kerja.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPelanggan
 * @property string $NomorPolisi
 * @property string $Merek
 * @property string|null $Tipe
 * @property int|null $Tahun
 * @property string|null $Warna
 * @property string|null $NomorRangka
 * @property string|null $NomorMesin
 * @property int|null $KmTerakhir
 * @property string|null $Catatan
 * @property bool $Aktif
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class Kendaraan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Kendaraan';

    /** @var list<string> */
    protected $guarded = ['Id', 'KunciNomorPolisiAktif'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'Tipe' => null,
        'Tahun' => null,
        'Warna' => null,
        'NomorRangka' => null,
        'NomorMesin' => null,
        'KmTerakhir' => null,
        'Catatan' => null,
        'Aktif' => true,
        'DibuatOleh' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Aktif' => 'boolean', 'Tahun' => 'integer', 'KmTerakhir' => 'integer'];
    }

    /** "Honda Vario 125 | 2021" untuk label pilihan & dokumen. */
    public function AmbilLabel(): string
    {
        $nama = trim($this->Merek.' '.($this->Tipe ?? ''));

        return $this->Tahun === null ? $nama : "{$nama} | {$this->Tahun}";
    }
}
