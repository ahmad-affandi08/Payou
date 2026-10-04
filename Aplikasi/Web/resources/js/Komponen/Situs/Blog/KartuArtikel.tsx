import TautanSitus from '@/Komponen/Situs/TautanSitus';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { RingkasanArtikel } from '@/Tipe/Situs';

/** Kartu ringkas satu artikel blog (daftar & artikel terkait). */
export default function KartuArtikel({ artikel }: { artikel: RingkasanArtikel }) {
    return (
        <article className="flex h-full flex-col overflow-hidden rounded-panel border border-garis bg-permukaan">
            {artikel.Sampul ? (
                <img
                    src={artikel.Sampul.Url}
                    alt={artikel.Sampul.Alt}
                    width={artikel.Sampul.Lebar ?? undefined}
                    height={artikel.Sampul.Tinggi ?? undefined}
                    loading="lazy"
                    className="aspect-video w-full bg-latar object-cover"
                />
            ) : null}
            <div className="flex flex-1 flex-col gap-2 p-5">
                <p className="text-label text-teks-sekunder">
                    {[artikel.Kategori, artikel.DiterbitkanPada ? FormatTanggal(artikel.DiterbitkanPada) : null]
                        .filter(Boolean)
                        .join(' | ')}
                </p>
                <h2 className="text-subjudul font-semibold text-teks-utama">
                    <TautanSitus href={`/blog/${artikel.Slug}`} className="hover:underline">
                        {artikel.Judul}
                    </TautanSitus>
                </h2>
                {artikel.Ringkasan ? <p className="text-isi text-teks-sekunder">{artikel.Ringkasan}</p> : null}
            </div>
        </article>
    );
}
