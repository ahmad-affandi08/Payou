import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AjakanTambahBatas from '@/Komponen/Kelola/AjakanTambahBatas';

const uji = vi.hoisted(() => ({ props: { Edisi: 'Saas' } as { Edisi?: string } }));

vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: uji.props }) }));

describe('AjakanTambahBatas (D-35)', () => {
    beforeEach(() => {
        uji.props = { Edisi: 'Saas' };
    });

    afterEach(cleanup);

    it('edisi SaaS: tautan ke menu Langganan tetap tampil', () => {
        render(
            <p>
                <AjakanTambahBatas>
                    <a href="/kelola/langganan">menu Langganan</a>
                </AjakanTambahBatas>
            </p>,
        );

        expect(screen.getByRole('link', { name: 'menu Langganan' })).not.toBeNull();
    });

    it('edisi Lisensi: tautan diganti ajakan ke penjual lisensi, atau dihilangkan', () => {
        uji.props = { Edisi: 'Lisensi' };
        const { container } = render(
            <p>
                <AjakanTambahBatas>
                    <a href="/kelola/langganan">menu Langganan</a>
                </AjakanTambahBatas>
                <AjakanTambahBatas teksLisensi={null}>
                    <a href="/pengembang">dokumentasi API</a>
                </AjakanTambahBatas>
            </p>,
        );

        expect(screen.queryByRole('link')).toBeNull();
        expect(container.textContent).toBe('minta berkas lisensi dengan batas lebih besar ke penjual lisensi Payoung');
    });
});
