import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanAbsensi, { FormatDurasiMenit } from '@/Halaman/Kelola/Karyawan/Absensi';
import HalamanBuatKaryawan from '@/Halaman/Kelola/Karyawan/Buat';
import HalamanBuatAturanKomisi from '@/Halaman/Kelola/Karyawan/BuatAturanKomisi';
import HalamanDaftarKaryawan from '@/Halaman/Kelola/Karyawan/Daftar';
import HalamanAturanKomisi, { FormatNilaiKomisi } from '@/Halaman/Kelola/Karyawan/Komisi';
import HalamanLaporanKomisi from '@/Halaman/Kelola/Karyawan/LaporanKomisi';
import HalamanJadwalKerja, {
    CekJam,
    GeserTanggal,
    RapikanJam,
    SusunSelBerubah,
} from '@/Halaman/Kelola/Karyawan/Jadwal';
import { AturHalamanUji, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import { BuatHasilTabel } from '@/Komponen/Persediaan/DataUjiPersediaan';
import type { BarisAbsensi, BarisJadwal, BarisKaryawan } from '@/Tipe/Karyawan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const Outlet = { Uuid: '01J9OTL0000000000000000001', Nama: 'Kopi Senja Solo Baru' };
const Hari = ['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26', '2026-09-27'];

function BarisJadwalUji(): BarisJadwal {
    return {
        Uuid: '01J9KRY0000000000000000001',
        Nama: 'Dimas Pratama Wicaksono Adiwijaya',
        Jabatan: 'Barista',
        Aktif: true,
        Jadwal: {
            ...Object.fromEntries(Hari.map((t) => [t, null])),
            '2026-09-21': { JamMulai: '08:00', JamSelesai: '16:00', OutletLain: false },
            '2026-09-22': { JamMulai: '09:00', JamSelesai: '17:00', OutletLain: true },
        },
    };
}

describe('Halaman karyawan (F-18)', () => {
    beforeEach(() => {
        AturHalamanUji({}, '/kelola/karyawan');
        window.history.replaceState({}, '', '/kelola/karyawan');
        vi.clearAllMocks();
    });
    afterEach(() => cleanup());

    it('bantuan jadwal: geser tanggal, cek & rapikan jam, sel berubah (outlet lain dilewati), durasi', () => {
        expect(GeserTanggal('2026-09-28', -7)).toBe('2026-09-21');
        expect(GeserTanggal('2026-12-28', 7)).toBe('2027-01-04');
        expect([CekJam('08:00'), CekJam('24:00'), CekJam('8:00')]).toEqual([true, false, false]);
        expect([RapikanJam('800'), RapikanJam('0830'), RapikanJam('08:30')]).toEqual(['08:00', '08:30', '08:30']);
        const b = BarisJadwalUji();
        expect(
            SusunSelBerubah([b], {
                [b.Uuid]: {
                    '2026-09-21': { JamMulai: '', JamSelesai: '' },
                    '2026-09-22': { JamMulai: '10:00', JamSelesai: '18:00' },
                    '2026-09-23': { JamMulai: '22:00', JamSelesai: '06:00' },
                },
            }),
        ).toEqual([
            { UuidKaryawan: b.Uuid, Tanggal: '2026-09-21', JamMulai: null, JamSelesai: null },
            { UuidKaryawan: b.Uuid, Tanggal: '2026-09-23', JamMulai: '22:00', JamSelesai: '06:00' },
        ]);
        expect([FormatDurasiMenit(445), FormatDurasiMenit(45), FormatDurasiMenit(null)]).toEqual([
            '7 j 25 m',
            '45 m',
            '—',
        ]);
    });

    it('jadwal: ubah jam lalu simpan mengirim sel yang berubah; sel outlet lain terkunci', () => {
        window.history.replaceState({}, '', '/kelola/karyawan/jadwal');
        RenderUji(
            <HalamanJadwalKerja
                OpsiOutlet={[Outlet]}
                UuidOutlet={Outlet.Uuid}
                Senin="2026-09-21"
                Jadwal={{ Hari, Baris: [BarisJadwalUji()] }}
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getByText('Di outlet lain 09:00–17:00')).toBeTruthy();
        const simpan = screen.getByRole('button', { name: 'Simpan jadwal' });
        expect((simpan as HTMLButtonElement).disabled).toBe(true);
        fireEvent.change(screen.getByLabelText('Dimas Pratama Wicaksono Adiwijaya Rabu mulai'), {
            target: { value: '07:30' },
        });
        expect((simpan as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByText(/isi keduanya atau kosongkan keduanya/)).toBeTruthy();
        fireEvent.change(screen.getByLabelText('Dimas Pratama Wicaksono Adiwijaya Rabu selesai'), {
            target: { value: '15:30' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan jadwal' }));
        expect(tiruanRouter.put).toHaveBeenCalledWith(
            '/kelola/karyawan/jadwal',
            {
                UuidOutlet: Outlet.Uuid,
                Senin: '2026-09-21',
                Sel: [
                    {
                        UuidKaryawan: '01J9KRY0000000000000000001',
                        Tanggal: '2026-09-23',
                        JamMulai: '07:30',
                        JamSelesai: '15:30',
                    },
                ],
            },
            expect.anything(),
        );
    });

    it('daftar: tombol tambah & kolom gaji hanya untuk karyawan.kelola; absensi menampilkan terlambat', () => {
        const baris: BarisKaryawan = {
            Uuid: '01J9KRY0000000000000000001',
            Nama: 'Rina Wulandari',
            Jabatan: 'Barista',
            LevelStaf: 'Senior',
            GajiPokok: '3500000.00',
            TarifLemburPerJam: null,
            PotonganTerlambatPerMenit: null,
            PotonganTidakMasukPerHari: null,
            UuidPengguna: null,
            NamaPengguna: null,
            UuidOutlet: null,
            NamaOutlet: null,
            Status: 'Aktif',
            LabelStatus: 'Aktif',
            TautanAbsen: false,
            StatusWajah: null,
        };
        RenderUji(
            <HalamanDaftarKaryawan
                Karyawan={BuatHasilTabel([baris])}
                OpsiPengguna={[]}
                OpsiOutlet={[Outlet]}
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getAllByRole('link', { name: 'Tambah karyawan' })[0]?.getAttribute('href')).toBe(
            '/kelola/karyawan/buat',
        );
        expect(screen.getAllByText('Rp 3.500.000').length).toBeGreaterThan(0);
        // D-46: karyawan tanpa akun diberi tahu, bukan dibiarkan kosong.
        expect(screen.getAllByText('Belum punya akun (absen lewat HP)').length).toBeGreaterThan(0);
        cleanup();

        RenderUji(
            <HalamanDaftarKaryawan
                Karyawan={BuatHasilTabel([{ ...baris, GajiPokok: null }])}
                OpsiPengguna={[]}
                OpsiOutlet={[Outlet]}
                Izin={{ Kelola: false }}
            />,
        );
        expect(screen.queryByRole('link', { name: 'Tambah karyawan' })).toBeNull();
        cleanup();

        window.history.replaceState({}, '', '/kelola/karyawan/absensi');
        const absensi: BarisAbsensi = {
            Uuid: '01J9ABS0000000000000000001',
            TanggalBisnis: '2026-09-25',
            UuidKaryawan: baris.Uuid,
            NamaKaryawan: baris.Nama,
            NamaOutlet: Outlet.Nama,
            JamMasuk: '09:40',
            JamKeluar: '17:05',
            KeluarBeda: false,
            DurasiMenit: 445,
            Jadwal: '09:00–17:00',
            TerlambatMenit: 40,
            PulangCepatMenit: 0,
            LemburMenit: 0,
            DiluarJadwal: false,
            Status: 'Terlambat',
            LabelStatus: 'Terlambat',
            AdaSwafotoMasuk: true,
            AdaSwafotoKeluar: false,
            Sumber: 'Pos',
            Dikoreksi: false,
            AlasanKoreksi: null,
            JarakMasukMeter: null,
            JarakKeluarMeter: null,
            KemiripanWajahMasuk: null,
            KemiripanWajahKeluar: null,
        };
        RenderUji(
            <HalamanAbsensi
                Absensi={BuatHasilTabel([absensi])}
                OpsiKaryawan={[]}
                OpsiOutlet={[Outlet]}
                BolehKoreksi={false}
            />,
        );
        expect(screen.queryByRole('button', { name: 'Catat absensi terlewat' })).toBeNull();
        expect(screen.getAllByText('Terlambat 40 menit').length).toBeGreaterThan(0);
        expect(screen.getAllByRole('link', { name: 'Swafoto masuk' })[0]?.getAttribute('href')).toBe(
            '/kelola/karyawan/absensi/01J9ABS0000000000000000001/swafoto/masuk',
        );
    });

    it('komisi: format nilai, tombol tambah hanya untuk karyawan.kelola; laporan menampilkan total bersih', () => {
        expect(FormatNilaiKomisi('Persen', '12.50')).toBe('12,50%');
        expect(FormatNilaiKomisi('Persen', '10.00')).toBe('10%');
        expect(FormatNilaiKomisi('Tetap', '5000.00')).toBe('Rp 5.000 per jumlah');
        window.history.replaceState({}, '', '/kelola/karyawan/komisi');
        const aturan = {
            Uuid: '01J9ATR0000000000000000001',
            Nama: 'Senior potong rambut',
            Cakupan: 'Produk' as const,
            LabelCakupan: 'Produk',
            UuidProduk: '01J9PRD0000000000000000001',
            UuidKategori: null,
            NamaSasaran: 'Potong Rambut Wanita Panjang',
            LevelStaf: 'Senior',
            Jenis: 'Persen' as const,
            Nilai: '20.00',
            Status: 'Aktif' as const,
        };
        RenderUji(<HalamanAturanKomisi Aturan={[aturan]} OpsiKategori={[]} Izin={{ Kelola: true }} />);
        expect(screen.getAllByRole('link', { name: 'Tambah aturan komisi' })[0]?.getAttribute('href')).toBe(
            '/kelola/karyawan/komisi/buat',
        );
        expect(screen.getAllByText('20%').length).toBeGreaterThan(0);
        cleanup();
        RenderUji(<HalamanAturanKomisi Aturan={[aturan]} OpsiKategori={[]} Izin={{ Kelola: false }} />);
        expect(screen.queryByRole('link', { name: 'Tambah aturan komisi' })).toBeNull();
        cleanup();

        window.history.replaceState({}, '', '/kelola/karyawan/komisi/laporan');
        RenderUji(
            <HalamanLaporanKomisi
                Komisi={{ ...BuatHasilTabel([]), Ringkasan: { Bersih: '1250000.00' } }}
                OpsiOutlet={[Outlet]}
            />,
        );
        expect(screen.getByText('Rp 1.250.000')).toBeTruthy();
    });

    it('halaman tambah karyawan: kirim POST ke /kelola/karyawan (isian kosong = null); Batal kembali ke daftar', () => {
        window.history.replaceState({}, '', '/kelola/karyawan/buat');
        RenderUji(<HalamanBuatKaryawan OpsiPengguna={[]} OpsiOutlet={[Outlet]} />);
        fireEvent.change(screen.getByLabelText('Nama karyawan'), { target: { value: 'Rina Wulandari' } });
        fireEvent.change(screen.getByLabelText('Jabatan (opsional)'), { target: { value: 'Barista' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan karyawan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/karyawan',
            {
                Nama: 'Rina Wulandari',
                Jabatan: 'Barista',
                LevelStaf: null,
                GajiPokok: null,
                TarifLemburPerJam: null,
                PotonganTerlambatPerMenit: null,
                PotonganTidakMasukPerHari: null,
                UuidPengguna: null,
                UuidOutlet: null,
            },
            expect.anything(),
        );

        fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
        expect(tiruanRouter.visit).toHaveBeenCalledWith('/kelola/karyawan');
    });

    it('halaman tambah aturan komisi: kirim POST ke /kelola/karyawan/komisi; Batal kembali ke daftar aturan', () => {
        window.history.replaceState({}, '', '/kelola/karyawan/komisi/buat');
        RenderUji(<HalamanBuatAturanKomisi OpsiKategori={[]} />);
        fireEvent.change(screen.getByLabelText('Nama aturan'), { target: { value: 'Umum 10%' } });
        fireEvent.change(screen.getByLabelText('Persen komisi'), { target: { value: '10,5' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan aturan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/karyawan/komisi',
            {
                Nama: 'Umum 10%',
                Cakupan: 'Semua',
                UuidProduk: null,
                UuidKategori: null,
                LevelStaf: null,
                Jenis: 'Persen',
                Nilai: '10.5',
            },
            expect.anything(),
        );

        fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
        expect(tiruanRouter.visit).toHaveBeenCalledWith('/kelola/karyawan/komisi');
    });
});
