import { Link } from '@inertiajs/react';
import { CircleCheckIcon, CircleDashedIcon, CircleIcon } from 'lucide-react';

import { Progress } from '@/Komponen/Ui/progress';
import { cn } from '@/Komponen/Ui/utils';

import type { LangkahPanduan, RingkasanLangkah, StatusLangkahPanduan } from '@/Tipe/PanduanAwal';

/** Teks status langkah; status tidak pernah hanya warna (PRD §17.6.3). */
export const teksStatusLangkah: Record<StatusLangkahPanduan, string> = {
    Selesai: 'Selesai',
    Dilewati: 'Dilewati',
    Belum: 'Belum',
};

const ikonStatus = { Selesai: CircleCheckIcon, Dilewati: CircleDashedIcon, Belum: CircleIcon };

const kelasStatus: Record<StatusLangkahPanduan, string> = {
    Selesai: 'text-sukses',
    Dilewati: 'text-peringatan',
    Belum: 'text-teks-sekunder',
};

type PropsIndikatorLangkah = {
    langkah: RingkasanLangkah[];
    /** Langkah yang sedang dibuka; null di halaman ringkasan. */
    aktif: LangkahPanduan | null;
};

/** Penanda 6 langkah panduan awal. Setiap langkah bisa dibuka kapan saja (lewati & lanjutkan nanti). */
export default function IndikatorLangkah({ langkah, aktif }: PropsIndikatorLangkah) {
    const indeksAktif = langkah.findIndex((item) => item.Kunci === aktif);
    const jumlahSelesai = langkah.filter((item) => item.Status === 'Selesai').length;

    return (
        <nav aria-label="Langkah panduan awal" className="flex flex-col gap-2">
            <p className="text-label text-teks-sekunder">
                {indeksAktif >= 0 ? `Langkah ${String(indeksAktif + 1)} dari ${String(langkah.length)} | ` : ''}
                {`${String(jumlahSelesai)} dari ${String(langkah.length)} langkah selesai`}
            </p>
            {/* Visual saja; angka progres sudah tertulis di atas. */}
            <Progress
                value={langkah.length === 0 ? 0 : Math.round((jumlahSelesai * 100) / langkah.length)}
                aria-hidden="true"
                className="h-1.5 bg-permukaan-sorot"
            />
            <ol className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                {langkah.map((item, indeks) => {
                    const sedangDibuka = item.Kunci === aktif;
                    const IkonStatus = ikonStatus[item.Status];

                    return (
                        <li key={item.Kunci}>
                            <Link
                                href={item.Tautan}
                                aria-current={sedangDibuka ? 'step' : undefined}
                                className={cn(
                                    'flex h-full flex-col gap-0.5 rounded-kontrol border bg-card px-3 py-2 transition-colors outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                    sedangDibuka ? 'border-b-4 border-brand' : 'border-garis',
                                )}
                            >
                                <span className="text-label font-semibold break-words text-teks-utama">
                                    {String(indeks + 1)}. {item.Judul}
                                </span>
                                <span
                                    className={`inline-flex items-center gap-1 text-keterangan font-semibold ${kelasStatus[item.Status]}`}
                                >
                                    <IkonStatus aria-hidden="true" className="size-3.5 shrink-0" />
                                    {sedangDibuka ? 'Sedang dibuka | ' : ''}
                                    {teksStatusLangkah[item.Status]}
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
