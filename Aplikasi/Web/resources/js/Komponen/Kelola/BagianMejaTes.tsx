import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { AturHalamanUji, kirimanForm, RenderUji } from '@/Komponen/Katalog/TiruanInertia';
import BagianMeja from '@/Komponen/Kelola/BagianMeja';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const modeMeja = { Aktif: true, Area: [], Meja: [] };

beforeEach(() => AturHalamanUji({}, '/kelola/outlet/O-1'));
afterEach(() => cleanup());

describe('F-10a tambah banyak meja', () => {
    it('menampilkan pratinjau nama dan mengirim awalan, nomor awal, dan jumlah ke rute massal', () => {
        RenderUji(
            <BagianMeja
                alamatOutlet="/kelola/outlet/O-1"
                modeMeja={modeMeja}
                bentuk={[{ Nilai: 'Persegi', Label: 'Persegi' }]}
                bolehKelola
                pesanSendiri={{ FiturAktif: false, Aktif: false }}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Tambah banyak meja' }));
        expect(screen.getByRole('dialog').textContent).toContain('Akan dibuat: Meja 1 sampai Meja 10');

        fireEvent.change(screen.getByLabelText(/Jumlah meja/), { target: { value: '20' } });
        fireEvent.change(screen.getByLabelText(/Awalan nama/), { target: { value: '' } });
        expect(screen.getByRole('dialog').textContent).toContain('Akan dibuat: 1 sampai 20');

        fireEvent.click(screen.getByRole('button', { name: 'Buat meja' }));
        expect(kirimanForm).toHaveLength(1);
        expect(kirimanForm[0]).toMatchObject({
            metode: 'post',
            url: '/kelola/outlet/O-1/meja/massal',
            data: { Awalan: '', Mulai: '1', Jumlah: '20', Kapasitas: '4', Bentuk: 'Persegi' },
        });
    });

    it('tombol tidak tampil tanpa izin kelola', () => {
        RenderUji(
            <BagianMeja
                alamatOutlet="/kelola/outlet/O-1"
                modeMeja={modeMeja}
                bentuk={[]}
                bolehKelola={false}
                pesanSendiri={{ FiturAktif: false, Aktif: false }}
            />,
        );

        expect(screen.queryByRole('button', { name: 'Tambah banyak meja' })).toBeNull();
    });
});
