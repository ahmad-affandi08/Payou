import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

type Pendengar = (peristiwa: { detail: { visit: { prefetch?: boolean } } }) => void;
const pendengar = new Map<string, Pendengar>();
const Picu = (nama: string, peristiwa: ReturnType<typeof Kunjungan>) => pendengar.get(nama)?.(peristiwa);

vi.mock('@inertiajs/react', () => ({
    router: {
        on: (nama: string, fungsi: Pendengar) => {
            pendengar.set(nama, fungsi);
            return () => {
                pendengar.delete(nama);
            };
        },
    },
}));

import { BATAS_TAMPIL_MS, JEDA_MUNCUL_MS, PasangPenandaNavigasi, TAMPIL_MINIMAL_MS } from '@/Pustaka/PenandaNavigasi';

const Kunjungan = (prefetch = false) => ({ detail: { visit: { prefetch } } });
const Bilah = () => document.querySelector<HTMLElement>('.penanda-navigasi');

describe('PenandaNavigasi (D-58, D-62)', () => {
    let Lepas: () => void;

    beforeEach(() => {
        vi.useFakeTimers();
        Lepas = PasangPenandaNavigasi();
    });

    afterEach(() => {
        Lepas();
        vi.useRealTimers();
    });

    it('tidak muncul untuk kunjungan yang selesai sebelum jeda', () => {
        Picu('start', Kunjungan());
        vi.advanceTimersByTime(JEDA_MUNCUL_MS - 50);
        Picu('finish', Kunjungan());
        vi.advanceTimersByTime(2000);

        expect(Bilah()?.dataset.keadaan).toBeUndefined();
    });

    it('muncul setelah jeda, tampil minimal 400 ms, lalu selesai', () => {
        Picu('start', Kunjungan());
        vi.advanceTimersByTime(JEDA_MUNCUL_MS);
        expect(Bilah()?.dataset.keadaan).toBe('jalan');

        Picu('finish', Kunjungan());
        expect(Bilah()?.dataset.keadaan).toBe('jalan');

        vi.advanceTimersByTime(TAMPIL_MINIMAL_MS);
        expect(Bilah()?.dataset.keadaan).toBe('selesai');
    });

    it('prefetch di latar tidak memunculkan bilah', () => {
        Picu('start', Kunjungan(true));
        vi.advanceTimersByTime(1000);

        expect(Bilah()?.dataset.keadaan).toBeUndefined();
    });

    it('dua kunjungan beruntun: bilah selesai setelah yang terakhir', () => {
        Picu('start', Kunjungan());
        Picu('start', Kunjungan());
        vi.advanceTimersByTime(JEDA_MUNCUL_MS);
        Picu('finish', Kunjungan());
        vi.advanceTimersByTime(TAMPIL_MINIMAL_MS + 50);
        expect(Bilah()?.dataset.keadaan).toBe('jalan');

        Picu('finish', Kunjungan());
        vi.advanceTimersByTime(TAMPIL_MINIMAL_MS);
        expect(Bilah()?.dataset.keadaan).toBe('selesai');
    });

    it('pelepas membuang elemen bilah', () => {
        Lepas();
        expect(Bilah()).toBeNull();
        Lepas = PasangPenandaNavigasi();
    });

    it('dipaksa selesai bila sinyal selesai tidak pernah datang', () => {
        Picu('start', Kunjungan());
        vi.advanceTimersByTime(JEDA_MUNCUL_MS);
        expect(Bilah()?.dataset.keadaan).toBe('jalan');

        vi.advanceTimersByTime(BATAS_TAMPIL_MS);
        expect(Bilah()?.dataset.keadaan).toBe('selesai');
    });
});

describe('PenandaNavigasi tirai layar penuh (D-65)', () => {
    it('memasang tirai yang membungkus tanda muat di tengah', () => {
        const Lepas = PasangPenandaNavigasi();
        const bilah = document.querySelector<HTMLElement>('.penanda-navigasi');

        expect(bilah?.querySelector('.penanda-navigasi__kotak .tanda-muat')).not.toBeNull();
        Lepas();
    });
});
