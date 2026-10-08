<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Tautan konfirmasi ganti email akun, dikirim ke alamat baru (BR-00.5).
 */
final class GantiEmail extends SurelDasar
{
    public function __construct(public readonly string $nama, public readonly string $tautan, public readonly int $jamBerlaku)
    {
        $this->subject('Konfirmasi email baru akun Anda')
            ->IsiSurel('Tenant.GantiEmail', ['Nama' => $nama, 'Tautan' => $tautan, 'JamBerlaku' => $jamBerlaku]);
    }
}
