import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { AmbilNilaiPilihan, BukaPilihan, PilihOpsi } from '@/Pengujian/InteraksiPilihan';

import PilihanCari, { CocokkanCari } from './PilihanCari';

afterEach(() => cleanup());

const opsiKota = [
    { Nilai: '3471', Label: 'Kota Yogyakarta', Keterangan: 'DI Yogyakarta' },
    { Nilai: '3404', Label: 'Kab. Sleman', Keterangan: 'DI Yogyakarta' },
    { Nilai: '3578', Label: 'Kota Surabaya', Keterangan: 'Jawa Timur' },
];

function Terkendali({ saatBerubah }: { saatBerubah: (nilai: string) => void }) {
    const [nilai, AturNilai] = useState('');

    return (
        <>
            <label htmlFor="kota">Kota</label>
            <PilihanCari
                id="kota"
                label="Kota"
                nilai={nilai}
                opsi={opsiKota}
                kosong="Semua kota"
                saatBerubah={(baru) => {
                    saatBerubah(baru);
                    AturNilai(baru);
                }}
            />
        </>
    );
}

describe('PilihanCari (select ber-cari, §17.6)', () => {
    it('cocokkan cari: tanpa beda huruf, setiap kata, termasuk keterangan', () => {
        expect(CocokkanCari(opsiKota[0] ?? { Nilai: '', Label: '' }, 'kota yogya')).toBe(true);
        expect(CocokkanCari(opsiKota[1] ?? { Nilai: '', Label: '' }, 'yogyakarta')).toBe(true);
        expect(CocokkanCari(opsiKota[2] ?? { Nilai: '', Label: '' }, 'yogyakarta')).toBe(false);
    });

    it('daftar dibatasi tinggi layar yang tersedia dan tetap bisa digulir (regresi layar HP)', () => {
        render(<Terkendali saatBerubah={vi.fn()} />);
        BukaPilihan(screen.getByRole('combobox', { name: 'Kota' }));

        // Tanpa batas ini popover lebih tinggi dari layar HP, terpotong viewport, dan tidak ada yang bisa digulir:
        // hanya beberapa baris pertama yang terlihat.
        const isi = document.querySelector('[data-slot="popover-content"]');
        expect(isi?.className).toContain('max-h-(--radix-popover-content-available-height)');
        expect(isi?.className).toContain('overflow-hidden');

        // Daftarnya yang menyusut dan menggulir, bukan popover-nya yang meluber.
        const daftar = document.querySelector('[data-slot="command-list"]');
        expect(daftar?.className).toContain('overflow-y-auto');
        expect(daftar?.className).toContain('flex-1');
        expect(daftar?.className).toContain('overscroll-contain');
    });

    it('daftar terbuka di bawah pemicu (tidak menimpanya), bisa dicari, dan menandai pilihan aktif', () => {
        const SaatBerubah = vi.fn();
        render(<Terkendali saatBerubah={SaatBerubah} />);
        const pemicu = screen.getByRole('combobox', { name: 'Kota' });

        expect(pemicu.textContent).toContain('Semua kota');
        expect(AmbilNilaiPilihan(pemicu)).toEqual(['', '3471', '3404', '3578']);

        const daftar = BukaPilihan(pemicu);
        expect(daftar.closest('[data-side]')?.getAttribute('data-side')).toBe('bottom');
        expect(pemicu.getAttribute('aria-expanded')).toBe('true');

        fireEvent.change(screen.getByRole('textbox', { name: 'Cari Kota' }), { target: { value: 'jawa' } });
        expect(
            Array.from(daftar.querySelectorAll('[data-slot="pilihan-cari-item"]')).map((el) =>
                el.getAttribute('data-nilai'),
            ),
        ).toEqual(['3578']);

        PilihOpsi(pemicu, '3578');
        expect(SaatBerubah).toHaveBeenLastCalledWith('3578');
        expect(pemicu.textContent).toContain('Kota Surabaya');
        expect(pemicu.getAttribute('aria-expanded')).toBe('false');
    });

    it('mengetik huruf saat pemicu fokus langsung membuka daftar dengan kata cari itu', () => {
        render(<Terkendali saatBerubah={() => undefined} />);
        const pemicu = screen.getByRole('combobox', { name: 'Kota' });

        fireEvent.keyDown(pemicu, { key: 's' });
        expect(pemicu.getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole<HTMLInputElement>('textbox', { name: 'Cari Kota' }).value).toBe('s');
    });
});

describe('PilihanCari di layar sentuh & di halaman biasa (regresi dropdown tertutup keyboard HP)', () => {
    const MatchMediaAsli = window.matchMedia;

    afterEach(() => {
        window.matchMedia = MatchMediaAsli;
    });

    function PakaiSentuh(sentuh: boolean) {
        window.matchMedia = ((kueri: string) => ({
            matches: sentuh && kueri.includes('pointer: coarse'),
            media: kueri,
            onchange: null,
            addEventListener: () => undefined,
            removeEventListener: () => undefined,
            addListener: () => undefined,
            removeListener: () => undefined,
            dispatchEvent: () => false,
        })) as typeof window.matchMedia;
    }

    it('di layar sentuh kotak cari tidak otomatis fokus, supaya keyboard tidak menutupi daftar', () => {
        PakaiSentuh(true);
        render(<Terkendali saatBerubah={() => undefined} />);
        BukaPilihan(screen.getByRole('combobox', { name: 'Kota' }));

        expect(document.activeElement).not.toBe(screen.getByRole('textbox', { name: 'Cari Kota' }));
    });

    it('di desktop kotak cari tetap otomatis fokus', () => {
        PakaiSentuh(false);
        render(<Terkendali saatBerubah={() => undefined} />);
        BukaPilihan(screen.getByRole('combobox', { name: 'Kota' }));

        expect(document.activeElement).toBe(screen.getByRole('textbox', { name: 'Cari Kota' }));
    });

    it('di halaman biasa popover non-modal: halaman tidak dikunci gulirnya', () => {
        render(<Terkendali saatBerubah={() => undefined} />);
        BukaPilihan(screen.getByRole('combobox', { name: 'Kota' }));

        // Mode modal memasang aria-hidden pada sisa halaman dan mengunci gulir; non-modal tidak.
        expect(document.body.style.pointerEvents).not.toBe('none');
        expect(document.querySelector('label[for="kota"]')?.closest('[aria-hidden="true"]')).toBeNull();
    });

    it('tinggi isi popover juga dibatasi area terlihat saat keyboard naik', () => {
        render(<Terkendali saatBerubah={() => undefined} />);
        BukaPilihan(screen.getByRole('combobox', { name: 'Kota' }));

        const isi = document.querySelector<HTMLElement>('[data-slot="popover-content"]');
        expect(isi?.style.maxHeight).toContain('--tinggi-tampak');
        expect(isi?.style.maxHeight).toContain('--radix-popover-content-available-height');
    });
});

describe('Penjaga: select bawaan peramban tidak dipakai (§17.6)', () => {
    it('tidak ada <select> / NativeSelect di Halaman & Komponen (kecuali Komponen/Ui)', () => {
        const berkas = import.meta.glob(['/resources/js/Halaman/**/*.tsx', '/resources/js/Komponen/**/*.tsx'], {
            query: '?raw',
            import: 'default',
            eager: true,
        });
        const pelanggar = Object.entries(berkas)
            .filter(([jalur]) => !jalur.endsWith('Tes.tsx') && !jalur.includes('/Komponen/Ui/'))
            .filter(([, isi]) => /<select[\s>]|<NativeSelect\b/.test(String(isi)))
            .map(([jalur]) => jalur);

        expect(Object.keys(berkas).length).toBeGreaterThan(50);
        expect(pelanggar).toEqual([]);
    });
});
