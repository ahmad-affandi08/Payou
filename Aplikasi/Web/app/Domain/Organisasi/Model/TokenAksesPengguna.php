<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Token akses pengguna Aplikasi Owner (OWN-01, PRD §16 "user token"). Milik akun `Pengguna`, bukan tenant, sehingga
 * tanpa `MilikTenant`: selalu dicari lewat hash tokennya sendiri atau disaring `IdPengguna` pemiliknya. Tenant dipilih
 * per permintaan (header `X-Tenant`) dan keanggotaannya diperiksa ulang setiap kali.
 *
 * `HashToken` = SHA-256 token (token asli hanya ditampilkan sekali saat masuk). `Kemampuan` = cakupan (`pemilik`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdPengguna
 * @property string $Nama
 * @property string $Kemampuan
 * @property bool $MasukGoogle diterbitkan lewat Masuk dengan Google, menggantikan 2FA (D-57)
 * @property string $HashToken
 * @property string|null $Ip
 * @property string|null $AgenPengguna
 * @property Carbon|null $TerakhirDipakaiPada
 * @property Carbon $KedaluwarsaPada
 * @property Carbon|null $DicabutPada
 * @property-read Pengguna $Pengguna
 */
final class TokenAksesPengguna extends ModelDasar
{
    public const KEMAMPUAN_PEMILIK = 'pemilik';

    protected $table = 'TokenAksesPengguna';

    /** @var list<string> */
    protected $hidden = ['HashToken'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'MasukGoogle' => false,
        'Ip' => null,
        'AgenPengguna' => null,
        'TerakhirDipakaiPada' => null,
        'DicabutPada' => null,
    ];

    public static function BuatHashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function CekBerlaku(): bool
    {
        return $this->DicabutPada === null && $this->KedaluwarsaPada->isFuture();
    }

    /**
     * @return BelongsTo<Pengguna, $this>
     */
    public function Pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'IdPengguna', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TerakhirDipakaiPada' => 'datetime',
            'KedaluwarsaPada' => 'datetime',
            'DicabutPada' => 'datetime',
            'MasukGoogle' => 'boolean',
        ];
    }
}
