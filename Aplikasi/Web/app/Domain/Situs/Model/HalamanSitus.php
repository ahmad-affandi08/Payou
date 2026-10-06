<?php

declare(strict_types=1);

namespace App\Domain\Situs\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * Halaman situs pemasaran berblok (D-21). `BagianDraf` & kolom draf diedit di konsol; kolom `…Terbit` hanya diisi saat
 * diterbitkan dan itulah yang tampil publik. `Aktif` = false menyembunyikan halaman tanpa menghapus isinya.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Slug
 * @property string $Judul
 * @property string|null $JudulSeo
 * @property string|null $DeskripsiSeo
 * @property string|null $UuidGambarOg
 * @property bool $TampilDiSitemap
 * @property list<array<string, mixed>> $BagianDraf
 * @property list<array<string, mixed>>|null $BagianTerbit
 * @property string|null $JudulTerbit
 * @property string|null $JudulSeoTerbit
 * @property string|null $DeskripsiSeoTerbit
 * @property string|null $UuidGambarOgTerbit
 * @property bool $Aktif
 * @property Carbon|null $DiterbitkanPada
 * @property Carbon|null $JadwalTerbitPada
 * @property int|null $IdPenggunaPengelolaPenjadwal
 * @property int|null $IdPenggunaPengelolaPenerbit
 * @property int|null $IdPenggunaPengelolaPengubah
 * @property Carbon|null $DiubahPada
 */
final class HalamanSitus extends ModelDasar
{
    /** Slug halaman beranda (`/`). */
    public const SLUG_BERANDA = 'beranda';

    protected $table = 'HalamanSitus';

    /** @var array<string, mixed> */
    protected $attributes = [
        'TampilDiSitemap' => true,
        'Aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'BagianDraf' => 'array',
            'BagianTerbit' => 'array',
            'TampilDiSitemap' => 'boolean',
            'Aktif' => 'boolean',
            'DiterbitkanPada' => 'datetime',
            'JadwalTerbitPada' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'Uuid';
    }

    public function CekTerbit(): bool
    {
        return $this->BagianTerbit !== null;
    }

    /** Draf berbeda dari versi terbit (ada perubahan yang belum diterbitkan). */
    public function CekAdaPerubahan(): bool
    {
        return ! $this->CekTerbit()
            || $this->BagianDraf !== $this->BagianTerbit
            || $this->Judul !== $this->JudulTerbit
            || $this->JudulSeo !== $this->JudulSeoTerbit
            || $this->DeskripsiSeo !== $this->DeskripsiSeoTerbit
            || $this->UuidGambarOg !== $this->UuidGambarOgTerbit;
    }
}
