import { Link } from '@inertiajs/react';
import { useState } from 'react';

import DialogKendaraan, { AlamatKendaraan } from '@/Komponen/Bengkel/DialogKendaraan';
import { AlamatPerintahKerja } from '@/Komponen/Bengkel/KolomPerintahKerja';
import Tombol from '@/Komponen/Formulir/Tombol';
import AksiHalaman from '@/Komponen/Kelola/AksiHalaman';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { Kendaraan, PropsDaftarKendaraan } from '@/Tipe/Bengkel';

const kolom: KolomTabel<Kendaraan>[] = [
    {
        id: 'NomorPolisi',
        accessorKey: 'NomorPolisi',
        header: 'Nomor polisi',
        meta: { label: 'Nomor polisi', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: k } }) => (
            <Link href={`${AlamatKendaraan}/${k.Uuid}`} className="font-mono font-semibold text-brand underline">
                {k.NomorPolisi}
            </Link>
        ),
    },
    {
        id: 'Merek',
        accessorKey: 'Merek',
        header: 'Kendaraan',
        meta: { label: 'Kendaraan', prioritas: 'penting' },
        cell: ({ row: { original: k } }) => (
            <span className="flex flex-col gap-0.5 break-words">
                <span>{k.Label}</span>
                {k.Warna ? <span className="text-keterangan text-teks-sekunder">{k.Warna}</span> : null}
            </span>
        ),
    },
    {
        id: 'Pelanggan',
        header: 'Pemilik',
        enableSorting: false,
        meta: { label: 'Pemilik', prioritas: 'penting' },
        cell: ({ row: { original: k } }) =>
            k.Pelanggan.Uuid ? (
                <Link href={`/kelola/pelanggan/${k.Pelanggan.Uuid}`} className="text-brand underline">
                    {k.Pelanggan.Nama}
                </Link>
            ) : (
                k.Pelanggan.Nama
            ),
    },
    {
        id: 'KmTerakhir',
        accessorKey: 'KmTerakhir',
        header: 'KM terakhir',
        meta: { label: 'KM terakhir', angka: true, prioritas: 'rendah' },
        cell: ({ row: { original: k } }) => (k.KmTerakhir === null ? '-' : k.KmTerakhir.toLocaleString('id-ID')),
    },
    {
        id: 'DiubahPada',
        accessorKey: 'DiubahPada',
        header: 'Servis terakhir',
        meta: { label: 'Servis terakhir', prioritas: 'rendah' },
        cell: ({ row: { original: k } }) =>
            k.ServisTerakhirPada === null
                ? 'Belum pernah'
                : `${FormatTanggal(k.ServisTerakhirPada.slice(0, 10))} | ${String(k.JumlahServis)}×`,
    },
    {
        id: 'Aktif',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'rendah' },
        cell: ({ row: { original: k } }) => (
            <LabelStatus jenis={k.Aktif ? 'sukses' : 'netral'} teks={k.Aktif ? 'Aktif' : 'Diarsipkan'} />
        ),
    },
];

const saring: DefinisiSaring[] = [
    {
        id: 'Aktif',
        label: 'Status',
        jenis: 'pilihan',
        opsi: [
            { nilai: 'Ya', label: 'Aktif' },
            { nilai: 'Tidak', label: 'Diarsipkan' },
        ],
    },
];

/**
 * Kendaraan pelanggan bengkel (§9.10): plat, merek/tipe, KM terakhir, dan riwayat servisnya. Kendaraan yang berganti
 * pemilik diarsipkan; riwayat servis tetap tersimpan.
 */
export default function HalamanDaftarKendaraan({ Kendaraan: data }: PropsDaftarKendaraan) {
    const [dialog, AturDialog] = useState<Kendaraan | 'baru' | null>(null);

    return (
        <TataLetakAplikasi judul="Kendaraan pelanggan" jejak={[{ label: 'Perintah kerja', href: AlamatPerintahKerja }]}>
            <AksiHalaman keterangan="Kendaraan terhubung ke pelanggan. Nomor polisi unik di antara kendaraan aktif.">
                <Tombol onClick={() => AturDialog('baru')}>Tambah kendaraan</Tombol>
            </AksiHalaman>
            <TabelData
                id="bengkel-kendaraan"
                label="Daftar kendaraan pelanggan"
                kolom={kolom}
                sumber={{ mode: 'server', alamat: AlamatKendaraan, awal: data }}
                ambilIdBaris={(k) => k.Uuid}
                urutBawaan="NomorPolisi"
                cari="Cari nomor polisi, merek, atau tipe"
                saring={saring}
                alamatDetail={(k) => `${AlamatKendaraan}/${k.Uuid}`}
                aksiBaris={(k: Kendaraan) => (
                    <>
                        <DropdownMenuItem onSelect={() => AturDialog(k)}>Ubah kendaraan</DropdownMenuItem>
                        {k.Aktif ? (
                            <DropdownMenuItem asChild>
                                <Link href={`${AlamatPerintahKerja}/buat?kendaraan=${k.Uuid}`}>
                                    Buat perintah kerja
                                </Link>
                            </DropdownMenuItem>
                        ) : null}
                    </>
                )}
                kosong={{ ilustrasi: true, judul: 'Belum ada kendaraan pelanggan.' }}
            />
            {dialog !== null ? (
                <DialogKendaraan
                    kendaraan={dialog === 'baru' ? null : dialog}
                    pelanggan={null}
                    saatTutup={() => AturDialog(null)}
                />
            ) : null}
        </TataLetakAplikasi>
    );
}
