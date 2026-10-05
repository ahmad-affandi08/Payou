<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\MasukGoogle;

/** Identitas yang sudah terverifikasi dari token ID Google (D-57). Email selalu huruf kecil dan sudah terverifikasi Google. */
final readonly class IdentitasGoogle
{
    public function __construct(
        public string $sub,
        public string $email,
        public string $nama,
    ) {}
}
