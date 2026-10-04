<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Browser yang dipercaya anggota tim konsol setelah kode 2FA benar (P-01 BR-P01.2, D-42). Login berikutnya dari
 * browser ini cukup kata sandi sampai `BerlakuSampai`. Hanya hash token yang disimpan; tokennya ada di cookie.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdPenggunaPengelola
 * @property string $HashToken
 * @property string $Keterangan ringkasan peramban & sistem operasi, misal "Chrome di Windows"
 * @property string|null $AlamatIp
 * @property Carbon|null $TerakhirDipakaiPada
 * @property Carbon $BerlakuSampai
 * @property Carbon|null $DicabutPada
 * @property Carbon|null $DibuatPada
 */
final class PerangkatTepercayaPengelola extends ModelDasar
{
    protected $table = 'PerangkatTepercayaPengelola';

    /** @var list<string> */
    protected $hidden = ['HashToken'];

    /**
     * Nilai bawaan kolom, sama dengan default migrasi.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'AlamatIp' => null,
        'TerakhirDipakaiPada' => null,
        'DicabutPada' => null,
    ];

    public static function BuatHashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<PenggunaPengelola, $this>
     */
    public function Pengguna(): BelongsTo
    {
        return $this->belongsTo(PenggunaPengelola::class, 'IdPenggunaPengelola', 'Id');
    }

    public function CekMasihBerlaku(): bool
    {
        return $this->DicabutPada === null && $this->BerlakuSampai->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TerakhirDipakaiPada' => 'datetime',
            'BerlakuSampai' => 'datetime',
            'DicabutPada' => 'datetime',
        ];
    }
}
