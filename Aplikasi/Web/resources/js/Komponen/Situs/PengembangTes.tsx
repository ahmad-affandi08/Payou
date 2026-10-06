import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import Pengembang from '@/Halaman/Situs/Pengembang';
import type { PropsHalamanPengembang } from '@/Tipe/Situs';

const props: PropsHalamanPengembang = {
    AlamatApi: 'https://dashboard.payoung.id/api/v1',
    Versi: '1.0.0',
    Endpoint: [
        {
            Metode: 'GET',
            Jalur: '/api/v1/penjualan',
            Ringkasan: 'Penjualan per rentang tanggal bisnis (maks. 92 hari)',
            Cakupan: 'penjualan:baca',
            Parameter: [
                { Nama: 'per', Wajib: false },
                { Nama: 'dari', Wajib: true },
            ],
        },
    ],
    Webhook: [{ Peristiwa: 'penjualan.selesai', Ringkasan: 'Penjualan diterima server.' }],
    UnduhSpesifikasi: '/pengembang/openapi-v1.json',
};

vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props }), Head: () => null, Link: 'a' }));
vi.mock('@/TataLetak/TataLetakSitus', () => ({ default: ({ children }: { children: unknown }) => children }));

/* X7 bagian 3: portal pengembang menampilkan endpoint, cakupan, webhook, contoh verifikasi, dan tautan spesifikasi. */
describe('X7 portal pengembang', () => {
    afterEach(cleanup);

    it('menampilkan endpoint dari spesifikasi, parameter wajib, webhook, dan contoh verifikasi tanda tangan', () => {
        render(<Pengembang />);

        expect(screen.getByText('/api/v1/penjualan')).toBeTruthy();
        expect(screen.getByText('per, dari (wajib)')).toBeTruthy();
        expect(screen.getByText('penjualan.selesai')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'openapi-v1.json' }).getAttribute('href')).toBe(
            '/pengembang/openapi-v1.json',
        );
        expect(screen.getByText(/hash_equals/)).toBeTruthy();
        expect(screen.getByText(/timingSafeEqual/)).toBeTruthy();
    });
});
