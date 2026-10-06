import { Head } from '@inertiajs/react';

import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { AmbilJenisStatusKompatibilitas, LabelSambunganPrinter, type BarisKompatibilitas } from '@/Tipe/Kompatibilitas';

const kolom: KolomTabel<BarisKompatibilitas>[] = [
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Model',
        meta: { label: 'Model', prioritas: 'utama', wajib: true, kelasSel: 'text-teks-utama' },
        cell: ({ row: { original: b } }) => (
            <>
                <span className="block">{b.Nama}</span>
                {b.Catatan ? <span className="block text-keterangan text-teks-sekunder">{b.Catatan}</span> : null}
            </>
        ),
    },
    {
        id: 'Sambungan',
        accessorFn: (b) => (b.Sambungan ? (LabelSambunganPrinter[b.Sambungan] ?? b.Sambungan) : '—'),
        header: 'Sambungan',
        meta: { label: 'Sambungan', prioritas: 'rendah' },
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row: { original: b } }) => (
            <LabelStatus jenis={AmbilJenisStatusKompatibilitas(b.Status)} teks={b.LabelStatus} />
        ),
    },
    {
        id: 'JumlahTenant',
        accessorKey: 'JumlahTenant',
        header: 'Dipakai di',
        meta: { label: 'Dipakai di', prioritas: 'rendah', angka: true },
        cell: ({ row: { original: b } }) => (b.JumlahTenant > 0 ? `${String(b.JumlahTenant)} usaha` : '—'),
    },
];

/**
 * Daftar kompatibilitas perangkat & printer Payoung (PRD §17.2.5a HCL, v1.98) untuk calon pengguna: Tersertifikasi (lolos
 * uji lab), Kompatibel (lolos Wizard Uji Perangkat di usaha pengguna), Terbatas (ada kendala; lihat catatan).
 */
export default function HalamanKompatibilitasPerangkatPublik({ Baris }: { Baris: BarisKompatibilitas[] }) {
    const perangkat = Baris.filter((b) => b.Jenis === 'Perangkat');
    const printer = Baris.filter((b) => b.Jenis === 'Printer');

    return (
        <>
            <Head title="Kompatibilitas perangkat" />
            <main className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-10">
                <header className="flex flex-col gap-2">
                    <JudulHalaman>Perangkat &amp; printer yang didukung</JudulHalaman>
                    <p className="max-w-3xl text-isi text-teks-sekunder">
                        Payoung berjalan di Android, iPad/iPhone, dan Windows, dengan printer thermal LAN/Wi-Fi,
                        Bluetooth, USB, atau printer bawaan mesin kasir. Daftar ini disusun dari hasil uji perangkat di
                        usaha pengguna dan uji tim kami. Perangkat yang belum ada di daftar tetap bisa dicoba lewat menu
                        Uji perangkat di aplikasi kasir.
                    </p>
                </header>
                <section className="flex flex-col gap-3">
                    <h2 className="text-subjudul font-semibold text-teks-utama">Perangkat</h2>
                    <TabelData
                        id="publik-kompatibilitas-perangkat"
                        label="Perangkat yang didukung"
                        kolom={kolom.filter((k) => k.id !== 'Sambungan')}
                        sumber={{ mode: 'lokal', data: perangkat }}
                        ambilIdBaris={(b) => b.Uuid}
                        cari="Cari merek atau model"
                        kosong={{ judul: 'Belum ada perangkat yang teruji.' }}
                    />
                </section>
                <section className="flex flex-col gap-3">
                    <h2 className="text-subjudul font-semibold text-teks-utama">Printer struk</h2>
                    <TabelData
                        id="publik-kompatibilitas-printer"
                        label="Printer yang didukung"
                        kolom={kolom}
                        sumber={{ mode: 'lokal', data: printer }}
                        ambilIdBaris={(b) => b.Uuid}
                        cari="Cari printer"
                        kosong={{ judul: 'Belum ada printer yang teruji.' }}
                    />
                </section>
            </main>
        </>
    );
}
