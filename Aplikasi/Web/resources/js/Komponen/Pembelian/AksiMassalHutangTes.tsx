import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import AksiMassalHutang from '@/Komponen/Pembelian/AksiMassalHutang';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

function Konteks(terpilih: { Uuid: string; UuidPemasok: string | null }[]) {
    return {
        terpilih,
        semuaHasil: false,
        total: terpilih.length,
        keadaan: { cari: '', urut: [], halaman: 1, perHalaman: 25, saring: {} },
        bersihkan: vi.fn(),
    };
}

describe('Aksi massal hutang (bayar faktur terpilih)', () => {
    beforeEach(() => tiruanRouter.visit.mockClear());
    afterEach(() => cleanup());

    it('membuka formulir pembayaran dengan semua faktur satu pemasok', () => {
        RenderUji(
            <AksiMassalHutang
                konteks={Konteks([
                    { Uuid: '01J9FKT0000000000000000001', UuidPemasok: '01J9PSK0000000000000000001' },
                    { Uuid: '01J9FKT0000000000000000002', UuidPemasok: '01J9PSK0000000000000000001' },
                ])}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Bayar 2 faktur terpilih' }));

        expect(tiruanRouter.visit).toHaveBeenCalledWith(
            '/kelola/pembelian/pembayaran/buat?pemasok=01J9PSK0000000000000000001&faktur=01J9FKT0000000000000000001,01J9FKT0000000000000000002',
        );
    });

    it('menolak pilihan lintas pemasok dengan penjelasan', () => {
        RenderUji(
            <AksiMassalHutang
                konteks={Konteks([
                    { Uuid: '01J9FKT0000000000000000001', UuidPemasok: '01J9PSK0000000000000000001' },
                    { Uuid: '01J9FKT0000000000000000002', UuidPemasok: '01J9PSK0000000000000000002' },
                ])}
            />,
        );

        expect((screen.getByRole('button', { name: 'Bayar 2 faktur terpilih' }) as HTMLButtonElement).disabled).toBe(
            true,
        );
        expect(screen.getByText(/hanya untuk satu pemasok/)).toBeTruthy();
    });
});
