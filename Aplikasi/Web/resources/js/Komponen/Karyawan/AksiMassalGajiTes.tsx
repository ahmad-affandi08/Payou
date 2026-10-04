import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import AksiMassalGaji from '@/Komponen/Karyawan/AksiMassalGaji';
import type { BarisGajiKaryawan } from '@/Tipe/Karyawan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

function Konteks(uuid: string[]) {
    return {
        terpilih: uuid.map((u) => ({ UuidKaryawan: u, Nama: `Karyawan ${u}` }) as unknown as BarisGajiKaryawan),
        semuaHasil: false,
        total: uuid.length,
        keadaan: { cari: '', urut: [], halaman: 1, perHalaman: 25, saring: {} },
        bersihkan: vi.fn(),
    };
}

describe('Aksi massal rekap gaji (tambahan)', () => {
    beforeEach(() => tiruanRouter.post.mockClear());
    afterEach(() => cleanup());

    it('mengirim jumlah, catatan, dan karyawan terpilih ke rekap yang dibuka', () => {
        RenderUji(
            <AksiMassalGaji
                alamat="/kelola/karyawan/gaji/01J9GJI0000000000000000001"
                konteks={Konteks(['01J9KRY0000000000000000001', '01J9KRY0000000000000000002'])}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Beri tambahan (THR, bonus)' }));
        expect(screen.getByRole('dialog').textContent).toContain('Beri tambahan ke 2 karyawan');
        expect((screen.getByRole('button', { name: 'Tambahkan' }) as HTMLButtonElement).disabled).toBe(true);

        fireEvent.change(screen.getByLabelText(/Tambahan per karyawan/), { target: { value: '250000' } });
        fireEvent.change(screen.getByLabelText(/Catatan/), { target: { value: 'THR 2026' } });
        fireEvent.click(screen.getByRole('button', { name: 'Tambahkan' }));

        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/karyawan/gaji/01J9GJI0000000000000000001/tambahan-massal',
            {
                Tambahan: '250000',
                Catatan: 'THR 2026',
                Uuid: ['01J9KRY0000000000000000001', '01J9KRY0000000000000000002'],
            },
            expect.anything(),
        );
    });
});
