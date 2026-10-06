import { act, cleanup, fireEvent, render, renderHook, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import DialogTambahBlok from './DialogTambahBlok';
import { RingkasBlok } from './DaftarBlok';
import { BerinyaIdBlok, BuatMuatan, KunciDraf, type Draf } from './Tipe';
import { useRiwayat } from './useRiwayat';

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

const draf: Draf = {
    Slug: 'promo',
    Judul: 'Promo',
    JudulSeo: '',
    DeskripsiSeo: '',
    UuidGambarOg: null,
    TampilDiSitemap: true,
    Bagian: BerinyaIdBlok([{ Jenis: 'Hero', Judul: 'Halo' }]),
};

describe('Editor visual situs D-63', () => {
    it('muatan ke server tidak membawa penanda _id editor', () => {
        const muatan = BuatMuatan(draf);

        expect(muatan.Bagian).toEqual([{ Jenis: 'Hero', Judul: 'Halo' }]);
        expect(KunciDraf(draf)).not.toContain('_id');
        expect(draf.Bagian[0]?._id).toBeTruthy();
    });

    it('urungkan & ulangi, dan ketikan beruntun digabung menjadi satu langkah', () => {
        vi.useFakeTimers();
        const { result } = renderHook(() => useRiwayat('a'));

        act(() => result.current.ubah('b'));
        act(() => result.current.ubah('bc', true));
        act(() => result.current.ubah('bcd', true));
        expect(result.current.nilai).toBe('bcd');

        act(() => result.current.urungkan());
        expect(result.current.nilai).toBe('a');
        expect(result.current.bisaUrungkan).toBe(false);
        expect(result.current.bisaUlangi).toBe(true);

        act(() => result.current.ulangi());
        expect(result.current.nilai).toBe('bcd');
    });

    it('perubahan baru setelah urungkan membuang riwayat ulangi', () => {
        const { result } = renderHook(() => useRiwayat(1));

        act(() => result.current.ubah(2));
        act(() => result.current.urungkan());
        act(() => result.current.ubah(3));

        expect(result.current.bisaUlangi).toBe(false);
        expect(result.current.nilai).toBe(3);
    });

    it('ringkasan blok memakai judul, label, atau judul item pertama', () => {
        expect(RingkasBlok({ Jenis: 'Hero', Judul: ' Kasir ' })).toBe('Kasir');
        expect(RingkasBlok({ Jenis: 'Faq', Item: [{ Pertanyaan: 'Bisa offline?' }] })).toBe('Bisa offline?');
        expect(RingkasBlok({ Jenis: 'Pemisah' })).toBe('');
    });

    it('galeri tambah blok mengelompokkan jenis dan memilih saat kartu diklik', () => {
        const SaatPilih = vi.fn();

        render(
            <DialogTambahBlok
                terbuka
                saatTutup={() => undefined}
                jenis={['Hero', 'Faq']}
                label={{ Hero: 'Hero', Faq: 'Tanya jawab' }}
                saatPilih={SaatPilih}
                keterangan="Pilih blok."
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: /Tanya jawab/ }));
        expect(SaatPilih).toHaveBeenCalledWith('Faq');
    });
});
