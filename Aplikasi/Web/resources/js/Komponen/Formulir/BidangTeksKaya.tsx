import { Bold, Heading2, Link2, List, ListOrdered } from 'lucide-react';
import { useId, useRef } from 'react';

import { TerapkanFormat, type JenisFormat } from '@/Pustaka/FormatTeksKaya';
import { Textarea } from '@/Komponen/Ui/textarea';
import { cn } from '@/Komponen/Ui/utils';

import {
    BuatKelasKontrol,
    GabungDijelaskanOleh,
    GalatBidang,
    KerangkaBidang,
    KeteranganBidang,
    LabelBidang,
} from './BagianBidang';

type PropsBidangTeksKaya = {
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string;
    baris?: number;
    maksimal?: number;
    required?: boolean;
};

const ALAT = [
    { jenis: 'tebal', label: 'Tebal', ikon: Bold },
    { jenis: 'subjudul', label: 'Subjudul', ikon: Heading2 },
    { jenis: 'butir', label: 'Daftar butir', ikon: List },
    { jenis: 'nomor', label: 'Daftar bernomor', ikon: ListOrdered },
    { jenis: 'tautan', label: 'Tautan', ikon: Link2 },
] as const satisfies readonly { jenis: JenisFormat; label: string; ikon: unknown }[];

/**
 * Isian teks panjang dengan bilah alat format (D-63): tebal, subjudul, daftar, tautan. Hasilnya tetap subset `TeksKaya`
 * (teks biasa dengan penanda), bukan HTML, jadi aman dirender dan tetap bisa disunting tanpa bilah alat.
 */
export default function BidangTeksKaya({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    baris = 12,
    maksimal,
    required,
}: PropsBidangTeksKaya) {
    const id = useId();
    const kolom = useRef<HTMLTextAreaElement | null>(null);

    const Format = (jenis: JenisFormat) => {
        const elemen = kolom.current;

        if (elemen === null) {
            return;
        }

        const hasil = TerapkanFormat(nilai, elemen.selectionStart, elemen.selectionEnd, jenis);
        saatBerubah(hasil.teks);
        // Pilihan dipulihkan sesudah React menulis nilai baru.
        requestAnimationFrame(() => {
            elemen.focus();
            elemen.setSelectionRange(hasil.mulai, hasil.akhir);
        });
    };

    return (
        <KerangkaBidang galat={galat}>
            <LabelBidang htmlFor={id}>{label}</LabelBidang>
            <div role="toolbar" aria-label={`Format ${label}`} className="flex flex-wrap gap-1">
                {ALAT.map(({ jenis, label: namaAlat, ikon: Ikon }) => (
                    <button
                        key={jenis}
                        type="button"
                        title={namaAlat}
                        aria-label={namaAlat}
                        onMouseDown={(peristiwa) => peristiwa.preventDefault()}
                        onClick={() => Format(jenis)}
                        className="inline-flex size-8 items-center justify-center rounded-kontrol border border-garis-input text-teks-utama outline-none hover:bg-permukaan-sorot focus-visible:ring-2 focus-visible:ring-brand pointer-coarse:size-11"
                    >
                        <Ikon className="size-4" aria-hidden />
                    </button>
                ))}
            </div>
            <Textarea
                ref={kolom}
                id={id}
                value={nilai}
                rows={baris}
                maxLength={maksimal}
                required={required}
                onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                aria-invalid={galat ? true : undefined}
                aria-describedby={GabungDijelaskanOleh(keterangan && `${id}-keterangan`, galat && `${id}-galat`)}
                className={BuatKelasKontrol(galat, cn('h-auto py-2 field-sizing-fixed'))}
            />
            {keterangan ? <KeteranganBidang id={`${id}-keterangan`}>{keterangan}</KeteranganBidang> : null}
            {galat ? <GalatBidang id={`${id}-galat`}>{galat}</GalatBidang> : null}
        </KerangkaBidang>
    );
}
