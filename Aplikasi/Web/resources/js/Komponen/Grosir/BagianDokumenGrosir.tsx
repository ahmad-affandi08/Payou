import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { Card } from '@/Komponen/Ui/card';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type {
    IzinGrosir,
    JurnalGrosir,
    Opsi,
    RiwayatGrosir,
    StatusDokumenGrosir,
    StatusPesananGrosir,
} from '@/Tipe/Grosir';

/*
 * Bagian bersama halaman dokumen grosir (F-12, §9.7): kolom identitas, saring, kerangka halaman, kartu keterangan,
 * ringkasan nilai, riwayat status, jurnal, dan dialog alasan. Semua daftar memakai `TabelData` mode server (aturan #21)
 * dan responsif 360px ke atas (D-16).
 *
 * Kembaran `Komponen/Pembelian/BagianDokumenPembelian`. Sengaja tidak diangkat ke satu tempat bersama dalam tugas ini,
 * karena itu berarti menyentuh belasan halaman pembelian yang sudah berjalan tanpa alasan dari tugas grosir; kalau
 * modul ketiga membutuhkan bentuk yang sama, ketiganya disatukan sekalian.
 */

/** Akar rute back-office grosir. */
export const AlamatGrosir = '/kelola/grosir';

type BarisDasarGrosir = {
    Uuid: string;
    Nomor: string;
    Tanggal: string;
    LabelStatus: string;
};

/** Jenis label status: selesai/diposting = sukses, sedang berjalan = peringatan, batal = bahaya, draf = netral. */
export function AmbilJenisStatusGrosir(
    status: StatusPesananGrosir | StatusDokumenGrosir,
): 'sukses' | 'peringatan' | 'bahaya' | 'netral' {
    switch (status) {
        case 'Selesai':
        case 'Diposting':
            return 'sukses';
        case 'Dikonfirmasi':
        case 'SebagianDikirim':
            return 'peringatan';
        case 'Dibatalkan':
            return 'bahaya';
        default:
            return 'netral';
    }
}

export function LabelStatusGrosir({
    status,
    label,
}: {
    status: StatusPesananGrosir | StatusDokumenGrosir;
    label: string;
}) {
    return <LabelStatus jenis={AmbilJenisStatusGrosir(status)} teks={label} />;
}

export function KolomNomorGrosir<T extends BarisDasarGrosir>(alamat: string, label = 'Nomor'): KolomTabel<T> {
    return {
        id: 'Nomor',
        accessorKey: 'Nomor',
        header: label,
        meta: { label, prioritas: 'utama', wajib: true },
        cell: ({ row: { original: d } }) => (
            <Link href={`${alamat}/${d.Uuid}`} className="font-mono font-semibold break-all text-brand underline">
                {d.Nomor}
            </Link>
        ),
    };
}

export function KolomTanggalGrosir<T extends BarisDasarGrosir>(label = 'Tanggal'): KolomTabel<T> {
    return {
        id: 'Tanggal',
        accessorKey: 'Tanggal',
        header: label,
        meta: { label, prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggal(row.original.Tanggal),
    };
}

export function KolomStatusGrosir<
    T extends BarisDasarGrosir & { Status: StatusPesananGrosir | StatusDokumenGrosir },
>(): KolomTabel<T> {
    return {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row }) => <LabelStatusGrosir status={row.original.Status} label={row.original.LabelStatus} />,
    };
}

export function KolomPelangganGrosir<T extends { NamaPelanggan: string }>(): KolomTabel<T> {
    return {
        id: 'Pelanggan',
        header: 'Pelanggan',
        enableSorting: false,
        meta: { label: 'Pelanggan', prioritas: 'penting' },
        cell: ({ row }) => <span className="break-words">{row.original.NamaPelanggan}</span>,
    };
}

export function KolomUangGrosir<T>(
    id: string,
    label: string,
    ambil: (baris: T) => string,
    urut = false,
): KolomTabel<T> {
    return {
        id,
        header: label,
        enableSorting: urut,
        meta: { label, angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatRupiah(ambil(row.original)),
    };
}

/** Saring bawaan daftar dokumen grosir: status & rentang tanggal. */
export function BuatSaringGrosir(opsiStatus: Opsi[], tambahan: DefinisiSaring[] = []): DefinisiSaring[] {
    return [
        {
            id: 'Status',
            label: 'Status',
            jenis: 'pilihanBanyak',
            opsi: opsiStatus.map((o) => ({ nilai: o.Nilai, label: o.Label })),
        },
        { id: 'Tanggal', label: 'Tanggal', jenis: 'rentangTanggal' },
        ...tambahan,
    ];
}

/** Kerangka halaman grosir: judul, keterangan, pesan hanya-lihat, galat server, lalu isinya. */
export function HalamanGrosir({
    judul,
    keterangan,
    izin,
    objek,
    children,
}: {
    judul: string;
    keterangan: string;
    izin: IzinGrosir;
    objek: string;
    children: ReactNode;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();

    return (
        <TataLetakAplikasi judul={judul}>
            <p className="max-w-3xl text-isi text-teks-sekunder">{keterangan}</p>
            {!izin.Kelola ? <PesanHanyaLihat izin="grosir.kelola" objek={objek} /> : null}
            <DaftarGalatServer galat={props.errors} />
            {children}
        </TataLetakAplikasi>
    );
}

/** Satu pasangan keterangan dokumen (dt/dd). */
export function KeteranganGrosir({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex min-w-0 flex-col gap-0.5">
            <dt className="text-label font-semibold text-teks-sekunder">{label}</dt>
            <dd className="text-isi break-words text-teks-utama">{children}</dd>
        </div>
    );
}

/** Kartu keterangan dokumen (grid responsif 1/2/3 kolom). */
export function KartuKeteranganGrosir({ children }: { children: ReactNode }) {
    return (
        <Card className="gap-0 rounded-panel p-4 shadow-none">
            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{children}</dl>
        </Card>
    );
}

/** Ringkasan nilai dokumen (label kiri, Rupiah tabular rata kanan). */
export function RingkasanNilaiGrosir({ baris }: { baris: { label: string; nilai: string; tebal?: boolean }[] }) {
    return (
        <dl className="ml-auto flex w-full max-w-md flex-col gap-1 border-t border-garis pt-3">
            {baris.map((b) => (
                <div key={b.label} className="flex items-baseline justify-between gap-3">
                    <dt className={b.tebal ? 'font-semibold text-teks-utama' : 'text-teks-sekunder'}>{b.label}</dt>
                    <dd
                        className={`text-right tabular-nums ${b.tebal ? 'text-subjudul font-semibold text-teks-utama' : 'text-teks-utama'}`}
                    >
                        {FormatRupiah(b.nilai)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export function RiwayatGrosirDokumen({ riwayat }: { riwayat: RiwayatGrosir[] }) {
    if (riwayat.length === 0) {
        return null;
    }

    return (
        <section aria-label="Riwayat status" className="flex flex-col gap-2">
            <h2 className="text-subjudul font-semibold text-teks-utama">Riwayat status</h2>
            <ol className="flex flex-col gap-1 text-isi">
                {riwayat.map((r, i) => (
                    <li key={`${r.Pada}-${String(i)}`} className="break-words text-teks-sekunder">
                        <span className="font-semibold text-teks-utama">{r.StatusKe}</span> |{' '}
                        {FormatTanggalWaktu(r.Pada)}
                        {r.Oleh ? ` oleh ${r.Oleh}` : ''}
                        {r.Alasan ? ` — ${r.Alasan}` : ''}
                    </li>
                ))}
            </ol>
        </section>
    );
}

/** Jurnal otomatis dokumen; tautan hanya bila pengguna boleh melihat laporan keuangan. */
export function JurnalDokumenGrosir({ jurnal, izin }: { jurnal: JurnalGrosir[]; izin: IzinGrosir }) {
    if (jurnal.length === 0) {
        return null;
    }

    return (
        <section aria-label="Jurnal" className="flex flex-col gap-2">
            <h2 className="text-subjudul font-semibold text-teks-utama">Jurnal</h2>
            <ul className="flex flex-col gap-1 text-isi">
                {jurnal.map((j) => (
                    <li key={j.Uuid} className="break-words text-teks-sekunder">
                        {izin.LihatJurnal ? (
                            <Link
                                href={`/kelola/akuntansi/jurnal/${j.Uuid}`}
                                className="font-mono font-semibold text-brand underline"
                            >
                                {j.Nomor}
                            </Link>
                        ) : (
                            <span className="font-mono font-semibold text-teks-utama">{j.Nomor}</span>
                        )}{' '}
                        | {FormatTanggal(j.Tanggal)} | {j.KunciSumber} | {FormatRupiah(j.TotalDebit)}
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** Dialog alasan untuk pembatalan dokumen grosir (minimal 5 karakter, ditegakkan juga di server). */
export function DialogAlasanGrosir({
    judul,
    keterangan,
    labelAksi,
    alamat,
    saatTutup,
}: {
    judul: string;
    keterangan: ReactNode;
    labelAksi: string;
    alamat: string;
    saatTutup: () => void;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [alasan, AturAlasan] = useState('');
    const [memproses, AturMemproses] = useState(false);

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.post(
            alamat,
            { Alasan: alasan },
            {
                preserveScroll: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
                onSuccess: saatTutup,
            },
        );
    };

    return (
        <DialogFormulir judul={judul} keterangan={keterangan} galatUmum={props.errors.Umum} saatTutup={saatTutup}>
            <form onSubmit={Kirim} noValidate className="flex flex-col gap-4" aria-label={judul}>
                <BidangTeksPanjang
                    label="Alasan"
                    nilai={alasan}
                    saatBerubah={AturAlasan}
                    galat={props.errors.Alasan}
                    maksimal={255}
                    required
                />
                <div className="flex flex-wrap justify-end gap-2">
                    <Tombol type="button" varian="sekunder" onClick={saatTutup}>
                        Batal
                    </Tombol>
                    <Tombol type="submit" varian="bahaya" memproses={memproses} disabled={alasan.trim().length < 5}>
                        {labelAksi}
                    </Tombol>
                </div>
            </form>
        </DialogFormulir>
    );
}
