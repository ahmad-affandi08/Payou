import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AksiHalaman from '@/Komponen/Kelola/AksiHalaman';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabSitus from '@/Komponen/Pengelola/Situs/TabSitus';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import type { PropsBersamaPengelola } from '@/Tipe/Pengelola';

export type RingkasHalamanSitus = {
    Uuid: string;
    Slug: string;
    Judul: string;
    Jalur: string;
    Terbit: boolean;
    AdaPerubahan: boolean;
    Aktif: boolean;
    DiterbitkanPada: string | null;
    DiubahPada: string | null;
};

/** Status tampil halaman: Draf (belum pernah terbit), Tersembunyi, Ada perubahan, Terbit. */
export function AmbilStatusHalaman(h: RingkasHalamanSitus): {
    teks: string;
    jenis: 'sukses' | 'peringatan' | 'netral';
} {
    if (!h.Terbit) {
        return { teks: 'Draf', jenis: 'peringatan' };
    }

    if (!h.Aktif) {
        return { teks: 'Tersembunyi', jenis: 'netral' };
    }

    return h.AdaPerubahan
        ? { teks: 'Terbit | ada draf baru', jenis: 'peringatan' }
        : { teks: 'Terbit', jenis: 'sukses' };
}

const kolom: KolomTabel<RingkasHalamanSitus>[] = [
    {
        id: 'Judul',
        accessorKey: 'Judul',
        header: 'Halaman',
        meta: { label: 'Halaman', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: h } }) => (
            <>
                <Link href={`/situs/halaman/${h.Uuid}`} className="block font-semibold text-teks-utama underline">
                    {h.Judul}
                </Link>
                <span className="block font-mono text-keterangan text-teks-sekunder">{h.Jalur}</span>
            </>
        ),
    },
    {
        id: 'Status',
        accessorFn: (h) => AmbilStatusHalaman(h).teks,
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row: { original: h } }) => {
            const status = AmbilStatusHalaman(h);

            return <LabelStatus jenis={status.jenis} teks={status.teks} />;
        },
    },
    {
        id: 'DiterbitkanPada',
        accessorKey: 'DiterbitkanPada',
        header: 'Terakhir terbit',
        meta: { label: 'Terakhir terbit', prioritas: 'rendah' },
        cell: ({ row: { original: h } }) => (h.DiterbitkanPada ? FormatTanggalWaktu(h.DiterbitkanPada) : '–'),
    },
    {
        id: 'DiubahPada',
        accessorKey: 'DiubahPada',
        header: 'Diubah',
        meta: { label: 'Diubah', prioritas: 'rendah' },
        cell: ({ row: { original: h } }) => (h.DiubahPada ? FormatTanggalWaktu(h.DiubahPada) : '–'),
    },
];

/** Daftar halaman situs pemasaran (D-21). Halaman bawaan disiapkan otomatis saat pertama dibuka. */
export default function HalamanDaftarHalamanSitus({
    Halaman,
    Izin,
}: {
    Halaman: RingkasHalamanSitus[];
    Izin: { Kelola: boolean };
}) {
    const { props } = usePage<PropsBersamaPengelola>();
    const [buat, AturBuat] = useState(false);

    return (
        <TataLetakPengelola judul="Situs pemasaran">
            <TabSitus />
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
            <p className="text-isi text-teks-sekunder">
                Susun isi situs payou.id per halaman. Perubahan disimpan sebagai draf, cek lewat Pratinjau, lalu
                Terbitkan agar tampil ke pengunjung.
            </p>
            {buat ? <FormBuatHalaman saatTutup={() => AturBuat(false)} /> : null}
            <AksiHalaman>
                {Izin.Kelola ? <Tombol onClick={() => AturBuat(true)}>Buat halaman</Tombol> : null}
            </AksiHalaman>
            <TabelData
                id="pengelola-situs-halaman"
                label="Halaman situs"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Halaman }}
                ambilIdBaris={(h) => h.Uuid}
                cari="Cari judul atau alamat"
                alamatDetail={(h) => `/situs/halaman/${h.Uuid}`}
                {...(Izin.Kelola
                    ? {
                          aksiBaris: (h: RingkasHalamanSitus) =>
                              h.Terbit && h.Slug !== 'beranda' ? (
                                  <DropdownMenuItem
                                      onSelect={() =>
                                          router.post(
                                              `/situs/halaman/${h.Uuid}/aktif`,
                                              { Aktif: !h.Aktif },
                                              { preserveScroll: true },
                                          )
                                      }
                                  >
                                      {h.Aktif ? 'Sembunyikan dari situs' : 'Tampilkan lagi'}
                                  </DropdownMenuItem>
                              ) : null,
                      }
                    : {})}
                kosong={{ ilustrasi: true, judul: 'Belum ada halaman. Buat halaman pertama untuk situs pemasaran.' }}
            />
        </TataLetakPengelola>
    );
}

function FormBuatHalaman({ saatTutup }: { saatTutup: () => void }) {
    const formulir = useForm({ Judul: '', Slug: '' });
    const [slugDiubah, AturSlugDiubah] = useState(false);
    const BuatSlug = (judul: string) =>
        judul
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 60);

    const Kirim = (p: FormEvent) => {
        p.preventDefault();
        formulir.post('/situs/halaman', { preserveScroll: true });
    };

    return (
        <DialogFormulir
            judul="Buat halaman"
            saatTutup={saatTutup}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Judul halaman"
                    nilai={formulir.data.Judul}
                    saatBerubah={(v) => {
                        formulir.setData((d) => ({ ...d, Judul: v, Slug: slugDiubah ? d.Slug : BuatSlug(v) }));
                    }}
                    galat={formulir.errors.Judul}
                    maxLength={150}
                    required
                    autoFocus
                />
                <BidangTeks
                    label="Alamat halaman"
                    kode
                    keterangan="Huruf kecil, angka, dan tanda hubung. Boleh satu tingkat, misal solusi/apotek. Menjadi payou.id/alamat."
                    nilai={formulir.data.Slug}
                    saatBerubah={(v) => {
                        AturSlugDiubah(true);
                        formulir.setData('Slug', v.toLowerCase());
                    }}
                    galat={formulir.errors.Slug}
                    maxLength={100}
                    required
                />
                <div className="flex justify-end gap-2">
                    <Tombol varian="sekunder" onClick={saatTutup}>
                        Batal
                    </Tombol>
                    <Tombol type="submit" memproses={formulir.processing}>
                        Buat draf
                    </Tombol>
                </div>
            </form>
        </DialogFormulir>
    );
}
