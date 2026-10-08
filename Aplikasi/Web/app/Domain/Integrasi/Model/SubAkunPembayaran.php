<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Integrasi\Enum\StatusSubAkunPembayaran;
use Illuminate\Support\Carbon;

/**
 * Sub account pembayaran milik tenant di penyedia (DOKU), dibuat otomatis oleh pengelola. Satu per (tenant, penyedia).
 * Tidak menyimpan rahasia; kredensial akun induk ada di integrasi platform `DokuBilling`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Penyedia
 * @property string|null $IdSubAkun
 * @property StatusSubAkunPembayaran $Status
 * @property string|null $PesanGalat
 * @property int|null $DibuatOleh
 * @property int|null $DiubahOleh
 * @property Carbon $DibuatPada
 * @property Carbon $DiubahPada
 */
final class SubAkunPembayaran extends ModelDasar
{
    use MilikTenant;

    public const PENYEDIA_DOKU = 'Doku';

    protected $table = 'SubAkunPembayaran';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Menunggu',
    ];

    /** Sudah terdaftar di penyedia: tidak boleh dibuat lagi (idempoten). */
    public function CekSudahAda(): bool
    {
        return $this->IdSubAkun !== null && $this->IdSubAkun !== '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Status' => StatusSubAkunPembayaran::class];
    }
}
