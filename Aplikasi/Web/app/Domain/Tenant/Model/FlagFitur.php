<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\CakupanFlagFitur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Aturan flag fitur (P-10, PGL-18). Data platform (tanpa `MilikTenant`); dikelola Platform Pengelola, dievaluasi
 * `FlagFiturTenant` untuk `EvaluatorFitur` dan `konfigurasi-aplikasi`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Kunci
 * @property CakupanFlagFitur $Cakupan
 * @property int|null $IdObjek
 * @property bool $Nilai
 * @property int|null $Persen
 * @property string $Alasan
 * @property int|null $DiubahOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class FlagFitur extends ModelDasar
{
    protected $table = 'FlagFitur';

    /** Kunci cache seluruh aturan flag (dipakai `FlagFiturTenant`); dibatalkan otomatis tiap flag berubah. */
    public const KUNCI_CACHE_ATURAN = 'flagfitur:aturan';

    protected static function booted(): void
    {
        $lupakan = static fn () => Cache::forget(self::KUNCI_CACHE_ATURAN);
        self::saved($lupakan);
        self::deleted($lupakan);
    }

    /** @var array<string, mixed> */
    protected $attributes = ['IdObjek' => null, 'Persen' => null, 'DiubahOleh' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Cakupan' => CakupanFlagFitur::class,
            'IdObjek' => 'integer',
            'Nilai' => 'boolean',
            'Persen' => 'integer',
        ];
    }
}
