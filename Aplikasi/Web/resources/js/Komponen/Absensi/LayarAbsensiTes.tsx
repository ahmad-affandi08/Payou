import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { CekKodeQrLengkap, NormalkanKodeQr } from '@/Fitur/Absensi/KodeQr';
import LayarAbsensi, { HitungSisaDetik } from '@/Halaman/Publik/LayarAbsensi';
import { AturHalamanUji, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import LokasiAbsensiOutlet from '@/Komponen/Kelola/LokasiAbsensiOutlet';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

/*
 * F-18 bagian 4 (D-37): layar QR absensi outlet (kode 6 digit berganti tiap 30 detik) dan pengaturannya di detail
 * outlet (buat tautan layar, wajibkan QR, buat ulang & cabut lewat konfirmasi).
 */
beforeEach(() => {
    AturHalamanUji({}, '/kelola/outlet/O-1');
    vi.stubGlobal(
        'fetch',
        vi.fn(() => new Promise(() => undefined)),
    );
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('Kode QR absensi', () => {
    it('membuang selain angka, memotong ke 6 digit, dan hanya 6 digit yang lengkap', () => {
        expect(NormalkanKodeQr(' 123-456 789')).toBe('123456');
        expect(CekKodeQrLengkap('12345')).toBe(false);
        expect(CekKodeQrLengkap('123456')).toBe(true);
    });

    it('sisa detik dibulatkan ke atas dan tidak negatif', () => {
        const batas = '2026-10-05T01:00:30Z';
        expect(HitungSisaDetik(batas, Date.parse('2026-10-05T01:00:00.200Z'))).toBe(30);
        expect(HitungSisaDetik(batas, Date.parse('2026-10-05T01:00:31Z'))).toBe(0);
    });
});

describe('Layar QR absensi', () => {
    it('menampilkan nama outlet, QR, kode terpisah 3-3, dan keterangan wajib', () => {
        RenderUji(
            <LayarAbsensi
                NamaToko="Kedai Kopi Senja"
                NamaOutlet="Outlet Solo"
                WajibQr
                Kode="482913"
                BerlakuSampai={new Date(Date.now() + 20_000).toISOString()}
                Qr="<svg xmlns='http://www.w3.org/2000/svg'></svg>"
                AlamatKode="/kopi-senja/layar-absen/aaa/kode"
            />,
        );

        expect(screen.getByRole('heading', { level: 1, name: 'Absen Outlet Solo' })).not.toBeNull();
        expect(screen.getByRole('img', { name: /kode 482913/ })).not.toBeNull();
        expect(screen.getByText('482 913')).not.toBeNull();
        expect(screen.getByText(/Berganti dalam \d+ detik/)).not.toBeNull();
        expect(screen.getByText(/wajib memakai kode ini/)).not.toBeNull();
    });
});

describe('Pengaturan layar QR di detail outlet', () => {
    const lokasi = { Lintang: '-7.5560000', Bujur: '110.8310000', RadiusMeter: 100 };

    it('tanpa layar: tombol buat tautan, tanpa sakelar wajib', () => {
        RenderUji(<LokasiAbsensiOutlet alamatOutlet="/kelola/outlet/O-1" data={lokasi} bolehKelola />);

        fireEvent.click(screen.getByRole('button', { name: 'Buat tautan layar QR' }));
        expect(tiruanRouter.post).toHaveBeenLastCalledWith('/kelola/outlet/O-1/layar-absensi', {}, expect.anything());
        expect(screen.queryByRole('checkbox')).toBeNull();
    });

    it('dengan layar: sakelar wajib QR, buka layar, cabut lewat konfirmasi', () => {
        const tautan = 'https://dashboard.payoung.id/kopi-senja/layar-absen/' + 'b'.repeat(40);
        RenderUji(
            <LokasiAbsensiOutlet
                alamatOutlet="/kelola/outlet/O-1"
                data={{ ...lokasi, TautanLayar: tautan, WajibQr: false }}
                bolehKelola
            />,
        );

        expect((screen.getByRole('link', { name: 'Buka layar' }) as HTMLAnchorElement).href).toBe(tautan);
        fireEvent.click(screen.getByRole('checkbox', { name: /Wajibkan pindai QR/ }));
        expect(tiruanRouter.post).toHaveBeenLastCalledWith(
            '/kelola/outlet/O-1/wajib-qr-absensi',
            { Wajib: true },
            expect.anything(),
        );

        // Tiruan router tidak memanggil onFinish (tombol tetap "memproses"), jadi cabut diuji di render baru.
        cleanup();
        RenderUji(
            <LokasiAbsensiOutlet
                alamatOutlet="/kelola/outlet/O-1"
                data={{ ...lokasi, TautanLayar: tautan, WajibQr: true }}
                bolehKelola
            />,
        );
        fireEvent.click(screen.getByRole('button', { name: 'Cabut layar' }));
        expect(tiruanRouter.delete).not.toHaveBeenCalled();
        fireEvent.click(screen.getAllByRole('button', { name: 'Cabut layar' }).at(-1) as HTMLElement);
        expect(tiruanRouter.delete).toHaveBeenCalledWith('/kelola/outlet/O-1/layar-absensi', expect.anything());
    });
});
