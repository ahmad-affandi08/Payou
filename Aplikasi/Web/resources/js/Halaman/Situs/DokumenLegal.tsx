import { Link } from '@inertiajs/react';
import { CalendarClock, Printer } from 'lucide-react';
import { useMemo } from 'react';

import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import TeksKaya from '@/Komponen/Situs/TeksKaya';
import { Button } from '@/Komponen/Ui/button';
import { cn } from '@/Komponen/Ui/utils';
import { AmbilDaftarIsi } from '@/Pustaka/DaftarIsiTeks';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakSitus from '@/TataLetak/TataLetakSitus';

type PropsDokumenLegal = {
    Dokumen: { Label: string; Judul: string; Versi: number; BerlakuMulai: string; Isi: string; Terjadwal: boolean };
    /** Dokumen legal lain yang sedang berlaku, untuk tab di bawah judul. */
    Daftar?: { Label: string; Tautan: string; Aktif: boolean }[];
};

/**
 * Dokumen legal versi yang berlaku (P-06, F-00 langkah 1), di domain pemasaran `payoung.id`.
 *
 * D-28: ikut kepala & kaki situs dan isinya dirender `TeksKaya` (Markdown subset, tanpa HTML). D-72: dokumen
 * sepanjang belasan pasal tidak lagi satu dinding teks. Ada kepala dengan versi & tanggal berlaku, tab antar
 * dokumen legal, daftar isi yang menempel di samping (lipat di HP) dengan tautan ke tiap pasal, dan tombol cetak.
 */
export default function HalamanDokumenLegalPublik({ Dokumen, Daftar = [] }: PropsDokumenLegal) {
    const daftarIsi = useMemo(() => AmbilDaftarIsi(Dokumen.Isi), [Dokumen.Isi]);

    const DaftarTautan = (
        <ul className="flex flex-col gap-1">
            {daftarIsi.map((butir) => (
                <li key={butir.Id} className={butir.Tingkat === 2 ? 'pl-3' : undefined}>
                    <a
                        href={`#${butir.Id}`}
                        className={cn(
                            'block rounded-md px-2 py-1 text-keterangan hover:bg-brand-lembut hover:text-brand',
                            butir.Tingkat === 1 ? 'font-semibold text-teks-utama' : 'text-teks-sekunder',
                        )}
                    >
                        {butir.Judul}
                    </a>
                </li>
            ))}
        </ul>
    );

    return (
        <TataLetakSitus judul={Dokumen.Label}>
            <header className="border-b border-garis bg-brand-lembut">
                <div className="mx-auto flex max-w-5xl flex-col gap-4 px-4 py-10 sm:py-14">
                    <p className="text-keterangan font-semibold tracking-wide text-brand uppercase">{Dokumen.Label}</p>
                    <JudulHalaman skala="situs">{Dokumen.Judul}</JudulHalaman>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-keterangan text-teks-sekunder">
                        <span className="inline-flex items-center gap-1.5">
                            <CalendarClock className="size-4" aria-hidden="true" />
                            {Dokumen.Terjadwal ? 'Akan berlaku mulai' : 'Berlaku mulai'}{' '}
                            {FormatTanggal(Dokumen.BerlakuMulai)}
                        </span>
                        <span aria-hidden="true">|</span>
                        <span>Versi {Dokumen.Versi}</span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="ml-auto print:hidden"
                            onClick={() => window.print()}
                        >
                            <Printer className="size-4" aria-hidden="true" />
                            Cetak
                        </Button>
                    </div>
                    {Dokumen.Terjadwal ? (
                        <p className="rounded-lg border border-peringatan bg-peringatan-lembut px-3 py-2 text-keterangan text-teks-utama">
                            Ini versi yang akan berlaku pada tanggal di atas. Versi yang berlaku saat ini masih memakai
                            ketentuan sebelumnya.
                        </p>
                    ) : null}
                    {Daftar.length > 1 ? (
                        <nav aria-label="Dokumen legal" className="print:hidden">
                            <ul className="flex flex-wrap gap-2">
                                {Daftar.map((dokumen) => (
                                    <li key={dokumen.Tautan}>
                                        <Link
                                            href={dokumen.Tautan}
                                            aria-current={dokumen.Aktif ? 'page' : undefined}
                                            className={cn(
                                                'inline-flex min-h-9 items-center rounded-full border px-4 text-keterangan font-semibold transition-colors',
                                                dokumen.Aktif
                                                    ? 'border-brand bg-brand text-brand-teks'
                                                    : 'border-garis bg-permukaan text-teks-utama hover:border-brand hover:text-brand',
                                            )}
                                        >
                                            {dokumen.Label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    ) : null}
                </div>
            </header>
            <div className="mx-auto grid max-w-5xl gap-8 px-4 py-10 lg:grid-cols-[16rem_minmax(0,1fr)]">
                {daftarIsi.length > 0 ? (
                    <aside className="print:hidden">
                        <details className="rounded-lg border border-garis bg-permukaan p-3 lg:hidden">
                            <summary className="cursor-pointer text-keterangan font-semibold text-teks-utama">
                                Daftar isi
                            </summary>
                            <nav aria-label="Daftar isi" className="mt-2">
                                {DaftarTautan}
                            </nav>
                        </details>
                        <nav
                            aria-label="Daftar isi"
                            className="sticky top-24 hidden max-h-[calc(100vh-8rem)] overflow-y-auto lg:block"
                        >
                            <p className="mb-2 px-2 text-keterangan font-semibold tracking-wide text-teks-sekunder uppercase">
                                Daftar isi
                            </p>
                            {DaftarTautan}
                        </nav>
                    </aside>
                ) : null}
                <main className="min-w-0">
                    <TeksKaya teks={Dokumen.Isi} jangkar className="text-isi leading-relaxed text-teks-utama" />
                </main>
            </div>
        </TataLetakSitus>
    );
}
