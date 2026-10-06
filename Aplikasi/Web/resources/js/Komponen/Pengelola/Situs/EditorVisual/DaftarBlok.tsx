import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ArrowDown, ArrowUp, ChevronDown, Copy, GripVertical, MoreVertical, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Komponen/Ui/dropdown-menu';

import { IsiBlok } from '../EditorBlok';
import type { NilaiBlok, SkemaBlok } from '../Tipe';
import { AmbilInfoBlok } from './PustakaBlok';
import type { BlokDraf } from './Tipe';

function Teks(nilai: unknown): string {
    return typeof nilai === 'string' ? nilai.trim() : '';
}

/** Ringkasan satu baris isi blok untuk daftar yang diciutkan. */
export function RingkasBlok(blok: NilaiBlok): string {
    const judul = Teks(blok.Judul) || Teks(blok.Label);

    if (judul !== '') {
        return judul;
    }

    const item = Array.isArray(blok.Item) ? (blok.Item[0] as NilaiBlok | undefined) : undefined;

    return (item && (Teks(item.Judul) || Teks(item.Nama) || Teks(item.Pertanyaan))) || '';
}

type PropsKartu = {
    indeks: number;
    jumlah: number;
    blok: BlokDraf;
    skema: SkemaBlok;
    label: string;
    terbuka: boolean;
    terpilih: boolean;
    galat: Record<string, string>;
    ikon: string[];
    bolehUbah: boolean;
    saatToggle: () => void;
    saatBerubah: (blok: BlokDraf) => void;
    saatPindah: (arah: -1 | 1) => void;
    saatGandakan: () => void;
    saatHapus: () => void;
    saatSisipkan: () => void;
};

function KartuBlok({
    indeks,
    jumlah,
    blok,
    skema,
    label,
    terbuka,
    terpilih,
    galat,
    ikon,
    bolehUbah,
    saatToggle,
    saatBerubah,
    saatPindah,
    saatGandakan,
    saatHapus,
    saatSisipkan,
}: PropsKartu) {
    const {
        attributes,
        listeners,
        setNodeRef: AturNode,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: blok._id,
        disabled: !bolehUbah,
    });
    const elemen = useRef<HTMLDivElement | null>(null);
    const awalan = `Bagian.${String(indeks)}`;
    const adaGalat = Object.keys(galat).some((k) => k === awalan || k.startsWith(`${awalan}.`));
    const info = AmbilInfoBlok(String(blok.Jenis));
    const Ikon = info.ikon;
    const ringkas = RingkasBlok(blok);

    useEffect(() => {
        if (terpilih) {
            elemen.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }, [terpilih]);

    return (
        <div
            ref={(node) => {
                AturNode(node);
                elemen.current = node;
            }}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={`rounded-panel border bg-permukaan ${isDragging ? 'z-10 shadow-lg' : ''} ${
                terpilih ? 'border-brand ring-2 ring-brand/30' : adaGalat ? 'border-bahaya' : 'border-garis'
            }`}
        >
            <div className="flex items-center gap-1 pr-1">
                {bolehUbah ? (
                    <button
                        type="button"
                        aria-label={`Geser blok ${String(indeks + 1)}: ${label}`}
                        className="flex size-10 shrink-0 cursor-grab touch-none items-center justify-center rounded-kontrol text-teks-sekunder outline-none hover:bg-permukaan-sorot focus-visible:ring-2 focus-visible:ring-brand active:cursor-grabbing"
                        {...attributes}
                        {...listeners}
                    >
                        <GripVertical className="size-4" aria-hidden />
                    </button>
                ) : null}
                <button
                    type="button"
                    aria-expanded={terbuka}
                    onClick={saatToggle}
                    className="flex min-h-12 min-w-0 flex-1 items-center gap-3 rounded-kontrol py-2 text-left outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-kontrol bg-brand-lembut text-brand">
                        <Ikon className="size-4" aria-hidden />
                    </span>
                    <span className="flex min-w-0 flex-1 flex-col">
                        <span className="text-keterangan font-semibold text-teks-sekunder">
                            {label}
                            {adaGalat ? <span className="ml-2 text-bahaya">| perlu diperbaiki</span> : null}
                        </span>
                        <span className="truncate text-isi font-semibold text-teks-utama">
                            {ringkas === '' ? 'Belum ada judul' : ringkas}
                        </span>
                    </span>
                    <ChevronDown
                        className={`size-4 shrink-0 text-teks-sekunder transition-transform ${terbuka ? 'rotate-180' : ''}`}
                        aria-hidden
                    />
                </button>
                {bolehUbah ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                aria-label={`Aksi blok ${String(indeks + 1)}`}
                                className="flex size-10 shrink-0 items-center justify-center rounded-kontrol text-teks-sekunder outline-none hover:bg-permukaan-sorot focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                <MoreVertical className="size-4" aria-hidden />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onSelect={saatSisipkan}>
                                <Plus aria-hidden /> Sisipkan blok di bawah
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={saatGandakan}>
                                <Copy aria-hidden /> Gandakan
                            </DropdownMenuItem>
                            <DropdownMenuItem disabled={indeks === 0} onSelect={() => saatPindah(-1)}>
                                <ArrowUp aria-hidden /> Naikkan
                            </DropdownMenuItem>
                            <DropdownMenuItem disabled={indeks === jumlah - 1} onSelect={() => saatPindah(1)}>
                                <ArrowDown aria-hidden /> Turunkan
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem variant="destructive" onSelect={saatHapus}>
                                <Trash2 aria-hidden /> Hapus blok
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
            </div>
            {terbuka ? (
                <div className="border-t border-garis p-4">
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
            ) : null}
        </div>
    );
}

type PropsDaftarBlok = {
    bagian: BlokDraf[];
    skema: Record<string, SkemaBlok>;
    labelBlok: Record<string, string>;
    ikon: string[];
    galat: Record<string, string>;
    bolehUbah: boolean;
    terbukaId: string | null;
    terpilihId: string | null;
    saatToggle: (id: string) => void;
    saatUbahBlok: (indeks: number, blok: BlokDraf) => void;
    saatUrutUlang: (baru: BlokDraf[]) => void;
    saatGandakan: (indeks: number) => void;
    saatHapus: (indeks: number) => void;
    saatSisipkan: (indeks: number) => void;
};

/** Daftar blok halaman yang bisa diseret (mouse, sentuh, atau papan ketik) dan dilipat (D-63). */
export default function DaftarBlok({
    bagian,
    skema,
    labelBlok,
    ikon,
    galat,
    bolehUbah,
    terbukaId,
    terpilihId,
    saatToggle,
    saatUbahBlok,
    saatUrutUlang,
    saatGandakan,
    saatHapus,
    saatSisipkan,
}: PropsDaftarBlok) {
    const sensor = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const SaatSelesaiSeret = (peristiwa: DragEndEvent) => {
        const { active, over } = peristiwa;

        if (!over || active.id === over.id) {
            return;
        }

        const dari = bagian.findIndex((b) => b._id === active.id);
        const ke = bagian.findIndex((b) => b._id === over.id);

        if (dari >= 0 && ke >= 0) {
            saatUrutUlang(arrayMove(bagian, dari, ke));
        }
    };

    return (
        <DndContext sensors={sensor} collisionDetection={closestCenter} onDragEnd={SaatSelesaiSeret}>
            <SortableContext items={bagian.map((b) => b._id)} strategy={verticalListSortingStrategy}>
                <div className="flex flex-col gap-2">
                    {bagian.map((blok, i) => {
                        const jenis = String(blok.Jenis);
                        const skemaBlok = skema[jenis];

                        return skemaBlok ? (
                            <KartuBlok
                                key={blok._id}
                                indeks={i}
                                jumlah={bagian.length}
                                blok={blok}
                                skema={skemaBlok}
                                label={labelBlok[jenis] ?? jenis}
                                terbuka={terbukaId === blok._id}
                                terpilih={terpilihId === blok._id}
                                galat={galat}
                                ikon={ikon}
                                bolehUbah={bolehUbah}
                                saatToggle={() => saatToggle(blok._id)}
                                saatBerubah={(b) => saatUbahBlok(i, b)}
                                saatPindah={(arah) => saatUrutUlang(arrayMove(bagian, i, i + arah))}
                                saatGandakan={() => saatGandakan(i)}
                                saatHapus={() => saatHapus(i)}
                                saatSisipkan={() => saatSisipkan(i)}
                            />
                        ) : null;
                    })}
                </div>
            </SortableContext>
        </DndContext>
    );
}
