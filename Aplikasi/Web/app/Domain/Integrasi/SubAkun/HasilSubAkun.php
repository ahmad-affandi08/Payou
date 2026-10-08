<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\SubAkun;

use App\Domain\Integrasi\Enum\StatusSubAkunPembayaran;

/** Hasil pembuatan sub account di penyedia: pengenalnya dan status awalnya. */
final readonly class HasilSubAkun
{
    public function __construct(public string $idSubAkun, public StatusSubAkunPembayaran $status) {}
}
