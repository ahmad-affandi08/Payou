import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PanelSubAkunPembayaran from '@/Komponen/Pengelola/Tenant/PanelSubAkunPembayaran';
import type { Tampilan360 } from '@/Tipe/TenantPengelola';

/*
 * Tahap 3 DOKU: panel sub account di detail tenant konsol. Tombol hanya aktif bila belum ada ID sub account, gerbang
 * platform sudah diisi, dan pelaku berizin; status, ID, dan pesan galat tampil apa adanya.
 */

const uji = vi.hoisted(() => ({
    kiriman: [] as { url: string; data: unknown }[],
}));

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }: { href: string; children?: ReactNode }) => <a href={href}>{children}</a>,
    router: { post: (url: string, data: unknown) => uji.kiriman.push({ url, data }) },
}));

type Data = Tampilan360['SubAkunPembayaran'];

const BuatSub = (ubah: Partial<NonNullable<Data['Sub']>> = {}): NonNullable<Data['Sub']> => ({
    Uuid: '01K5SUBAKUN0000000000001',
    Penyedia: 'Doku',
    IdSubAkun: 'SAC-1234-5678',
    Status: 'Menunggu',
    LabelStatus: 'Menunggu',
    PesanGalat: null,
    BisaDibuat: false,
    DibuatPada: '2026-11-23T03:00:00+00:00',
    ...ubah,
});

function Tampil(data: Data, bolehKelola = true) {
    render(<PanelSubAkunPembayaran uuidTenant="01K5TENANT00000000000001" data={data} bolehKelola={bolehKelola} />);
}

beforeEach(() => {
    uji.kiriman.length = 0;
});
afterEach(() => cleanup());

describe('PanelSubAkunPembayaran', () => {
    it('belum dibuat: status "Belum dibuat", tombol aktif dan mengirim POST tanpa isian ke rute tenant', () => {
        Tampil({ Sub: null, GerbangPlatformAktif: true });

        expect(screen.getByText('Sub account pembayaran (DOKU)')).toBeTruthy();
        expect(screen.getByText('Belum dibuat')).toBeTruthy();
        expect(screen.getByText('Belum ada')).toBeTruthy();
        const tombol = screen.getByRole('button', { name: 'Buat sub account otomatis' }) as HTMLButtonElement;
        expect(tombol.disabled).toBe(false);

        fireEvent.click(tombol);
        expect(uji.kiriman).toEqual([{ url: '/tenant/01K5TENANT00000000000001/sub-akun-pembayaran', data: {} }]);
    });

    it('sudah punya ID: ID & status tampil, tombol nonaktif dengan keterangan, tidak ada kiriman', () => {
        Tampil({ Sub: BuatSub(), GerbangPlatformAktif: true });

        expect(screen.getByText('SAC-1234-5678')).toBeTruthy();
        expect(screen.getAllByText('Menunggu').length).toBeGreaterThan(0);
        const tombol = screen.getByRole('button', { name: 'Buat sub account otomatis' }) as HTMLButtonElement;
        expect(tombol.disabled).toBe(true);
        expect(screen.getByText('Sub account sudah terdaftar di DOKU.')).toBeTruthy();

        fireEvent.click(tombol);
        expect(uji.kiriman).toHaveLength(0);
    });

    it('sudah punya ID dan Aktif: label Aktif', () => {
        Tampil({ Sub: BuatSub({ Status: 'Aktif', LabelStatus: 'Aktif' }), GerbangPlatformAktif: true });

        expect(screen.getByText('Aktif')).toBeTruthy();
    });

    it('gagal tanpa ID: pesan galat tampil dan tombol "Coba buat lagi" aktif', () => {
        Tampil({
            Sub: BuatSub({
                IdSubAkun: null,
                Status: 'Gagal',
                LabelStatus: 'Gagal',
                PesanGalat: 'DOKU menolak pembuatan sub account (HTTP 400): Email sudah dipakai.',
                BisaDibuat: true,
            }),
            GerbangPlatformAktif: true,
        });

        expect(screen.getByText(/Email sudah dipakai/)).toBeTruthy();
        const tombol = screen.getByRole('button', { name: 'Coba buat lagi' }) as HTMLButtonElement;
        expect(tombol.disabled).toBe(false);

        fireEvent.click(tombol);
        expect(uji.kiriman).toHaveLength(1);
    });

    it('gerbang platform belum aktif: tombol nonaktif dan tautan ke menu Integrasi', () => {
        Tampil({ Sub: null, GerbangPlatformAktif: false });

        expect((screen.getByRole('button', { name: 'Buat sub account otomatis' }) as HTMLButtonElement).disabled).toBe(
            true,
        );
        expect(screen.getByText(/Akun DOKU Payoung \(induk\) belum diisi/)).toBeTruthy();
        expect(screen.getByRole('link', { name: 'menu Integrasi' }).getAttribute('href')).toBe('/integrasi');
    });

    it('tanpa izin kelola integrasi: tombol nonaktif dan alasan tampil', () => {
        Tampil({ Sub: null, GerbangPlatformAktif: true }, false);

        expect((screen.getByRole('button', { name: 'Buat sub account otomatis' }) as HTMLButtonElement).disabled).toBe(
            true,
        );
        expect(screen.getByText(/izin kelola integrasi/)).toBeTruthy();
    });
});
