<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Rincian add-on pada satu tagihan langganan (D-49): snapshot nama & harga saat terbit. Tidak berubah dan tidak
 * dihapus setelah dibuat, sama seperti tagihan induknya (CLAUDE.md #8).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdTagihanLangganan
 * @property int $IdAddon
 * @property string $KodeAddon
 * @property string $NamaAddon
 * @property int $Jumlah
 * @property string $HargaBulanan
 * @property int $JumlahBulan
 * @property bool $Prorata
 * @property int|null $HariDitagih
 * @property int|null $HariPeriode
 * @property string $Subtotal
 * @property Carbon $MulaiPada
 * @property Carbon $SelesaiPada
 */
final class TagihanLanggananAddon extends ModelDasar
{
    protected $table = 'TagihanLanggananAddon';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Jumlah' => 1, 'Prorata' => false, 'HariDitagih' => null, 'HariPeriode' => null];

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('Rincian add-on tagihan tidak boleh diubah setelah terbit.');
        });

        self::deleting(static function (): void {
            throw new LogicException('Rincian add-on tagihan tidak boleh dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jumlah' => 'integer',
            'HargaBulanan' => 'decimal:2',
            'JumlahBulan' => 'integer',
            'Prorata' => 'boolean',
            'HariDitagih' => 'integer',
            'HariPeriode' => 'integer',
            'Subtotal' => 'decimal:2',
            'MulaiPada' => 'datetime',
            'SelesaiPada' => 'datetime',
        ];
    }
}
