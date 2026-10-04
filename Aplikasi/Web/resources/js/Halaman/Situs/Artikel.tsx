import { usePage } from '@inertiajs/react';

import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import KartuArtikel from '@/Komponen/Situs/Blog/KartuArtikel';
import TautanSitus from '@/Komponen/Situs/TautanSitus';
import TeksKaya from '@/Komponen/Situs/TeksKaya';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakSitus from '@/TataLetak/TataLetakSitus';
import type { PropsArtikelSitus } from '@/Tipe/Situs';

/** Satu artikel blog situs pemasaran (bagian B2) + artikel terkait sekategori. */
export default function Artikel() {
    const { Halaman, Artikel: artikel, Terkait } = usePage<PropsArtikelSitus>().props;

    return (
        <TataLetakSitus judul={Halaman.Seo.Judul}>
            <article className="mx-auto max-w-3xl px-4 py-12 sm:py-16">
                <nav aria-label="Jejak" className="text-label text-teks-sekunder">
                    <TautanSitus href="/blog" className="text-brand underline">
                        Blog
                    </TautanSitus>
                    {artikel.Kategori ? (
                        <>
                            {' / '}
                            <TautanSitus
                                href={`/blog?kategori=${encodeURIComponent(artikel.Kategori)}`}
                                className="text-brand underline"
                            >
                                {artikel.Kategori}
                            </TautanSitus>
                        </>
                    ) : null}
                </nav>
                <JudulHalaman skala="situs" className="mt-4">
                    {artikel.Judul}
                </JudulHalaman>
                <p className="mt-2 text-label text-teks-sekunder">
                    {[artikel.NamaPenulis, artikel.DiterbitkanPada ? FormatTanggal(artikel.DiterbitkanPada) : null]
                        .filter(Boolean)
                        .join(' | ')}
                </p>
                {artikel.Sampul ? (
                    <img
                        src={artikel.Sampul.Url}
                        alt={artikel.Sampul.Alt}
                        width={artikel.Sampul.Lebar ?? undefined}
                        height={artikel.Sampul.Tinggi ?? undefined}
                        className="mt-6 w-full rounded-panel bg-latar object-cover"
                    />
                ) : null}
                {artikel.Ringkasan ? (
                    <p className="mt-6 text-pengantar font-semibold text-teks-utama">{artikel.Ringkasan}</p>
                ) : null}
                <TeksKaya teks={artikel.Isi} className="mt-6 text-subjudul text-teks-utama" />
            </article>
            {Terkait.length > 0 ? (
                <section aria-labelledby="judul-terkait" className="bg-permukaan py-12">
                    <div className="mx-auto max-w-6xl px-4">
                        <h2 id="judul-terkait" className="text-subjudul font-semibold text-teks-utama">
                            Baca juga
                        </h2>
                        <ul className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {Terkait.map((a) => (
                                <li key={a.Slug}>
                                    <KartuArtikel artikel={a} />
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>
            ) : null}
        </TataLetakSitus>
    );
}
