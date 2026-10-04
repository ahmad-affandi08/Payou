<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Template sektor (P-03, PRD §5.1). Isinya berversi di `TemplateSektorVersi`; F-01 menerapkan versi terbit.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Kode
 * @property string $Nama
 * @property string|null $Keterangan
 * @property Carbon|null $DinonaktifkanPada
 * @property-read TemplateSektorVersi|null $VersiTerbit
 * @property-read TemplateSektorVersi|null $VersiDraf
 */
final class TemplateSektor extends ModelDasar
{
    protected $table = 'TemplateSektor';

    /** @var array<string, mixed> */
    protected $attributes = ['Keterangan' => null];

    /** @var array<string, string> */
    protected $casts = ['DinonaktifkanPada' => 'datetime'];

    /** URL memakai kode sektor, misal `/template-sektor/FNB-CAF/versi/2`. */
    public function getRouteKeyName(): string
    {
        return 'Kode';
    }

    /**
     * @return HasMany<TemplateSektorVersi, $this>
     */
    public function Versi(): HasMany
    {
        return $this->hasMany(TemplateSektorVersi::class, 'IdTemplateSektor', 'Id');
    }

    /**
     * @return HasOne<TemplateSektorVersi, $this>
     */
    public function VersiTerbit(): HasOne
    {
        return $this->hasOne(TemplateSektorVersi::class, 'IdTemplateSektor', 'Id')
            ->where('Status', StatusTemplateSektor::Terbit->value);
    }

    /**
     * @return HasOne<TemplateSektorVersi, $this>
     */
    public function VersiDraf(): HasOne
    {
        return $this->hasOne(TemplateSektorVersi::class, 'IdTemplateSektor', 'Id')
            ->where('Status', StatusTemplateSektor::Draf->value);
    }
}
