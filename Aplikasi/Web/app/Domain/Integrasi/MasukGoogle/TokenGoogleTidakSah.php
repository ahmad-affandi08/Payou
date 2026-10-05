<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\MasukGoogle;

use RuntimeException;

/** Token ID Google ditolak (tanda tangan, penerbit, audiens, kedaluwarsa, nonce, atau email belum terverifikasi). */
final class TokenGoogleTidakSah extends RuntimeException {}
