import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { FormUnggahGambar } from '@/Komponen/Pengelola/Situs/PemilihGambarSitus';
import TabSitus from '@/Komponen/Pengelola/Situs/TabSitus';
import type { GambarPustaka } from '@/Komponen/Pengelola/Situs/Tipe';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import DialogKonfirmasi from '@/Komponen/Tindakan/DialogKonfirmasi';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatUkuranBerkas } from '@/Pustaka/FormatUkuran';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import type { PropsBersamaPengelola } from '@/Tipe/Pengelola';

const kolom: KolomTabel<GambarPustaka>[] = [
    {
        id: 'NamaBerkas',
        accessorKey: 'NamaBerkas',
        header: 'Gambar',
        meta: { label: 'Gambar', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: g } }) => (
            <span className="flex items-center gap-3">
                <img
                    src={g.Url}
                    alt={g.TeksAlternatif ?? ''}
                    loading="lazy"
                    className="size-14 shrink-0 rounded-kontrol border border-garis bg-latar object-contain"
                />
                <span className="flex min-w-0 flex-col">
                    <span className="truncate text-teks-utama">{g.NamaBerkas}</span>
                    <span className="font-mono text-keterangan font-normal text-teks-sekunder">{g.Url}</span>
                </span>
            </span>
        ),
    },
    {
        id: 'TeksAlternatif',
        accessorKey: 'TeksAlternatif',
        header: 'Teks alternatif',
        meta: { label: 'Teks alternatif', prioritas: 'penting' },
        cell: ({ row: { original: g } }) => g.TeksAlternatif ?? <span className="text-peringatan">Belum diisi</span>,
    },
    {
        id: 'Dimensi',
        accessorFn: (g) => (g.Lebar && g.Tinggi ? `${g.Lebar}×${g.Tinggi}` : '–'),
        header: 'Ukuran',
        meta: { label: 'Ukuran', prioritas: 'rendah' },
        cell: ({ row: { original: g } }) =>
            `${g.Lebar && g.Tinggi ? `${g.Lebar}×${g.Tinggi} | ` : ''}${FormatUkuranBerkas(g.Ukuran)}`,
    },
    {
        id: 'DibuatPada',
        accessorKey: 'DibuatPada',
        header: 'Diunggah',
        meta: { label: 'Diunggah', prioritas: 'rendah' },
        cell: ({ row: { original: g } }) => (g.DibuatPada ? FormatTanggalWaktu(g.DibuatPada) : '–'),
    },
];

/** Pustaka gambar situs pemasaran (D-21). Gambar yang masih dipakai halaman/pengaturan tidak bisa dihapus. */
export default function HalamanGambarSitus({ Gambar, Izin }: { Gambar: GambarPustaka[]; Izin: { Kelola: boolean } }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const [ubah, AturUbah] = useState<GambarPustaka | null>(null);
    const [hapus, AturHapus] = useState<GambarPustaka | null>(null);
    const [memproses, AturMemproses] = useState(false);

    return (
        <TataLetakPengelola judul="Situs pemasaran">
            <TabSitus />
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
            {Izin.Kelola ? <FormUnggahGambar /> : null}
            {ubah ? <FormTeksAlternatif gambar={ubah} saatTutup={() => AturUbah(null)} /> : null}
            {hapus ? (
                <DialogKonfirmasi
                    judul={`Hapus ${hapus.NamaBerkas}?`}
                    labelAksi="Hapus gambar"
                    memproses={memproses}
                    saatKonfirmasi={() =>
                        router.delete(`/situs/gambar/${hapus.Uuid}`, {
                            preserveScroll: true,
                            onStart: () => AturMemproses(true),
                            onFinish: () => {
                                AturMemproses(false);
                                AturHapus(null);
                            },
                        })
                    }
                    saatBatal={() => AturHapus(null)}
                >
                    <p>Gambar yang masih dipakai halaman atau pengaturan situs tidak bisa dihapus.</p>
                </DialogKonfirmasi>
            ) : null}
            <TabelData
                id="pengelola-situs-gambar"
                label="Gambar situs"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Gambar }}
                ambilIdBaris={(g) => g.Uuid}
                cari="Cari nama berkas atau teks alternatif"
                {...(Izin.Kelola
                    ? {
                          aksiBaris: (g: GambarPustaka) => (
                              <>
                                  <DropdownMenuItem onSelect={() => AturUbah(g)}>Ubah teks alternatif</DropdownMenuItem>
                                  <DropdownMenuItem variant="destructive" onSelect={() => AturHapus(g)}>
                                      Hapus
                                  </DropdownMenuItem>
                              </>
                          ),
                      }
                    : {})}
                kosong={{ ilustrasi: true, judul: 'Belum ada gambar. Unggah gambar untuk dipakai di halaman situs.' }}
            />
        </TataLetakPengelola>
    );
}

function FormTeksAlternatif({ gambar, saatTutup }: { gambar: GambarPustaka; saatTutup: () => void }) {
    const [nilai, AturNilai] = useState(gambar.TeksAlternatif ?? '');
    const [memproses, AturMemproses] = useState(false);
    const { props } = usePage<PropsBersamaPengelola>();

    return (
        <DialogFormulir judul={`Teks alternatif ${gambar.NamaBerkas}`} saatTutup={saatTutup}>
            <form
                onSubmit={(p) => {
                    p.preventDefault();
                    router.put(
                        `/situs/gambar/${gambar.Uuid}`,
                        { TeksAlternatif: nilai },
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
                <img src={gambar.Url} alt="" className="max-h-48 w-full rounded-kontrol bg-latar object-contain" />
                <BidangTeks
                    label="Teks alternatif"
                    keterangan="Jelaskan isi gambar untuk pembaca layar & mesin pencari."
                    nilai={nilai}
                    saatBerubah={AturNilai}
                    galat={props.errors.TeksAlternatif}
                    maxLength={150}
                    autoFocus
                />
                <div className="flex justify-end gap-2">
                    <Tombol varian="sekunder" onClick={saatTutup}>
                        Batal
                    </Tombol>
                    <Tombol type="submit" memproses={memproses}>
                        Simpan
                    </Tombol>
                </div>
            </form>
        </DialogFormulir>
    );
}
