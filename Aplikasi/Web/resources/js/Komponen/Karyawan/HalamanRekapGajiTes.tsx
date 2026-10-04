import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanRekapGaji from '@/Halaman/Kelola/Karyawan/Gaji';
import HalamanDetailRekapGaji from '@/Halaman/Kelola/Karyawan/DetailGaji';
import { AturHalamanUji, kirimanForm, RenderUji } from '@/Komponen/Katalog/TiruanInertia';
import { BuatHasilTabel } from '@/Komponen/Persediaan/DataUjiPersediaan';
import { BukaMenu } from '@/Pengujian/InteraksiRadix';
import type { BarisGajiKaryawan, BarisRekapGaji, PropsDetailRekapGaji } from '@/Tipe/Karyawan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const rekap: BarisRekapGaji = {
    Uuid: 'R1',
    Periode: '2026-10',
    LabelPeriode: 'Oktober 2026',
    Status: 'Draf',
    LabelStatus: 'Draf',
    TotalKotor: '4500000.00',
    TotalPotongan: '850000.00',
    TotalBersih: '3650000.00',
    TanggalBayar: null,
};

const baris: BarisGajiKaryawan = {
    UuidKaryawan: 'KR1',
    Nama: 'Dewi Junior',
    Jabatan: 'Terapis',
    GajiPokok: '1000000.00',
    Komisi: '0.00',
    Tambahan: '500000.00',
    LemburMenit: 0,
    Lembur: '0.00',
    TerlambatMenit: 0,
    PotonganTerlambat: '0.00',
    HariTidakMasuk: 0,
    PotonganTidakMasuk: '0.00',
    Kotor: '1500000.00',
    PotonganKasbon: '800000.00',
    PotonganLain: '50000.00',
    Bersih: '650000.00',
    Catatan: 'Lembur 5 hari',
    SisaKasbon: '1300000.00',
};

function PropsDetail(status: BarisRekapGaji['Status'] = 'Draf'): PropsDetailRekapGaji {
    return {
        Rekap: {
            ...rekap,
            Status: status,
            LabelStatus: status,
            AkunKasBank: null,
            AkunBeban: null,
            Jurnal: status === 'Dibayar' ? { Uuid: 'J1', Nomor: 'JU-2610-0001' } : null,
        },
        Baris: [baris],
        OpsiAkunKasBank: [{ Uuid: 'A1', Kode: '1-1100', Nama: '1-1100 Kas Outlet' }],
        OpsiAkunBeban: [
            { Uuid: 'B0', Kode: '6-0100', Nama: '6-0100 Beban Sewa' },
            { Uuid: 'B1', Kode: '6-1000', Nama: '6-1000 Beban Gaji & Komisi' },
        ],
    };
}

describe('Rekap gaji (F-18 bagian 3)', () => {
    beforeEach(() => AturHalamanUji({}, '/kelola/karyawan/gaji'));
    afterEach(() => cleanup());

    it('daftar: buat rekap mengirim periode terbaru yang tersedia', () => {
        RenderUji(
            <HalamanRekapGaji
                Rekap={BuatHasilTabel([rekap])}
                OpsiPeriode={[
                    { Nilai: '2026-09', Label: 'September 2026' },
                    { Nilai: '2026-08', Label: 'Agustus 2026' },
                ]}
                OpsiStatus={[
                    { Nilai: 'Draf', Label: 'Draf' },
                    { Nilai: 'Dibayar', Label: 'Dibayar' },
                ]}
            />,
        );
        expect(screen.getAllByText('Oktober 2026').length).toBeGreaterThan(0);
        fireEvent.click(screen.getByRole('button', { name: 'Buat rekap gaji' }));
        fireEvent.click(screen.getByRole('button', { name: 'Buat draf' }));
        expect(kirimanForm.at(-1)).toMatchObject({
            metode: 'post',
            url: '/kelola/karyawan/gaji',
            data: { Periode: '2026-09' },
        });
    });

    it('draf: ubah baris mengirim nilai tanpa desimal nol; bayar memilih akun beban gaji 6-1000', () => {
        RenderUji(<HalamanDetailRekapGaji {...PropsDetail()} />);
        BukaMenu(screen.getAllByRole('button', { name: /Aksi gaji Dewi Junior/ })[0] as HTMLElement);
        fireEvent.click(screen.getByRole('menuitem', { name: 'Ubah tambahan & potongan' }));
        fireEvent.click(screen.getByRole('button', { name: 'Simpan gaji' }));
        expect(kirimanForm.at(-1)).toMatchObject({
            metode: 'put',
            url: '/kelola/karyawan/gaji/R1/baris/KR1',
            data: { Tambahan: '500000', PotonganKasbon: '800000', PotonganLain: '50000', Catatan: 'Lembur 5 hari' },
        });
        cleanup();
        RenderUji(<HalamanDetailRekapGaji {...PropsDetail()} />);
        fireEvent.click(screen.getByRole('button', { name: 'Bayar gaji' }));
        fireEvent.click(screen.getByRole('button', { name: 'Bayar dan jurnal' }));
        expect(kirimanForm.at(-1)).toMatchObject({
            metode: 'post',
            url: '/kelola/karyawan/gaji/R1/bayar',
            data: { AkunKasBank: 'A1', AkunBeban: 'B1' },
        });
    });

    it('sudah dibayar: tanpa tombol bayar/hapus & tanpa ubah baris (hanya cetak slip, v3.35); tautan jurnal tampil', () => {
        RenderUji(<HalamanDetailRekapGaji {...PropsDetail('Dibayar')} />);
        expect(screen.queryByRole('button', { name: 'Bayar gaji' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Hapus draf' })).toBeNull();
        const [aksi] = screen.getAllByRole('button', { name: /Aksi gaji/ });
        expect(aksi).toBeTruthy();
        fireEvent.keyDown(aksi as HTMLElement, { key: 'Enter' });
        expect(screen.queryByRole('menuitem', { name: 'Ubah tambahan & potongan' })).toBeNull();
        expect(screen.getByRole('menuitem', { name: 'Cetak slip gaji' }).getAttribute('href')).toMatch(
            /\/kelola\/karyawan\/gaji\/.+\/slip\?karyawan=/,
        );
        expect(screen.getByRole('link', { name: 'Cetak semua slip' }).getAttribute('href')).toMatch(/\/slip$/);
        expect(screen.getByRole('link', { name: 'JU-2610-0001' }).getAttribute('href')).toBe(
            '/kelola/akuntansi/jurnal/J1',
        );
    });
});
