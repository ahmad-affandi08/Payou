<?php

declare(strict_types=1);

namespace App\Domain\Situs\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * D-63: salinan isi halaman situs pada satu waktu. `Jenis` = `Terbit` (diambil saat diterbitkan), `SebelumPulih`
 * (draf yang akan ditimpa saat memulihkan revisi lain), atau `Manual` (disimpan pengelola dari penyunting).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdHalamanSitus
 * @property string $Jenis
 * @property string $Judul
 * @property string|null $JudulSeo
 * @property string|null $DeskripsiSeo
 * @property string|null $UuidGambarOg
 * @property list<array<string, mixed>> $Bagian
 * @property int|null $IdPenggunaPengelola
 * @property Carbon $DibuatPada
 */
final class RevisiHalamanSitus extends ModelDasar
{
    public const JENIS_TERBIT = 'Terbit';

    public const JENIS_SEBELUM_PULIH = 'SebelumPulih';

    public const JENIS_MANUAL = 'Manual';

    /** Jumlah revisi yang disimpan per halaman; yang lebih tua dibuang. */
    public const BATAS_PER_HALAMAN = 30;

    protected $table = 'RevisiHalamanSitus';

    protected function casts(): array
    {
        return ['Bagian' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'Uuid';
    }
}
