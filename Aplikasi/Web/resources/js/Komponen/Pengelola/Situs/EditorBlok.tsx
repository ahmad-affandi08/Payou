import { ArrowDown, ArrowUp, Copy, Plus, Trash2 } from 'lucide-react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksKaya from '@/Komponen/Formulir/BidangTeksKaya';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';

import BidangTautan from './BidangTautan';
import PemilihGambarSitus from './PemilihGambarSitus';
import type { AturanBidang, NilaiBlok, NilaiJson, SkemaBlok } from './Tipe';

/** Label ramah untuk nama bidang skema (D-21). */
const LABEL_BIDANG: Record<string, string> = {
    Label: 'Label kecil di atas judul',
    Judul: 'Judul',
    Subjudul: 'Pengantar',
    TombolUtama: 'Tombol utama',
    TombolKedua: 'Tombol kedua',
    Tombol: 'Tombol',
    Gambar: 'Gambar',
    Catatan: 'Catatan kecil di bawah tombol',
    Kolom: 'Jumlah kolom di layar lebar',
    Item: 'Item',
    Sorotan: 'Sorotan',
    Spesimen: 'Tangkapan layar aplikasi (dipakai bila tanpa gambar)',
    Kelompok: 'Kelompok',
    Ikon: 'Ikon',
    Teks: 'Teks',
    Nama: 'Nama',
    Tautan: 'Tautan',
    Poin: 'Poin (daftar centang)',
    PosisiGambar: 'Posisi gambar',
    Angka: 'Angka',
    Keterangan: 'Keterangan',
    Usaha: 'Nama usaha',
    Kutipan: 'Kutipan',
    Foto: 'Foto',
    Bintang: 'Bintang (0–5)',
    TampilkanTahunan: 'Tampilkan pilihan harga tahunan',
    PaketDisorot: 'Kode paket yang disorot (misal PRO)',
    TeksTombol: 'Teks tombol (kosong = teks bawaan)',
    JenisProspek: 'Jenis formulir (Kontak = pertanyaan, Demo = minta demo)',
    CatatanKaki: 'Catatan di bawah tabel harga (misal "Harga belum termasuk PPN")',
    Pertanyaan: 'Pertanyaan',
    Jawaban: 'Jawaban',
    Isi: 'Isi',
    UrlYoutube: 'Alamat video YouTube',
};

const KETERANGAN_TEKS_PANJANG =
    'Baris kosong = paragraf baru. "- " = butir daftar, "## " = subjudul, **tebal**, [teks](tautan).';

/** Nilai kosong untuk satu blok/item baru menurut skemanya (daftar diisi item minimum). */
export function BuatNilaiKosong(skema: SkemaBlok): NilaiBlok {
    const hasil: NilaiBlok = {};

    for (const [bidang, aturan] of Object.entries(skema)) {
        switch (aturan[0]) {
            case 'Tombol':
                hasil[bidang] = { Label: '', Tautan: '' };
                break;
            case 'Benar':
                hasil[bidang] = false;
                break;
            case 'Daftar':
                hasil[bidang] = Array.from({ length: Math.max(aturan[1], 1) }, () => BuatNilaiKosong(aturan[3]));
                break;
            case 'Bilangan':
            case 'Gambar':
            case 'Ikon':
                hasil[bidang] = null;
                break;
            default:
                hasil[bidang] = '';
        }
    }

    return hasil;
}

function Teks(nilai: unknown): string {
    return typeof nilai === 'string' ? nilai : typeof nilai === 'number' ? String(nilai) : '';
}

type PropsBidangSkema = {
    bidang: string;
    aturan: AturanBidang;
    nilai: unknown;
    saatBerubah: (nilai: NilaiJson) => void;
    galat: Record<string, string>;
    jalur: string;
    ikon: string[];
    bolehUbah: boolean;
};

/** Satu bidang blok menurut aturan skema. */
function BidangSkema({ bidang, aturan, nilai, saatBerubah, galat, jalur, ikon, bolehUbah }: PropsBidangSkema) {
    const label = LABEL_BIDANG[bidang] ?? bidang;
    const g = galat[jalur];

    switch (aturan[0]) {
        case 'Teks':
            return (
                <BidangTeks
                    label={label}
                    nilai={Teks(nilai)}
                    saatBerubah={saatBerubah}
                    galat={g}
                    maxLength={aturan[1]}
                    required={aturan[2] === true}
                    disabled={!bolehUbah}
                />
            );
        case 'TeksPanjang':
            return (
                <div className="sm:col-span-2">
                    {aturan[1] > 1000 ? (
                        <BidangTeksKaya
                            label={label}
                            nilai={Teks(nilai)}
                            saatBerubah={saatBerubah}
                            galat={g}
                            maksimal={aturan[1]}
                            keterangan={KETERANGAN_TEKS_PANJANG}
                            required={aturan[2] === true}
                        />
                    ) : (
                        <BidangTeksPanjang
                            label={label}
                            nilai={Teks(nilai)}
                            saatBerubah={saatBerubah}
                            galat={g}
                            maksimal={aturan[1]}
                            baris={3}
                            {...(aturan[1] > 300 ? { keterangan: KETERANGAN_TEKS_PANJANG } : {})}
                            required={aturan[2] === true}
                        />
                    )}
                </div>
            );
        case 'Tombol': {
            const tombol = (nilai as { Label?: string; Tautan?: string } | null) ?? {};

            return (
                <fieldset className="grid gap-3 rounded-kontrol border border-garis p-3 sm:col-span-2 sm:grid-cols-2">
                    <legend className="px-1 text-label font-semibold text-teks-utama">{label}</legend>
                    <BidangTeks
                        label="Teks tombol"
                        nilai={tombol.Label ?? ''}
                        saatBerubah={(v) => saatBerubah({ ...tombol, Label: v })}
                        galat={galat[`${jalur}.Label`]}
                        maxLength={40}
                        disabled={!bolehUbah}
                    />
                    <BidangTautan
                        label="Tautan tombol"
                        nilai={tombol.Tautan ?? ''}
                        saatBerubah={(v) => saatBerubah({ ...tombol, Tautan: v })}
                        galat={galat[`${jalur}.Tautan`]}
                    />
                </fieldset>
            );
        }
        case 'Tautan':
            return (
                <BidangTautan
                    label={label}
                    nilai={Teks(nilai)}
                    saatBerubah={saatBerubah}
                    galat={g}
                    required={aturan[1] === true}
                    {...(bidang === 'UrlYoutube'
                        ? { keterangan: 'https://www.youtube.com/watch?v=… atau https://youtu.be/…' }
                        : {})}
                />
            );
        case 'Gambar':
            return (
                <PemilihGambarSitus
                    label={label}
                    nilai={typeof nilai === 'string' ? nilai : null}
                    saatBerubah={saatBerubah}
                    galat={g}
                    bolehUbah={bolehUbah}
                />
            );
        case 'Ikon':
            return (
                <BidangPilihan
                    label={label}
                    nilai={Teks(nilai)}
                    kosong="Tanpa ikon"
                    opsi={ikon.map((i) => ({ Nilai: i, Label: i }))}
                    saatBerubah={(v) => saatBerubah(v === '' ? null : v)}
                    galat={g}
                    disabled={!bolehUbah}
                />
            );
        case 'Pilihan':
            return (
                <BidangPilihan
                    label={label}
                    nilai={Teks(nilai)}
                    kosong="Bawaan"
                    opsi={aturan[1].map((p) => ({ Nilai: p, Label: p }))}
                    saatBerubah={(v) => saatBerubah(v === '' ? null : v)}
                    galat={g}
                    disabled={!bolehUbah}
                />
            );
        case 'Bilangan':
            return (
                <BidangTeks
                    label={label}
                    nilai={Teks(nilai)}
                    inputMode="numeric"
                    saatBerubah={(v) => saatBerubah(v.replace(/\D/g, '') === '' ? null : Number(v.replace(/\D/g, '')))}
                    galat={g}
                    maxLength={2}
                    disabled={!bolehUbah}
                />
            );
        case 'Benar':
            return (
                <div className="sm:col-span-2">
                    <KotakCentang label={label} nilai={nilai === true} saatBerubah={saatBerubah} />
                </div>
            );
        case 'Daftar':
            return (
                <EditorDaftar
                    label={label}
                    aturan={aturan}
                    nilai={Array.isArray(nilai) ? (nilai as NilaiBlok[]) : []}
                    saatBerubah={saatBerubah}
                    galat={galat}
                    jalur={jalur}
                    ikon={ikon}
                    bolehUbah={bolehUbah}
                />
            );
        default:
            return null;
    }
}

type PropsEditorDaftar = {
    label: string;
    aturan: ['Daftar', number, number, SkemaBlok];
    nilai: NilaiBlok[];
    saatBerubah: (nilai: NilaiBlok[]) => void;
    galat: Record<string, string>;
    jalur: string;
    ikon: string[];
    bolehUbah: boolean;
};

function EditorDaftar({ label, aturan, nilai, saatBerubah, galat, jalur, ikon, bolehUbah }: PropsEditorDaftar) {
    const [, min, maks, subskema] = aturan;
    const Pindah = (i: number, arah: -1 | 1) => {
        const baru = [...nilai];
        const [item] = baru.splice(i, 1);

        if (item) {
            baru.splice(i + arah, 0, item);
            saatBerubah(baru);
        }
    };

    return (
        <fieldset className="flex flex-col gap-3 sm:col-span-2">
            <legend className="mb-2 text-label font-semibold text-teks-utama">
                {label} ({nilai.length}/{maks})
            </legend>
            {galat[jalur] ? <p className="text-keterangan font-semibold text-bahaya">{galat[jalur]}</p> : null}
            {nilai.map((item, i) => (
                <div key={i} className="flex flex-col gap-3 rounded-kontrol border border-garis bg-latar p-3">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-label font-semibold text-teks-sekunder">
                            {label} {i + 1}
                        </span>
                        {bolehUbah ? (
                            <div className="flex gap-1">
                                <TombolIkon
                                    label={`Naikkan ${label} ${i + 1}`}
                                    disabled={i === 0}
                                    onClick={() => Pindah(i, -1)}
                                >
                                    <ArrowUp />
                                </TombolIkon>
                                <TombolIkon
                                    label={`Turunkan ${label} ${i + 1}`}
                                    disabled={i === nilai.length - 1}
                                    onClick={() => Pindah(i, 1)}
                                >
                                    <ArrowDown />
                                </TombolIkon>
                                <TombolIkon
                                    label={`Hapus ${label} ${i + 1}`}
                                    disabled={nilai.length <= min}
                                    onClick={() => saatBerubah(nilai.filter((_, j) => j !== i))}
                                >
                                    <Trash2 />
                                </TombolIkon>
                            </div>
                        ) : null}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {Object.entries(subskema).map(([bidang, a]) => (
                            <BidangSkema
                                key={bidang}
                                bidang={bidang}
                                aturan={a}
                                nilai={item[bidang]}
                                saatBerubah={(v) =>
                                    saatBerubah(nilai.map((it, j) => (j === i ? { ...it, [bidang]: v } : it)))
                                }
                                galat={galat}
                                jalur={`${jalur}.${i}.${bidang}`}
                                ikon={ikon}
                                bolehUbah={bolehUbah}
                            />
                        ))}
                    </div>
                </div>
            ))}
            {bolehUbah && nilai.length < maks ? (
                <div>
                    <Tombol varian="sekunder" onClick={() => saatBerubah([...nilai, BuatNilaiKosong(subskema)])}>
                        <Plus aria-hidden /> Tambah {label.toLowerCase()}
                    </Tombol>
                </div>
            ) : null}
        </fieldset>
    );
}

function TombolIkon({
    label,
    disabled,
    onClick,
    children,
}: {
    label: string;
    disabled?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
            className="inline-flex size-8 items-center justify-center rounded-kontrol text-teks-sekunder outline-none hover:bg-permukaan-sorot focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-40 pointer-coarse:size-11 [&_svg]:size-4"
        >
            {children}
        </button>
    );
}

type PropsIsiBlok = {
    awalan: string;
    blok: NilaiBlok;
    skema: SkemaBlok;
    galat: Record<string, string>;
    ikon: string[];
    bolehUbah: boolean;
    saatBerubah: (blok: NilaiBlok) => void;
};

/** Bidang-bidang satu blok menurut skemanya (dipakai editor lipat lama dan editor visual D-63). */
export function IsiBlok({ awalan, blok, skema, galat, ikon, bolehUbah, saatBerubah }: PropsIsiBlok) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {galat[awalan] ? (
                <p className="text-keterangan font-semibold text-bahaya sm:col-span-2">{galat[awalan]}</p>
            ) : null}
            {blok.Jenis === 'Harga' ? (
                <p className="rounded-kontrol bg-info-lembut p-3 text-isi text-teks-utama sm:col-span-2">
                    Daftar paket & harga diambil otomatis dari Katalog → Paket dan Harga paket (harga terbit yang
                    berlaku hari ini).
                </p>
            ) : null}
            {blok.Jenis === 'FormulirProspek' ? (
                <p className="rounded-kontrol bg-info-lembut p-3 text-isi text-teks-utama sm:col-span-2">
                    Pengunjung mengisi nama, nomor WhatsApp, dan pesan. Isian masuk ke tab Prospek dan dikirim ke email
                    notifikasi prospek (tab Pengaturan).
                </p>
            ) : null}
            {blok.Jenis === 'UnduhAplikasi' || blok.Jenis === 'Kontak' ? (
                <p className="rounded-kontrol bg-info-lembut p-3 text-isi text-teks-utama sm:col-span-2">
                    Isinya diambil dari tab Pengaturan ({blok.Jenis === 'Kontak' ? 'kontak' : 'tautan unduh aplikasi'}).
                </p>
            ) : null}
            {Object.entries(skema).map(([bidang, aturan]) => (
                <BidangSkema
                    key={bidang}
                    bidang={bidang}
                    aturan={aturan}
                    nilai={blok[bidang]}
                    saatBerubah={(v) => saatBerubah({ ...blok, [bidang]: v })}
                    galat={galat}
                    jalur={`${awalan}.${bidang}`}
                    ikon={ikon}
                    bolehUbah={bolehUbah}
                />
            ))}
        </div>
    );
}

type PropsEditorBlok = {
    indeks: number;
    jumlah: number;
    blok: NilaiBlok;
    skema: SkemaBlok;
    labelJenis: string;
    galat: Record<string, string>;
    ikon: string[];
    bolehUbah: boolean;
    saatBerubah: (blok: NilaiBlok) => void;
    saatPindah: (arah: -1 | 1) => void;
    saatGandakan: () => void;
    saatHapus: () => void;
};

/** Satu blok halaman di editor konsol: bisa dilipat, dipindah, digandakan, dihapus. */
export default function EditorBlok({
    indeks,
    jumlah,
    blok,
    skema,
    labelJenis,
    galat,
    ikon,
    bolehUbah,
    saatBerubah,
    saatPindah,
    saatGandakan,
    saatHapus,
}: PropsEditorBlok) {
    const awalan = `Bagian.${indeks}`;
    const adaGalat = Object.keys(galat).some((k) => k === awalan || k.startsWith(`${awalan}.`));
    const ringkas = Teks(blok.Judul) || Teks(blok.Label) || '(tanpa judul)';

    return (
        <details open={adaGalat || undefined} className="group rounded-panel border border-garis bg-permukaan">
            <summary className="flex min-h-12 cursor-pointer list-none flex-wrap items-center justify-between gap-2 px-4 py-2 [&::-webkit-details-marker]:hidden">
                <span className="flex min-w-0 flex-col">
                    <span className="text-keterangan font-semibold text-teks-sekunder">
                        {indeks + 1}. {labelJenis}
                        {adaGalat ? <span className="ml-2 text-bahaya">| perlu diperbaiki</span> : null}
                    </span>
                    <span className="truncate text-isi font-semibold text-teks-utama">{ringkas}</span>
                </span>
                {bolehUbah ? (
                    <span className="flex gap-1" onClick={(p) => p.preventDefault()}>
                        <TombolIkon
                            label={`Naikkan blok ${indeks + 1}`}
                            disabled={indeks === 0}
                            onClick={() => saatPindah(-1)}
                        >
                            <ArrowUp />
                        </TombolIkon>
                        <TombolIkon
                            label={`Turunkan blok ${indeks + 1}`}
                            disabled={indeks === jumlah - 1}
                            onClick={() => saatPindah(1)}
                        >
                            <ArrowDown />
                        </TombolIkon>
                        <TombolIkon label={`Gandakan blok ${indeks + 1}`} onClick={saatGandakan}>
                            <Copy />
                        </TombolIkon>
                        <TombolIkon label={`Hapus blok ${indeks + 1}`} onClick={saatHapus}>
                            <Trash2 />
                        </TombolIkon>
                    </span>
                ) : null}
            </summary>
            <div className="border-t border-garis p-4">
                <IsiBlok
                    awalan={awalan}
                    blok={blok}
                    skema={skema}
                    galat={galat}
                    ikon={ikon}
                    bolehUbah={bolehUbah}
                    saatBerubah={saatBerubah}
                />
            </div>
        </details>
    );
}
