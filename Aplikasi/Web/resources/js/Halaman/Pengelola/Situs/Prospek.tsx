import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabSitus from '@/Komponen/Pengelola/Situs/TabSitus';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { HasilTabel, KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import type { Pilihan, PropsBersamaPengelola } from '@/Tipe/Pengelola';

export type BarisProspek = {
    Uuid: string;
    Jenis: 'Kontak' | 'Demo';
    LabelJenis: string;
    Nama: string;
    NamaUsaha: string | null;
    JenisUsaha: string | null;
    Kota: string | null;
    NoHp: string;
    Email: string | null;
    Pesan: string | null;
    HalamanAsal: string | null;
    Status: 'Baru' | 'Dihubungi' | 'Selesai' | 'Spam';
    LabelStatus: string;
    Catatan: string | null;
    Penangan: string | null;
    DitanganiPada: string | null;
    DibuatPada: string | null;
};

type PropsProspek = {
    Prospek: HasilTabel<BarisProspek>;
    PilihanStatus: Pilihan[];
    PilihanJenis: Pilihan[];
    Izin: { Kelola: boolean };
};

const jenisLabelStatus = { Baru: 'peringatan', Dihubungi: 'netral', Selesai: 'sukses', Spam: 'bahaya' } as const;

/** Tautan wa.me hanya untuk nomor utuh (pemegang `situs.kelola`); nomor tersamar ditampilkan sebagai teks. */
function TautanWhatsApp(noHp: string): string | null {
    return /^62[0-9]{8,15}$/.test(noHp) ? `https://wa.me/${noHp}` : null;
}

const kolom: KolomTabel<BarisProspek>[] = [
    {
        id: 'DibuatPada',
        accessorKey: 'DibuatPada',
        header: 'Masuk',
        meta: { label: 'Masuk', prioritas: 'penting', kelasSel: 'text-teks-sekunder whitespace-nowrap tabular-nums' },
        cell: ({ row }) => (row.original.DibuatPada ? FormatTanggalWaktu(row.original.DibuatPada) : '-'),
    },
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Nama & usaha',
        meta: { label: 'Nama & usaha', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: p } }) => (
            <span className="flex flex-col gap-0.5 break-words">
                <span className="text-teks-utama">{p.Nama}</span>
                <span className="text-keterangan text-teks-sekunder">
                    {[p.NamaUsaha, p.JenisUsaha, p.Kota].filter(Boolean).join(' | ') || '-'}
                </span>
            </span>
        ),
    },
    {
        id: 'Kontak',
        header: 'Kontak',
        enableSorting: false,
        meta: { label: 'Kontak', prioritas: 'penting' },
        cell: ({ row: { original: p } }) => {
            const wa = TautanWhatsApp(p.NoHp);

            return (
                <span className="flex flex-col gap-0.5 break-all">
                    {wa ? (
                        <a href={wa} target="_blank" rel="noopener noreferrer" className="text-brand underline">
                            {p.NoHp}
                        </a>
                    ) : (
                        <span className="font-mono">{p.NoHp}</span>
                    )}
                    {p.Email ? <span className="text-keterangan text-teks-sekunder">{p.Email}</span> : null}
                </span>
            );
        },
    },
    {
        id: 'Jenis',
        header: 'Jenis',
        enableSorting: false,
        meta: { label: 'Jenis', prioritas: 'rendah' },
        cell: ({ row }) => row.original.LabelJenis,
    },
    {
        id: 'Pesan',
        header: 'Pesan',
        enableSorting: false,
        meta: { label: 'Pesan', prioritas: 'rendah' },
        cell: ({ row: { original: p } }) => (
            <span className="flex flex-col gap-0.5 break-words">
                <span>{p.Pesan ?? '-'}</span>
                {p.HalamanAsal ? (
                    <span className="text-keterangan text-teks-sekunder">Dari halaman {p.HalamanAsal}</span>
                ) : null}
            </span>
        ),
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row: { original: p } }) => (
            <span className="flex flex-col gap-0.5">
                <LabelStatus jenis={jenisLabelStatus[p.Status]} teks={p.LabelStatus} />
                {p.Penangan ? <span className="text-keterangan text-teks-sekunder">oleh {p.Penangan}</span> : null}
                {p.Catatan ? <span className="text-keterangan break-words text-teks-sekunder">{p.Catatan}</span> : null}
            </span>
        ),
    },
];

/**
 * Prospek dari formulir kontak/minta demo situs pemasaran (bagian B). Bawaan menyembunyikan Spam. Nomor & email utuh
 * hanya untuk pemegang izin kelola situs.
 */
export default function HalamanProspekSitus({ Prospek, PilihanStatus, PilihanJenis, Izin }: PropsProspek) {
    const [ubah, AturUbah] = useState<BarisProspek | null>(null);

    return (
        <TataLetakPengelola judul="Situs pemasaran">
            <TabSitus />
            {ubah ? (
                <FormUbahProspek prospek={ubah} pilihanStatus={PilihanStatus} saatTutup={() => AturUbah(null)} />
            ) : null}
            <TabelData
                id="pengelola-situs-prospek"
                label="Prospek situs"
                kolom={kolom}
                sumber={{ mode: 'server', alamat: '/situs/prospek', awal: Prospek }}
                ambilIdBaris={(p) => p.Uuid}
                cari="Cari nama, usaha, kota, atau nomor HP"
                saring={[
                    {
                        id: 'Status',
                        label: 'Status',
                        jenis: 'pilihanBanyak',
                        opsi: PilihanStatus.map((o) => ({ nilai: o.Nilai, label: o.Label })),
                    },
                    {
                        id: 'Jenis',
                        label: 'Jenis',
                        jenis: 'pilihanBanyak',
                        opsi: PilihanJenis.map((o) => ({ nilai: o.Nilai, label: o.Label })),
                    },
                ]}
                {...(Izin.Kelola
                    ? {
                          aksiBaris: (p: BarisProspek) => (
                              <DropdownMenuItem onSelect={() => AturUbah(p)}>Ubah status & catatan</DropdownMenuItem>
                          ),
                      }
                    : {})}
                kosong={{
                    ilustrasi: true,
                    judul: 'Belum ada prospek. Tambahkan blok "Formulir prospek" di halaman Kontak agar pengunjung bisa mengirim pesan.',
                }}
            />
        </TataLetakPengelola>
    );
}

function FormUbahProspek({
    prospek,
    pilihanStatus,
    saatTutup,
}: {
    prospek: BarisProspek;
    pilihanStatus: Pilihan[];
    saatTutup: () => void;
}) {
    const [status, AturStatus] = useState<string>(prospek.Status);
    const [catatan, AturCatatan] = useState(prospek.Catatan ?? '');
    const [memproses, AturMemproses] = useState(false);
    const { props } = usePage<PropsBersamaPengelola>();

    return (
        <DialogFormulir judul={`Prospek ${prospek.Nama}`} saatTutup={saatTutup}>
            <form
                onSubmit={(p) => {
                    p.preventDefault();
                    router.put(
                        `/situs/prospek/${prospek.Uuid}`,
                        { Status: status, Catatan: catatan },
                        {
                            preserveScroll: true,
                            onStart: () => AturMemproses(true),
                            onFinish: () => AturMemproses(false),
                            onSuccess: saatTutup,
                        },
                    );
                }}
                className="flex flex-col gap-4"
                noValidate
            >
                <BidangPilihan
                    label="Status"
                    nilai={status}
                    opsi={pilihanStatus}
                    saatBerubah={AturStatus}
                    galat={props.errors.Status}
                    required
                />
                <BidangTeksPanjang
                    label="Catatan tindak lanjut"
                    keterangan="Misalnya hasil telepon, jadwal demo, atau paket yang diminati."
                    nilai={catatan}
                    saatBerubah={AturCatatan}
                    galat={props.errors.Catatan}
                    maksimal={1000}
                />
                <div className="flex justify-end gap-2">
                    <Tombol varian="sekunder" onClick={saatTutup}>
                        Batal
                    </Tombol>
                    <Tombol type="submit" memproses={memproses}>
                        Simpan prospek
                    </Tombol>
                </div>
            </form>
        </DialogFormulir>
    );
}
