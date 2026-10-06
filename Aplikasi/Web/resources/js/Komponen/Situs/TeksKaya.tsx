import { Fragment, type ReactNode } from 'react';

import { BuatIdJudul } from '@/Pustaka/DaftarIsiTeks';

/** Hanya tautan aman yang dirender sebagai `<a>` (tanpa javascript:, data:, dst.). */
function CekTautanAman(url: string): boolean {
    return /^(https:\/\/|\/(?!\/)|#|mailto:|tel:)/.test(url);
}

/** Inline: **tebal** dan [teks](tautan). Teks lain dirender apa adanya (React meng-escape HTML). */
function RenderInline(teks: string, kunci: string): ReactNode[] {
    const hasil: ReactNode[] = [];
    const pola = /\*\*([^*]+)\*\*|\[([^\]]+)\]\(([^)\s]+)\)/g;
    let akhir = 0;
    let cocok: RegExpExecArray | null;
    let i = 0;

    while ((cocok = pola.exec(teks)) !== null) {
        if (cocok.index > akhir) {
            hasil.push(teks.slice(akhir, cocok.index));
        }

        if (cocok[1] !== undefined) {
            hasil.push(<strong key={`${kunci}-b${i}`}>{cocok[1]}</strong>);
        } else if (cocok[2] !== undefined && cocok[3] !== undefined) {
            hasil.push(
                CekTautanAman(cocok[3]) ? (
                    <a key={`${kunci}-a${i}`} href={cocok[3]} className="font-semibold text-brand underline">
                        {cocok[2]}
                    </a>
                ) : (
                    cocok[2]
                ),
            );
        }

        akhir = cocok.index + cocok[0].length;
        i += 1;
    }

    if (akhir < teks.length) {
        hasil.push(teks.slice(akhir));
    }

    return hasil;
}

/**
 * Teks bebas tanpa HTML (D-21, dipakai juga dokumen legal D-28): paragraf dipisah baris kosong, `# `/`## `/`### `
 * judul bertingkat, `- ` butir daftar, `1. ` daftar bernomor, baris baru di dalam paragraf dipertahankan,
 * **tebal**, dan [tautan](url).
 *
 * Tidak ada HTML yang dirender sama sekali — React meng-escape isinya, dan tautan disaring `CekTautanAman` — jadi
 * isi yang ditulis pengelola tidak bisa menyuntikkan skrip ke halaman publik.
 */
export default function TeksKaya({
    teks,
    className,
    jangkar = false,
}: {
    teks: string;
    className?: string;
    /** Beri id pada judul `# ` dan `## ` supaya bisa ditautkan dari daftar isi (dokumen legal). */
    jangkar?: boolean;
}) {
    const blok = teks.split(/\n{2,}/);

    return (
        <div className={`flex flex-col gap-4 ${className ?? ''}`}>
            {blok.map((isi, i) => {
                const baris = isi.split('\n').filter((b) => b.trim() !== '');

                if (baris.length > 0 && baris.every((b) => b.trimStart().startsWith('- '))) {
                    return (
                        <ul key={i} className="flex list-disc flex-col gap-1 pl-5">
                            {baris.map((b, j) => (
                                <li key={j}>{RenderInline(b.trimStart().slice(2), `${i}-${j}`)}</li>
                            ))}
                        </ul>
                    );
                }

                // Daftar bernomor: setiap baris diawali "1. ", "2. ", dan seterusnya. Nomor awalnya dipakai apa
                // adanya supaya pasal yang dikutip sebagian tetap bernomor benar.
                if (baris.length > 0 && baris.every((b) => /^\s*\d+\.\s/.test(b))) {
                    const mulai = Number(/^\s*(\d+)\./.exec(baris[0] ?? '')?.[1] ?? 1);

                    return (
                        <ol key={i} start={mulai} className="flex list-decimal flex-col gap-1 pl-5">
                            {baris.map((b, j) => (
                                <li key={j}>{RenderInline(b.replace(/^\s*\d+\.\s/, ''), `${i}-${j}`)}</li>
                            ))}
                        </ol>
                    );
                }

                // Judul bertingkat. `## ` tetap `h3` seperti sebelumnya supaya artikel blog yang sudah terbit tidak
                // berubah tampilannya; `# ` dan `### ` menambah tingkat di atas & di bawahnya.
                if (baris.length === 1 && baris[0]?.startsWith('### ')) {
                    return (
                        <h4 key={i} className="text-subjudul font-semibold text-teks-utama">
                            {baris[0].slice(4)}
                        </h4>
                    );
                }

                if (baris.length === 1 && baris[0]?.startsWith('## ')) {
                    return (
                        <h3
                            key={i}
                            {...(jangkar ? { id: BuatIdJudul(baris[0].slice(3)) } : {})}
                            className="scroll-mt-24 text-judul font-bold text-teks-utama"
                        >
                            {baris[0].slice(3)}
                        </h3>
                    );
                }

                if (baris.length === 1 && baris[0]?.startsWith('# ')) {
                    return (
                        <h2
                            key={i}
                            {...(jangkar ? { id: BuatIdJudul(baris[0].slice(2)) } : {})}
                            className="scroll-mt-24 text-tampilan font-bold text-teks-utama"
                        >
                            {baris[0].slice(2)}
                        </h2>
                    );
                }

                return (
                    <p key={i}>
                        {baris.map((b, j) => (
                            <Fragment key={j}>
                                {j > 0 ? <br /> : null}
                                {RenderInline(b, `${i}-${j}`)}
                            </Fragment>
                        ))}
                    </p>
                );
            })}
        </div>
    );
}
