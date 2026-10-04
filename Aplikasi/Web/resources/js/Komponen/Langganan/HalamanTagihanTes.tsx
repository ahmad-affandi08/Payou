import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanTagihanLangganan from '@/Halaman/Kelola/Langganan/Tagihan';
import { AturHalamanUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import type { TagihanLangganan } from '@/Tipe/TagihanLangganan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const tagihan: TagihanLangganan = {
    Uuid: 'TG-1',
    Nomor: 'INV-2026-0001',
    Jenis: 'Aktivasi',
    LabelJenis: 'Aktivasi',
    Status: 'Terbit',
    LabelStatus: 'Menunggu pembayaran',
    KodePaket: 'PRO',
    NamaPaket: 'Pro',
    Siklus: 'Bulanan',
    JumlahBulan: 1,
    Subtotal: '200000.00',
    SubtotalPaket: '200000.00',
    RincianAddon: [],
    KodeKupon: null,
    Diskon: '0.00',
    TarifPpn: '12.000000',
    PengaliDppPembilang: 11,
    PengaliDppPenyebut: 12,
    DasarPengenaanPajak: '183333.00',
    JumlahPpn: '22000.00',
    Total: '222000.00',
    TerbitPada: '2026-09-20T02:00:00Z',
    JatuhTempoPada: '2026-09-27T02:00:00Z',
    DibayarPada: null,
    DibatalkanPada: null,
    PeriodeMulai: null,
    PeriodeSelesai: null,
};

const gerbangAktif = { KunciKlien: 'SB-Mid-client-abc', UrlSnapJs: 'https://app.sandbox.midtrans.com/snap/snap.js' };

function RenderTagihan(tambahan: Partial<PropsRender> = {}) {
    return render(
        <HalamanTagihanLangganan
            Tagihan={tagihan}
            Pembayaran={[]}
            BolehBayarOnline={tambahan.BolehBayarOnline ?? false}
            BolehBatalkan={tambahan.BolehBatalkan ?? true}
            Gerbang={tambahan.Gerbang ?? null}
        />,
    );
}

type PropsRender = {
    BolehBayarOnline: boolean;
    BolehBatalkan: boolean;
    Gerbang: { KunciKlien: string; UrlSnapJs: string } | null;
};

describe('Langganan/Tagihan (P-08): bayar online & konfirmasi batal', () => {
    beforeEach(() => AturHalamanUji({}, '/kelola/langganan/tagihan/TG-1'));
    afterEach(() => cleanup());

    it('batalkan tagihan hanya setelah dikonfirmasi', () => {
        RenderTagihan();

        fireEvent.click(screen.getByRole('button', { name: 'Batalkan tagihan' }));
        expect(screen.getByRole('alertdialog', { name: 'Batalkan tagihan INV-2026-0001?' })).toBeTruthy();
        expect(tiruanRouter.post).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', { name: 'Jangan batalkan' }));
        expect(screen.queryByRole('alertdialog')).toBeNull();
        expect(tiruanRouter.post).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', { name: 'Batalkan tagihan' }));
        fireEvent.click(screen.getByRole('button', { name: 'Batalkan tagihan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/langganan/tagihan/TG-1/batalkan',
            {},
            expect.anything(),
        );
    });

    it('tanpa izin batalkan: tidak ada tombol batalkan', () => {
        RenderTagihan({ BolehBatalkan: false });

        expect(screen.queryByRole('button', { name: 'Batalkan tagihan' })).toBeNull();
    });

    it('BR-P08.11: tombol bayar online hanya muncul saat gerbang billing aktif', () => {
        RenderTagihan({ BolehBayarOnline: true, Gerbang: gerbangAktif });

        expect(screen.getByRole('button', { name: 'Bayar online' })).toBeTruthy();
    });

    it('BR-P08.11: gerbang belum dikonfigurasi = tidak ada tombol bayar online', () => {
        RenderTagihan({ BolehBayarOnline: true, Gerbang: null });

        expect(screen.queryByRole('button', { name: 'Bayar online' })).toBeNull();
    });
});
