<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use Database\Pabrik\PenggunaPengelolaPabrik;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as KontrakDapatDiautentikasi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Akun tim internal Platform Pengelola, guard `pengelola` (P-01, BR-P01.4, PRD §13.8, §15.3).
 * Terpisah dari `Pengguna` tenant: email yang sama boleh ada di keduanya tanpa berbagi sesi.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Nama
 * @property string $Email
 * @property string $KataSandi
 * @property bool $WajibGantiKataSandi kata sandi awal dibuat Super Admin (D-22), wajib diganti saat pertama masuk
 * @property string|null $Rahasia2fa
 * @property list<string>|null $KodePemulihan2fa
 * @property Carbon|null $DuaFaktorAktifPada
 * @property bool $Aktif
 * @property Carbon|null $DinonaktifkanPada
 * @property Carbon|null $TerakhirMasukPada
 */
final class PenggunaPengelola extends ModelDasar implements KontrakDapatDiautentikasi
{
    use Authenticatable;

    /** @use HasFactory<PenggunaPengelolaPabrik> */
    use HasFactory;

    use Notifiable;

    protected $table = 'PenggunaPengelola';

    /** @var list<string> */
    protected $hidden = ['KataSandi', 'Rahasia2fa', 'KodePemulihan2fa'];

    /**
     * Nilai bawaan kolom, sama dengan default migrasi (agar model baru lengkap walau belum dimuat ulang).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'Rahasia2fa' => null,
        'KodePemulihan2fa' => null,
        'DuaFaktorAktifPada' => null,
        'Aktif' => true,
        'WajibGantiKataSandi' => false,
        'DinonaktifkanPada' => null,
        'TerakhirMasukPada' => null,
    ];

    /** @var list<string>|null */
    private ?array $izinTerhimpun = null;

    public function getAuthPasswordName(): string
    {
        return 'KataSandi';
    }

    /**
     * Tanpa fitur "ingat saya": sesi pengelola wajib berakhir setelah 30 menit tidak aktif (BR-P01.2). Yang diingat
     * hanya perangkat tepercaya untuk langkah 2FA (D-42), bukan sesinya.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Perangkat tepercaya yang melewati langkah 2FA saat login (D-42).
     *
     * @return HasMany<PerangkatTepercayaPengelola, $this>
     */
    public function PerangkatTepercaya(): HasMany
    {
        return $this->hasMany(PerangkatTepercayaPengelola::class, 'IdPenggunaPengelola', 'Id');
    }

    public function routeNotificationForMail(): string
    {
        return $this->Email;
    }

    /**
     * @return BelongsToMany<PeranPengelola, $this>
     */
    public function Peran(): BelongsToMany
    {
        return $this->belongsToMany(PeranPengelola::class, 'PenggunaPengelolaPeran', 'IdPenggunaPengelola', 'IdPeranPengelola', 'Id', 'Id');
    }

    public function CekDuaFaktorAktif(): bool
    {
        return $this->DuaFaktorAktifPada !== null && $this->Rahasia2fa !== null;
    }

    public function PunyaIzin(IzinPengelola $izin): bool
    {
        return in_array($izin->value, $this->AmbilDaftarIzin(), true);
    }

    public function PunyaPeran(PeranPengelolaBawaan $peran): bool
    {
        return in_array($peran->value, $this->AmbilKodePeran(), true);
    }

    /**
     * @return list<string>
     */
    public function AmbilKodePeran(): array
    {
        return array_values($this->MuatPeran()->Peran->map(fn (PeranPengelola $peran): string => $peran->Kode)->all());
    }

    /**
     * @return list<string>
     */
    public function AmbilDaftarIzin(): array
    {
        if ($this->izinTerhimpun === null) {
            $kunci = [];

            foreach ($this->MuatPeran()->Peran as $peran) {
                foreach ($peran->Izin as $izin) {
                    $kunci[$izin->KunciIzin] = true;
                }
            }

            ksort($kunci);
            $this->izinTerhimpun = array_keys($kunci);
        }

        return $this->izinTerhimpun;
    }

    public function LupakanIzin(): void
    {
        $this->izinTerhimpun = null;
        $this->unsetRelation('Peran');
    }

    private function MuatPeran(): self
    {
        return $this->loadMissing('Peran.Izin');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'KataSandi' => 'hashed',
            'Rahasia2fa' => 'encrypted',
            'KodePemulihan2fa' => 'encrypted:array',
            'DuaFaktorAktifPada' => 'datetime',
            'Aktif' => 'boolean',
            'WajibGantiKataSandi' => 'boolean',
            'DinonaktifkanPada' => 'datetime',
            'TerakhirMasukPada' => 'datetime',
        ];
    }
}
