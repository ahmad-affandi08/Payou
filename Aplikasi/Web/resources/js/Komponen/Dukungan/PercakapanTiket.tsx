import { DownloadIcon } from 'lucide-react';

import { Badge } from '@/Komponen/Ui/badge';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { FormatUkuranBerkas } from '@/Pustaka/FormatUkuran';

export type LampiranPesan = { Uuid: string; NamaAsli: string; Mime: string; UkuranByte: number };

export type PesanTiket = {
    Uuid: string;
    JenisPengirim: 'Pengguna' | 'Pengelola' | 'Sistem';
    NamaPengirim: string;
    CatatanInternal?: boolean;
    Isi: string;
    Lampiran: LampiranPesan[];
    DibuatPada: string;
};

type PropsPercakapan = {
    pesan: PesanTiket[];
    tautanLampiran: (uuidLampiran: string) => string;
};

/**
 * Percakapan tiket dukungan (P-09), dipakai back-office tenant & Platform Pengelola. Pesan sistem ringkas; catatan
 * internal ditandai jelas dengan teks, bukan hanya warna (PRD §17.6.11).
 */
export default function PercakapanTiket({ pesan, tautanLampiran }: PropsPercakapan) {
    return (
        <ol className="flex flex-col gap-3" aria-label="Percakapan tiket">
            {pesan.map((baris) =>
                baris.JenisPengirim === 'Sistem' ? (
                    <li key={baris.Uuid} className="text-keterangan text-teks-sekunder">
                        {baris.CatatanInternal ? <strong className="font-semibold">Internal | </strong> : null}
                        {baris.Isi} | {FormatTanggalWaktu(baris.DibuatPada)}
                    </li>
                ) : (
                    <li
                        key={baris.Uuid}
                        className={`flex flex-col gap-2 rounded-panel border bg-card px-4 py-3 text-card-foreground ${
                            baris.CatatanInternal ? 'border-peringatan border-l-4' : 'border-garis'
                        } ${baris.JenisPengirim === 'Pengelola' ? 'ml-0 sm:ml-8' : 'mr-0 sm:mr-8'}`}
                    >
                        <p className="flex flex-wrap items-baseline gap-x-2 text-label">
                            <span className="font-semibold text-teks-utama">{baris.NamaPengirim}</span>
                            {baris.CatatanInternal ? (
                                <Badge
                                    variant="outline"
                                    className="rounded-kontrol border-peringatan bg-peringatan-lembut text-keterangan font-semibold text-peringatan"
                                >
                                    Catatan internal (tidak terlihat tenant)
                                </Badge>
                            ) : null}
                            <span className="text-teks-sekunder">{FormatTanggalWaktu(baris.DibuatPada)}</span>
                        </p>
                        <p className="whitespace-pre-line break-words text-isi text-teks-utama">{baris.Isi}</p>
                        {baris.Lampiran.length > 0 ? (
                            <ul className="flex flex-col gap-1 text-keterangan">
                                {baris.Lampiran.map((lampiran) => (
                                    <li key={lampiran.Uuid}>
                                        <a
                                            href={tautanLampiran(lampiran.Uuid)}
                                            className="inline-flex items-center gap-1 font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                        >
                                            <DownloadIcon aria-hidden="true" className="size-3.5" />
                                            Unduh {lampiran.NamaAsli}
                                        </a>{' '}
                                        <span className="text-teks-sekunder">
                                            ({FormatUkuranBerkas(lampiran.UkuranByte)})
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </li>
                ),
            )}
        </ol>
    );
}
