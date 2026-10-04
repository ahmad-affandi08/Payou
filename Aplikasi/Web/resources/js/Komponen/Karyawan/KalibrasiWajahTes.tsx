import { cleanup, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PanelKalibrasiWajah, { SusunSaranKalibrasi, TulisDesimal } from '@/Komponen/Karyawan/PanelKalibrasiWajah';
import { AturHalamanUji, RenderUji } from '@/Komponen/Katalog/TiruanInertia';
import type { KalibrasiWajah } from '@/Tipe/Karyawan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

/* F-18 bagian 4 (D-37, K37): panel kalibrasi ambang kemiripan wajah absensi web. */
const dasar: KalibrasiWajah = {
    Ambang: '0.60',
    Hari: 30,
    JumlahDiterima: 90,
    JumlahDitolak: 4,
    PersenDitolak: '4.3',
    TerendahDiterima: '0.62',
    MedianDiterima: '0.88',
    TertinggiDitolak: '0.57',
    CukupData: true,
    Kelompok: [
        { Dari: '0.00', Sampai: '0.30', Diterima: 0, Ditolak: 1 },
        { Dari: '0.55', Sampai: '0.60', Diterima: 0, Ditolak: 3 },
        { Dari: '0.60', Sampai: '0.65', Diterima: 2, Ditolak: 0 },
        { Dari: '0.85', Sampai: '0.90', Diterima: 88, Ditolak: 0 },
    ],
};

beforeEach(() => AturHalamanUji({}, '/kelola/karyawan/absensi'));
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('Kalibrasi wajah', () => {
    it('saran: data kurang → info, penolakan tinggi → peringatan, selain itu wajar', () => {
        expect(SusunSaranKalibrasi({ ...dasar, CukupData: false }).jenis).toBe('info');
        expect(SusunSaranKalibrasi({ ...dasar, PersenDitolak: '22.5' }).jenis).toBe('peringatan');
        expect(SusunSaranKalibrasi(dasar).jenis).toBe('sukses');
        expect(TulisDesimal('0.60')).toBe('0,60');
        expect(TulisDesimal(null)).toBe('–');
    });

    it('menampilkan ringkasan, kelompok ambang, dan jumlah per kelompok', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(new Response(JSON.stringify(dasar), { status: 200 }))),
        );
        RenderUji(<PanelKalibrasiWajah />);

        expect(await screen.findByText('0,60–0,65 | ambang')).not.toBeNull();
        expect(screen.getByText('< 0,30')).not.toBeNull();
        expect(screen.getByText('88 diterima')).not.toBeNull();
        expect(screen.getByText('4 (4,3%)')).not.toBeNull();
        expect(screen.getByText(/Sebaran wajar/)).not.toBeNull();
    });
});
