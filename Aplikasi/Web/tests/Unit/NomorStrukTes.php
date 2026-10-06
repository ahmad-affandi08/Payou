<?php

declare(strict_types=1);

use App\Domain\Penjualan\Layanan\NomorStruk;

it('nomor struk dibaca pelanggan tanpa kode perangkat (D-71)', function (): void {
    expect(NomorStruk::Pendekkan('INV/SLB/260924/POS-001-0042'))->toBe('INV/SLB/260924/0042')
        ->and(NomorStruk::Pendekkan('INV/UTM/261006/K01-0007'))->toBe('INV/UTM/261006/0007')
        ->and(NomorStruk::Pendekkan('42'))->toBe('42')
        ->and(NomorStruk::Pendekkan('TIKET-ABC'))->toBe('TIKET-ABC');
});
