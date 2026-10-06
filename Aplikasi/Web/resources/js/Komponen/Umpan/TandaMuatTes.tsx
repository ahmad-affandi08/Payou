import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { TutupLayarMuat } from '@/Pustaka/LayarMuat';
import { TandaMuat } from '@/Komponen/Umpan/TandaMuat';

describe('TandaMuat (D-58)', () => {
    it('berperan status "Memuat" dengan logo payung di dalam lingkaran dan ukuran dari prop', () => {
        const { container } = render(<TandaMuat ukuran={48} />);

        const tanda = screen.getByRole('status', { name: 'Memuat' });
        expect(tanda.style.getPropertyValue('--u')).toBe('48px');
        expect(container.querySelector('.tanda-muat__isi img')).not.toBeNull();
    });

    it('label bisa diganti untuk pembaca layar', () => {
        render(<TandaMuat label="Menyiapkan kasir" />);

        expect(screen.getByRole('status', { name: 'Menyiapkan kasir' })).toBeTruthy();
    });
});

describe('TutupLayarMuat', () => {
    it('menandai layar selesai lalu membuangnya', async () => {
        document.body.innerHTML = '<div id="layar-muat"></div>';
        TutupLayarMuat();
        expect(document.getElementById('layar-muat')?.hasAttribute('data-selesai')).toBe(true);

        await new Promise((selesai) => setTimeout(selesai, 400));
        expect(document.getElementById('layar-muat')).toBeNull();
    });

    it('aman bila tidak ada layar muat (halaman bukan hasil muat penuh)', () => {
        document.body.innerHTML = '';
        expect(() => TutupLayarMuat()).not.toThrow();
    });
});
