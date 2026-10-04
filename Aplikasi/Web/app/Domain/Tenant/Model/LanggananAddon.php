<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Add-on yang dimiliki tenant (D-49, F-19). Satu baris per tenant per add-on; aktif selama
 * `MulaiPada ≤ sekarang < SelesaiPada`. `BerhentiPada` terisi bila pemilik berhenti berlangganan (tetap aktif sampai
 * `SelesaiPada`, tidak ditagih lagi di perpanjangan berikutnya).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdAddon
 * @property int $Jumlah
 * @property Carbon $MulaiPada
 * @property Carbon $SelesaiPada
 * @property bool $PerpanjangOtomatis
 * @property Carbon|null $BerhentiPada
 * @property int|null $IdTagihanLanggananAsal
 * @property-read Addon $Addon
 */
final class LanggananAddon extends ModelDasar
{
    protected $table = 'LanggananAddon';

    /** @var array<string, mixed> */
    protected $attributes = ['Jumlah' => 1, 'PerpanjangOtomatis' => true, 'BerhentiPada' => null, 'IdTagihanLanggananAsal' => null];

    /**
     * @return BelongsTo<Addon, $this>
     */
    public function Addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'IdAddon', 'Id');
    }

    /**
     * @param  Builder<LanggananAddon>  $kueri
     * @return Builder<LanggananAddon>
     */
    public function scopeAktifPada(Builder $kueri, Carbon $pada): Builder
    {
        return $kueri->where('MulaiPada', '<=', $pada)->where('SelesaiPada', '>', $pada);
    }

    public function CekAktifPada(Carbon $pada): bool
    {
        return $this->MulaiPada->lessThanOrEqualTo($pada) && $this->SelesaiPada->greaterThan($pada);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jumlah' => 'integer',
            'MulaiPada' => 'datetime',
            'SelesaiPada' => 'datetime',
            'PerpanjangOtomatis' => 'boolean',
            'BerhentiPada' => 'datetime',
        ];
    }
}
