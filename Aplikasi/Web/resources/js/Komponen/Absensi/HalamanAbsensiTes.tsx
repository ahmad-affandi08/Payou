import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanAbsensi, { type PropsAbsensi } from '@/Halaman/Publik/Absensi';
import { AturHalamanUji, RenderUji } from '@/Komponen/Katalog/TiruanInertia';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

/*
 * F-18 bagian 4 (D-37): halaman absensi web. Belum ada wajah → daftar wajah (tombol aktif setelah persetujuan PDP);
 * menunggu persetujuan → info tanpa tombol absen; disetujui → satu tombol besar Absen masuk/keluar; offline → banner.
 */
const dasar: PropsAbsensi = {
    NamaToko: 'Kedai Kopi Senja',
    NamaKaryawan: 'Rina Wulandari',
    AlamatDasar: 'https://dashboard.payou.id/kopi-senja/absen/' + 'a'.repeat(40),
    AlamatModelWajah: 'https://dashboard.payou.id/model-wajah',
    Wajah: null,
    AbsensiTerbuka: null,
    Riwayat: [],
    JumlahFotoDaftar: 3,
    WajibQr: false,
};

beforeEach(() => {
    AturHalamanUji({}, '/kopi-senja/absen/' + 'a'.repeat(40));
    window.innerWidth = 360;
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('Halaman absensi web', () => {
    it('belum ada wajah: tombol rekam wajah aktif hanya setelah persetujuan pemrosesan data wajah', () => {
        RenderUji(<HalamanAbsensi {...dasar} />);

        const tombol = screen.getByRole('button', { name: /Mulai rekam wajah/ });
        expect((tombol as HTMLButtonElement).disabled).toBe(true);
        fireEvent.click(screen.getByRole('checkbox'));
        expect((tombol as HTMLButtonElement).disabled).toBe(false);
        expect(screen.queryByRole('button', { name: /Absen masuk/ })).toBeNull();
    });

    it('wajah ditolak: alasan pengelola tampil dan bisa daftar ulang', () => {
        RenderUji(
            <HalamanAbsensi {...dasar} Wajah={{ Status: 'Ditolak', Label: 'Ditolak', AlasanTolak: 'Foto gelap' }} />,
        );

        expect(screen.getByText(/Alasan pengelola: Foto gelap/)).not.toBeNull();
        expect(screen.getByRole('button', { name: /Mulai rekam wajah/ })).not.toBeNull();
    });

    it('outlet wajib QR: tombol absen aktif setelah 6 angka kode QR terisi (selain angka dibuang)', () => {
        RenderUji(
            <HalamanAbsensi
                {...dasar}
                WajibQr
                Wajah={{ Status: 'Disetujui', Label: 'Disetujui', AlasanTolak: null }}
            />,
        );

        const tombol = screen.getByRole('button', { name: 'Absen masuk' }) as HTMLButtonElement;
        expect(tombol.disabled).toBe(true);
        fireEvent.change(screen.getByLabelText(/Kode QR outlet/), { target: { value: '12 34-5' } });
        expect((screen.getByLabelText(/Kode QR outlet/) as HTMLInputElement).value).toBe('12345');
        expect(tombol.disabled).toBe(true);
        fireEvent.change(screen.getByLabelText(/Kode QR outlet/), { target: { value: '123456' } });
        expect(tombol.disabled).toBe(false);
        // Tanpa BarcodeDetector (jsdom, Safari iOS) hanya isian kode yang tampil.
        expect(screen.queryByRole('button', { name: 'Pindai QR outlet' })).toBeNull();
    });

    it('menunggu persetujuan: tanpa tombol absen', () => {
        RenderUji(
            <HalamanAbsensi
                {...dasar}
                Wajah={{ Status: 'Menunggu', Label: 'Menunggu persetujuan', AlasanTolak: null }}
            />,
        );

        expect(screen.getByText(/menunggu persetujuan pengelola/)).not.toBeNull();
        expect(screen.queryByRole('button', { name: /Absen/ })).toBeNull();
    });

    it('wajah disetujui: tombol Absen masuk, atau Absen keluar bila masih ada absensi terbuka; riwayat tampil', () => {
        const disetujui = { Status: 'Disetujui' as const, Label: 'Disetujui', AlasanTolak: null };
        RenderUji(<HalamanAbsensi {...dasar} Wajah={disetujui} />);
        expect(screen.getByRole('button', { name: 'Absen masuk' })).not.toBeNull();
        cleanup();

        RenderUji(
            <HalamanAbsensi
                {...dasar}
                Wajah={disetujui}
                AbsensiTerbuka={{
                    Uuid: '01K5ABSEN0000000000000000A',
                    MasukPada: '2026-10-05T01:00:00Z',
                    NamaOutlet: 'Outlet Solo',
                }}
                Riwayat={[{ MasukPada: '2026-10-05T01:00:00Z', KeluarPada: null, NamaOutlet: 'Outlet Solo' }]}
            />,
        );
        expect(screen.getByRole('button', { name: 'Absen keluar' })).not.toBeNull();
        expect(screen.getByText('Belum absen keluar')).not.toBeNull();
    });

    it('tanpa koneksi: banner offline dan tombol absen nonaktif', () => {
        vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
        RenderUji(<HalamanAbsensi {...dasar} Wajah={{ Status: 'Disetujui', Label: 'Disetujui', AlasanTolak: null }} />);

        expect(screen.getByText(/Tidak ada koneksi internet/)).not.toBeNull();
        expect((screen.getByRole('button', { name: 'Absen masuk' }) as HTMLButtonElement).disabled).toBe(true);
    });

    it('sedang bekerja: status, lama bekerja berjalan, jam masuk WIB, dan riwayat berdurasi', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-05T04:00:00Z'));
        const disetujui = { Status: 'Disetujui' as const, Label: 'Disetujui', AlasanTolak: null };
        RenderUji(
            <HalamanAbsensi
                {...dasar}
                Wajah={disetujui}
                AbsensiTerbuka={{
                    Uuid: '01K5ABSEN0000000000000000A',
                    MasukPada: '2026-10-05T01:00:00Z',
                    NamaOutlet: 'Outlet Solo',
                }}
                Riwayat={[
                    { MasukPada: '2026-10-04T01:00:00Z', KeluarPada: '2026-10-04T09:30:00Z', NamaOutlet: 'Outlet Solo' },
                ]}
            />,
        );

        expect(screen.getByText('Sedang bekerja')).not.toBeNull();
        expect(screen.getByText('Masuk pukul 08.00 di Outlet Solo')).not.toBeNull();
        expect(screen.getAllByText('3 jam').length).toBeGreaterThan(0);
        expect(screen.getByText('Selamat siang,')).not.toBeNull();
        expect(screen.getByText('8 jam 30 menit')).not.toBeNull();
        expect(screen.getByText('Selesai')).not.toBeNull();
        vi.useRealTimers();
    });

    it('belum absen: status netral dan tiga syarat absen tampil', () => {
        RenderUji(<HalamanAbsensi {...dasar} Wajah={{ Status: 'Disetujui', Label: 'Disetujui', AlasanTolak: null }} />);

        expect(screen.getByText('Belum absen masuk')).not.toBeNull();
        expect(screen.getByText('Internet siap')).not.toBeNull();
        expect(screen.getByText('Dalam radius outlet')).not.toBeNull();
        expect(screen.getByText('Wajah dicek')).not.toBeNull();
    });

    it('daftar wajah: langkah pendaftaran maju ke "Rekam wajah" setelah persetujuan', () => {
        RenderUji(<HalamanAbsensi {...dasar} />);

        const langkah = screen.getByRole('list', { name: 'Langkah pendaftaran' });
        expect(langkah.querySelector('[aria-current="step"]')?.textContent).toContain('Setujui penggunaan data');
        fireEvent.click(screen.getByRole('checkbox'));
        expect(langkah.querySelector('[aria-current="step"]')?.textContent).toContain('Rekam wajah');
    });
});
