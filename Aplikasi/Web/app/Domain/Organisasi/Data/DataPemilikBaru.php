<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

final readonly class DataPemilikBaru
{
    public function __construct(
        public string $nama,
        public string $email,
        public string $noHp,
        public string $kataSandi,
        /** D-57: pendaftaran lewat Google; email dianggap terverifikasi dan kata sandi acak buatan sistem. */
        public ?string $googleSub = null,
    ) {}
}
