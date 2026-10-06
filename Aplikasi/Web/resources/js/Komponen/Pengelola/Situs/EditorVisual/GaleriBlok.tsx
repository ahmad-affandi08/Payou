import { Search } from 'lucide-react';
import { useState } from 'react';

import { AmbilInfoBlok, URUTAN_KATEGORI } from './PustakaBlok';

type PropsGaleriBlok = {
    /** Kunci jenis blok yang tersedia (urutan dari server). */
    jenis: string[];
    label: Record<string, string>;
    saatPilih: (jenis: string) => void;
    /** Menjelaskan di mana blok baru akan masuk. */
    keterangan: string;
    bolehUbah: boolean;
};

/** Pustaka blok untuk panel samping studio: cari, lalu klik untuk menyisipkan (D-73). */
export default function GaleriBlok({ jenis, label, saatPilih, keterangan, bolehUbah }: PropsGaleriBlok) {
    const [cari, AturCari] = useState('');
    const kata = cari.trim().toLowerCase();
    const Cocok = (j: string) =>
        kata === '' ||
        (label[j] ?? j).toLowerCase().includes(kata) ||
        AmbilInfoBlok(j).deskripsi.toLowerCase().includes(kata);

    return (
        <div className="flex flex-col gap-3">
            <label className="relative block">
                <span className="sr-only">Cari blok</span>
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-teks-sekunder"
                    aria-hidden
                />
                <input
                    type="search"
                    value={cari}
                    onChange={(e) => AturCari(e.target.value)}
                    placeholder="Cari blok, misal harga atau FAQ"
                    className="h-10 w-full rounded-kontrol border border-garis-input bg-permukaan pr-3 pl-9 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                />
            </label>
            <p className="text-keterangan text-teks-sekunder">{keterangan}</p>
            {URUTAN_KATEGORI.map((kategori) => {
                const daftar = jenis.filter((j) => AmbilInfoBlok(j).kategori === kategori.kunci && Cocok(j));

                if (daftar.length === 0) {
                    return null;
                }

                return (
                    <section key={kategori.kunci} aria-label={kategori.judul} className="flex flex-col gap-1.5">
                        <h3 className="text-keterangan font-semibold tracking-wide text-teks-sekunder uppercase">
                            {kategori.judul}
                        </h3>
                        <div className="flex flex-col gap-1.5">
                            {daftar.map((j) => {
                                const info = AmbilInfoBlok(j);
                                const Ikon = info.ikon;

                                return (
                                    <button
                                        key={j}
                                        type="button"
                                        disabled={!bolehUbah}
                                        onClick={() => saatPilih(j)}
                                        className="flex items-start gap-3 rounded-panel border border-garis bg-permukaan p-2.5 text-left outline-none transition-colors hover:border-brand hover:bg-brand-lembut focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-50"
                                    >
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-kontrol bg-brand-lembut text-brand">
                                            <Ikon className="size-4" aria-hidden />
                                        </span>
                                        <span className="flex min-w-0 flex-col">
                                            <span className="text-isi font-semibold text-teks-utama">
                                                {label[j] ?? j}
                                            </span>
                                            <span className="text-keterangan text-teks-sekunder">{info.deskripsi}</span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}
