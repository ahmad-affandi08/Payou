import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import AksiMassalPelanggan from '@/Komponen/Pelanggan/AksiMassalPelanggan';
import { PilihOpsi } from '@/Pengujian/InteraksiPilihan';
import type { BarisPelanggan, OpsiTier } from '@/Tipe/Pelanggan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const Tier: OpsiTier[] = [{ Nilai: 'GOLD', Label: 'Gold', Uuid: '01J9TRR0000000000000000001' }];

function Konteks(uuid: string[]) {
    return {
        terpilih: uuid.map((u) => ({ Uuid: u, Nama: `Pelanggan ${u}` }) as unknown as BarisPelanggan),
        semuaHasil: false,
        total: uuid.length,
        keadaan: { cari: '', urut: [], halaman: 1, perHalaman: 25, saring: {} },
        bersihkan: vi.fn(),
    };
}

describe('Aksi massal pelanggan', () => {
    beforeEach(() => tiruanRouter.post.mockClear());
    afterEach(() => cleanup());

    it('arsipkan mengirim Uuid terpilih', () => {
        RenderUji(<AksiMassalPelanggan konteks={Konteks(['01J9PLG0000000000000000001'])} tier={[]} />);

        fireEvent.click(screen.getByRole('button', { name: 'Arsipkan' }));

        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/pelanggan/massal',
            { Aksi: 'Arsipkan', Uuid: ['01J9PLG0000000000000000001'], UuidTier: null, TierTetap: false },
            expect.anything(),
        );
        expect(screen.queryByRole('button', { name: 'Atur tier' })).toBeNull();
    });

    it('atur tier butuh pilihan tier, dan "Lepas tier" mengirim tier kosong', () => {
        RenderUji(<AksiMassalPelanggan konteks={Konteks(['01J9PLG0000000000000000001'])} tier={Tier} />);

        expect((screen.getByRole('button', { name: 'Atur tier' }) as HTMLButtonElement).disabled).toBe(true);
        PilihOpsi(screen.getByRole('combobox', { name: 'Tier tujuan' }), '01J9TRR0000000000000000001');
        fireEvent.click(screen.getByRole('button', { name: 'Atur tier' }));
        expect(tiruanRouter.post).toHaveBeenLastCalledWith(
            '/kelola/pelanggan/massal',
            expect.objectContaining({ Aksi: 'Tier', UuidTier: '01J9TRR0000000000000000001' }),
            expect.anything(),
        );

        PilihOpsi(screen.getByRole('combobox', { name: 'Tier tujuan' }), '__lepas__');
        fireEvent.click(screen.getByRole('button', { name: 'Atur tier' }));
        expect(tiruanRouter.post).toHaveBeenLastCalledWith(
            '/kelola/pelanggan/massal',
            expect.objectContaining({ Aksi: 'Tier', UuidTier: null }),
            expect.anything(),
        );
    });
});
