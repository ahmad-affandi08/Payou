import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { useState, type ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanGerbangPembayaran, { LangkahQris } from '@/Halaman/Kelola/Pembayaran/Gerbang';
import { PilihOpsi } from '@/Pengujian/InteraksiPilihan';
import type { GerbangPembayaranTenant, OpsiPenyediaGerbang } from '@/Tipe/Pembayaran';

/*
 * F-08 v2.06 gerbang pembayaran milik toko: bidang mengikuti penyedia, kredensial tersimpan hanya petunjuk,
 * aktifkan hanya setelah uji berhasil, URL webhook bisa disalin, dan isian ikut terkirim.
 */

const uji = vi.hoisted(() => ({
    kiriman: [] as { url: string; data: unknown }[],
    router: [] as string[],
    halaman: { props: { errors: {} } as Record<string, unknown>, url: '/kelola/pembayaran/gerbang' },
}));

vi.mock('@inertiajs/react', () => {
    function useForm<T extends object>(awal: T) {
        const [data, AturData] = useState<T>(awal);

        return {
            data,
            setData: (kunci: keyof T, nilai: unknown) => AturData((lama) => ({ ...lama, [kunci]: nilai })),
            errors: {},
            processing: false,
            isDirty: false,
            post: (url: string) => uji.kiriman.push({ url, data }),
        };
    }

    return {
        Head: () => null,
        Link: ({ href, children }: { href: string; children?: ReactNode }) => <a href={href}>{children}</a>,
        router: { post: (url: string) => uji.router.push(url), get: vi.fn() },
        usePage: () => uji.halaman,
        useForm,
    };
});

vi.mock('@/TataLetak/TataLetakAplikasi', () => ({
    default: ({ judul, children }: { judul: string; children: ReactNode }) => (
        <main>
            <h1>{judul}</h1>
            {children}
        </main>
    ),
}));

const penyedia: OpsiPenyediaGerbang[] = [
    {
        Nilai: 'Midtrans',
        Label: 'Midtrans',
        Keterangan: 'Core API QRIS.',
        BidangPengaturan: [
            { Kunci: 'Akuisitor', Label: 'Akuisitor QRIS', Jenis: 'Pilihan', Wajib: true, Opsi: ['gopay', 'airpay'] },
        ],
        BidangKredensial: [{ Kunci: 'KunciServer', Label: 'Server key', Wajib: true }],
    },
    {
        Nilai: 'Xendit',
        Label: 'Xendit',
        Keterangan: 'QR Codes API.',
        BidangPengaturan: [],
        BidangKredensial: [
            { Kunci: 'KunciRahasia', Label: 'Secret API key', Wajib: true },
            { Kunci: 'TokenCallback', Label: 'Token verifikasi callback', Wajib: true },
        ],
    },
];
const lingkungan = [
    { Nilai: 'Sandbox', Label: 'Sandbox (uji coba)' },
    { Nilai: 'Produksi', Label: 'Produksi (uang sungguhan)' },
];

function Gerbang(ubah: Partial<GerbangPembayaranTenant> = {}): GerbangPembayaranTenant {
    return {
        Uuid: '01K5GERBANG000000000000001',
        Penyedia: 'Midtrans',
        LabelPenyedia: 'Midtrans',
        PenyediaDiizinkan: true,
        Lingkungan: 'Sandbox',
        Pengaturan: { Akuisitor: 'gopay' },
        PetunjukKredensial: { KunciServer: '••••7788' },
        StatusUji: 'BelumDiuji',
        LabelStatusUji: 'Belum diuji',
        PesanUji: null,
        DiujiPada: null,
        Aktif: false,
        UrlWebhook: 'https://payoung.id/webhook/midtrans/1a-TokenWebhookToko',
        WebhookDiterimaPada: null,
        WebhookDitolakPada: null,
        ...ubah,
    };
}

beforeEach(() => {
    uji.kiriman.length = 0;
    uji.router.length = 0;
});
afterEach(() => cleanup());

describe('Gerbang pembayaran toko (v2.06)', () => {
    it('belum terhubung: pilih Xendit mengganti bidang kredensial lalu isian terkirim', () => {
        render(<HalamanGerbangPembayaran Gerbang={null} DaftarPenyedia={penyedia} DaftarLingkungan={lingkungan} />);

        expect(screen.getByText('Hubungkan QRIS dalam 4 langkah')).toBeTruthy();
        expect(screen.queryByText(/\(selesai\)/)).toBeNull();
        expect(screen.getByLabelText(/Server key/, { selector: 'input' })).toBeTruthy();
        PilihOpsi(screen.getByRole('combobox', { name: 'Penyedia' }), 'Xendit');
        expect(screen.queryByLabelText(/Server key/)).toBeNull();
        fireEvent.change(screen.getByLabelText(/Secret API key/, { selector: 'input' }), {
            target: { value: 'xnd_development_toko' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Simpan akun merchant' }));
        expect(uji.kiriman[0]).toEqual({
            url: '/kelola/pembayaran/gerbang',
            data: {
                Penyedia: 'Xendit',
                Lingkungan: 'Sandbox',
                Pengaturan: {},
                Kredensial: { KunciRahasia: 'xnd_development_toko', TokenCallback: '' },
            },
        });
    });

    it('tersimpan belum diuji: petunjuk kredensial tampil, aktifkan nonaktif, uji koneksi terkirim', () => {
        render(
            <HalamanGerbangPembayaran Gerbang={Gerbang()} DaftarPenyedia={penyedia} DaftarLingkungan={lingkungan} />,
        );

        expect(screen.getByText(/Tersimpan ••••7788/)).toBeTruthy();
        expect(screen.getByText('https://payoung.id/webhook/midtrans/1a-TokenWebhookToko')).toBeTruthy();
        expect((screen.getByRole('button', { name: 'Aktifkan gerbang' }) as HTMLButtonElement).disabled).toBe(true);
        fireEvent.click(screen.getByRole('button', { name: 'Uji koneksi' }));
        expect(uji.router).toEqual(['/kelola/pembayaran/gerbang/uji']);
    });

    it('uji berhasil: bisa diaktifkan; penyedia dilarang platform menampilkan peringatan dan tidak bisa diaktifkan', () => {
        const { unmount: Lepas } = render(
            <HalamanGerbangPembayaran
                Gerbang={Gerbang({ StatusUji: 'Berhasil', LabelStatusUji: 'Uji berhasil' })}
                DaftarPenyedia={penyedia}
                DaftarLingkungan={lingkungan}
            />,
        );
        fireEvent.click(screen.getByRole('button', { name: 'Aktifkan gerbang' }));
        expect(uji.router).toEqual(['/kelola/pembayaran/gerbang/aktifkan']);
        Lepas();

        render(
            <HalamanGerbangPembayaran
                Gerbang={Gerbang({ StatusUji: 'Berhasil', PenyediaDiizinkan: false })}
                DaftarPenyedia={penyedia.filter((p) => p.Nilai !== 'Midtrans')}
                DaftarLingkungan={lingkungan}
            />,
        );
        expect(screen.getByText(/sedang tidak tersedia dari platform/)).toBeTruthy();
        expect((screen.getByRole('button', { name: 'Aktifkan gerbang' }) as HTMLButtonElement).disabled).toBe(true);
    });

    it('#24 langkah QRIS ditandai selesai mengikuti status gerbang', () => {
        expect(LangkahQris(null).map((l) => l.Selesai)).toEqual([false, false, false, false]);
        expect(LangkahQris(Gerbang()).map((l) => l.Selesai)).toEqual([true, true, false, false]);
        expect(LangkahQris(Gerbang({ StatusUji: 'Berhasil' })).map((l) => l.Selesai)).toEqual([
            true,
            true,
            true,
            false,
        ]);
        expect(LangkahQris(Gerbang({ StatusUji: 'Berhasil', Aktif: true })).every((l) => l.Selesai)).toBe(true);
    });
});
