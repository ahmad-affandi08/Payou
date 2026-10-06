import { ArrowDown, ArrowUp, Copy, MousePointerClick, Plus, Settings2, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';

import { IsiBlok } from '../EditorBlok';
import type { SkemaBlok } from '../Tipe';
import { RingkasBlok } from './DaftarBlok';
import { AmbilInfoBlok } from './PustakaBlok';
import type { BlokDraf } from './Tipe';

type PropsInspektorBlok = {
    blok: BlokDraf | null;
    indeks: number;
    jumlah: number;
    skema: SkemaBlok | undefined;
    label: string;
    ikon: string[];
    galat: Record<string, string>;
    bolehUbah: boolean;
    saatBerubah: (blok: BlokDraf) => void;
    saatPindah: (arah: -1 | 1) => void;
    saatGandakan: () => void;
    saatHapus: () => void;
    saatSisipkan: () => void;
    saatPengaturanHalaman: () => void;
};

function TombolIkon({
    label,
    disabled,
    saatKlik,
    bahaya,
    children,
}: {
    label: string;
    disabled?: boolean;
    saatKlik: () => void;
    bahaya?: boolean;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            disabled={disabled === true}
            onClick={saatKlik}
            className={`inline-flex size-9 items-center justify-center rounded-kontrol outline-none hover:bg-permukaan-sorot focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-40 ${
                bahaya ? 'text-bahaya' : 'text-teks-utama'
            }`}
        >
            {children}
        </button>
    );
}

/** Inspektor blok terpilih di sisi kanan studio: aksi blok dan seluruh isiannya (D-73). */
export default function InspektorBlok({
    blok,
    indeks,
    jumlah,
    skema,
    label,
    ikon,
    galat,
    bolehUbah,
    saatBerubah,
    saatPindah,
    saatGandakan,
    saatHapus,
    saatSisipkan,
    saatPengaturanHalaman,
}: PropsInspektorBlok) {
    if (blok === null || skema === undefined) {
        return (
            <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                <span className="flex size-12 items-center justify-center rounded-full bg-brand-lembut text-brand">
                    <MousePointerClick className="size-6" aria-hidden />
                </span>
                <p className="text-isi font-semibold text-teks-utama">Belum ada blok dipilih</p>
                <p className="text-keterangan text-teks-sekunder">
                    Klik bagian mana pun di kanvas, atau pilih dari daftar Lapisan, untuk menyunting teks, gambar, dan
                    tombolnya di sini.
                </p>
                <button
                    type="button"
                    onClick={saatPengaturanHalaman}
                    className="inline-flex items-center gap-2 rounded-kontrol border border-garis-input px-3 py-2 text-label font-semibold text-teks-utama outline-none hover:bg-permukaan-sorot focus-visible:ring-2 focus-visible:ring-brand"
                >
                    <Settings2 className="size-4" aria-hidden /> Pengaturan halaman
                </button>
            </div>
        );
    }

    const awalan = `Bagian.${String(indeks)}`;
    const Ikon = AmbilInfoBlok(String(blok.Jenis)).ikon;
    const ringkas = RingkasBlok(blok);

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex items-center gap-3 border-b border-garis px-3 py-3">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-kontrol bg-brand-lembut text-brand">
                    <Ikon className="size-4" aria-hidden />
                </span>
                <span className="flex min-w-0 flex-1 flex-col">
                    <span className="text-keterangan font-semibold text-teks-sekunder">
                        Blok {String(indeks + 1)} dari {String(jumlah)} | {label}
                    </span>
                    <span className="truncate text-isi font-semibold text-teks-utama">
                        {ringkas === '' ? 'Belum ada judul' : ringkas}
                    </span>
                </span>
            </div>
            {bolehUbah ? (
                <div
                    className="flex items-center gap-0.5 border-b border-garis px-2 py-1"
                    role="toolbar"
                    aria-label="Aksi blok"
                >
                    <TombolIkon label="Naikkan blok" disabled={indeks === 0} saatKlik={() => saatPindah(-1)}>
                        <ArrowUp className="size-4" aria-hidden />
                    </TombolIkon>
                    <TombolIkon label="Turunkan blok" disabled={indeks === jumlah - 1} saatKlik={() => saatPindah(1)}>
                        <ArrowDown className="size-4" aria-hidden />
                    </TombolIkon>
                    <TombolIkon label="Gandakan blok" saatKlik={saatGandakan}>
                        <Copy className="size-4" aria-hidden />
                    </TombolIkon>
                    <TombolIkon label="Sisipkan blok di bawah" saatKlik={saatSisipkan}>
                        <Plus className="size-4" aria-hidden />
                    </TombolIkon>
                    <span className="flex-1" />
                    <TombolIkon label="Hapus blok" bahaya saatKlik={saatHapus}>
                        <Trash2 className="size-4" aria-hidden />
                    </TombolIkon>
                </div>
            ) : null}
            <div className="min-h-0 flex-1 overflow-y-auto p-4">
                {galat[awalan] ? (
                    <p className="mb-3 text-keterangan font-semibold text-bahaya">{galat[awalan]}</p>
                ) : null}
                <IsiBlok
                    awalan={awalan}
                    blok={blok}
                    skema={skema}
                    galat={galat}
                    ikon={ikon}
                    bolehUbah={bolehUbah}
                    saatBerubah={(b) => saatBerubah({ ...b, _id: blok._id })}
                />
            </div>
        </div>
    );
}
