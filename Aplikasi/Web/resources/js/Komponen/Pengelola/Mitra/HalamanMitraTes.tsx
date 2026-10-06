import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { useState, type ReactElement, type ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarMitra from '@/Halaman/Pengelola/Mitra/Daftar';
import HalamanTampilMitra from '@/Halaman/Pengelola/Mitra/Tampil';
import { IzinPengelola, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

/*
 * P-12 (v3.50) halaman konsol mitra: daftar dengan tautan rincian & komisi tertunda; rincian menampilkan tautan
 * pendaftaran, rekening tersamar, dan tombol sesuai izin (ubah = mitra.kelola, catat pencairan = mitra.pencairan).
 */

const uji = vi.hoisted(() => ({
    kiriman: [] as { metode: string; url: string; data: unknown }[],
    halaman: { props: {} as Record<string, unknown>, url: '/mitra' },
}));

vi.mock('@inertiajs/react', () => {
    function useForm<T extends object>(awal: T) {
        const [data, AturData] = useState<T>(awal);
        const Kirim = (metode: string) => (url: string) => uji.kiriman.push({ metode, url, data });
        return {
            data,
            setData: (kunci: keyof T | ((lama: T) => T), nilai?: unknown) =>
                typeof kunci === 'function' ? AturData(kunci) : AturData((lama) => ({ ...lama, [kunci]: nilai })),
            errors: {},
            processing: false,
            post: Kirim('post'),
            put: Kirim('put'),
        };
    }
    return {
        Head: () => null,
        Link: ({ href, children, ...sisa }: { href: string; children?: ReactNode }) => (
            <a href={href} {...sisa}>
                {children}
            </a>
        ),
        router: { get: vi.fn(), post: vi.fn(), visit: vi.fn(), reload: vi.fn() },
        usePage: () => uji.halaman,
        useForm,
    };
});

function AturHalaman(izin: string[]) {
    const props: PropsBersamaPengelola = {
        NamaAplikasi: 'Kasir',
        Lingkungan: 'Staging',
        Kilat: null,
        Pengguna: { Uuid: 'P1', Nama: 'Dewi Lestari', Email: 'dewi@contoh.id', KodePeran: [], Izin: izin },
        PeringatanSuperAdmin: false,
        PeringatanIntegrasi: [],
        PeringatanOperasional: [],
        errors: {},
    };
    uji.halaman.props = props;
    uji.kiriman.length = 0;
}

function Render(elemen: ReactElement) {
    return render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            {elemen}
        </QueryClientProvider>,
    );
}

const opsiJenis = [
    { Nilai: 'Reseller', Label: 'Reseller/agen daerah' },
    { Nilai: 'Referral', Label: 'Referral' },
];

afterEach(cleanup);

describe('konsol mitra', () => {
    it('daftar: tautan ke rincian, komisi tertunda dalam Rupiah, tombol tambah hanya dengan mitra.kelola', () => {
        AturHalaman([IzinPengelola.MitraLihat]);
        const mitra = {
            Uuid: '01JMITRA00000000000000001',
            Kode: 'AGEN-SOLO',
            Nama: 'CV Agen Kasir Solo',
            Jenis: 'Reseller',
            LabelJenis: 'Reseller/agen daerah',
            Status: 'Aktif',
            PersenKomisi: '20.00',
            KomisiBerulang: true,
            JumlahTenant: 3,
            KomisiTertunda: '87000.00',
        };
        Render(<HalamanDaftarMitra Mitra={[mitra]} OpsiJenis={opsiJenis} />);
        expect(screen.getAllByRole('link', { name: /CV Agen Kasir Solo/ })[0]?.getAttribute('href')).toBe(
            '/mitra/01JMITRA00000000000000001',
        );
        expect(screen.getAllByText('Rp 87.000').length).toBeGreaterThan(0);
        expect(screen.queryByRole('button', { name: 'Tambah mitra' })).toBeNull();

        cleanup();
        AturHalaman([IzinPengelola.MitraLihat, IzinPengelola.MitraKelola]);
        Render(<HalamanDaftarMitra Mitra={[mitra]} OpsiJenis={opsiJenis} />);
        fireEvent.click(screen.getByRole('button', { name: 'Tambah mitra' }));
        expect(screen.getByRole('dialog', { name: 'Tambah mitra' })).toBeTruthy();
    });

    it('rincian: tautan pendaftaran & rekening tersamar; Keuangan bisa mencatat pencairan tetapi tidak mengubah mitra', () => {
        AturHalaman([IzinPengelola.MitraLihat, IzinPengelola.MitraPencairan]);
        Render(
            <HalamanTampilMitra
                OpsiJenis={opsiJenis}
                Mitra={{
                    Uuid: '01JMITRA00000000000000001',
                    Kode: 'AGEN-SOLO',
                    Nama: 'CV Agen Kasir Solo',
                    Jenis: 'Reseller',
                    LabelJenis: 'Reseller/agen daerah',
                    Status: 'Aktif',
                    PersenKomisi: '20.00',
                    KomisiBerulang: true,
                    NamaBank: 'Bank Rakyat Indonesia',
                    RekeningTersamar: '•••• 3567',
                    NamaPemilikRekening: 'CV Agen Kasir Solo',
                    TautanPendaftaran: 'https://dashboard.payoung.id/daftar?mitra=AGEN-SOLO',
                    Tenant: [],
                    Komisi: [
                        {
                            Uuid: '01JKOMISI0000000000000001',
                            NomorTagihan: 'INV/2026/000123',
                            NamaTenant: 'Kopi Nusantara',
                            DasarKomisi: '199000.00',
                            PersenKomisi: '20.00',
                            Jumlah: '39800.00',
                            Status: 'Tertunda',
                            AlasanBatal: null,
                            DibuatPada: '2026-09-23T03:00:00Z',
                        },
                    ],
                    Pencairan: [],
                }}
            />,
        );
        expect(screen.getByText('https://dashboard.payoung.id/daftar?mitra=AGEN-SOLO')).toBeTruthy();
        expect(screen.getByText(/•••• 3567/)).toBeTruthy();
        expect(screen.getByText(/tertunda Rp 39\.800/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Ubah mitra' })).toBeNull();

        fireEvent.click(screen.getByRole('button', { name: 'Catat pencairan' }));
        fireEvent.change(screen.getByRole('textbox', { name: /Potongan pajak \(Rp\)/ }), { target: { value: '1000' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan pencairan' }));
        expect(uji.kiriman.at(-1)).toMatchObject({
            metode: 'post',
            url: '/mitra/01JMITRA00000000000000001/pencairan',
            data: { PotonganPajak: '1000' },
        });
    });
});
