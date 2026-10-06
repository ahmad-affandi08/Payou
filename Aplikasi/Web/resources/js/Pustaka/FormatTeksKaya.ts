/**
 * Penerap format untuk bilah alat teks kaya (D-63). Hanya menyisipkan sintaks subset `TeksKaya` (`## `, `### `, `- `,
 * `1. `, `**tebal**`, `[teks](tautan)`): tidak pernah menghasilkan HTML.
 */

export type JenisFormat = 'tebal' | 'subjudul' | 'anakSubjudul' | 'butir' | 'nomor' | 'tautan';

export type HasilFormat = { teks: string; mulai: number; akhir: number };

const POLA_AWALAN = /^(#{1,3} |- |\d+\. )/;

const POLA_JENIS: Record<'subjudul' | 'anakSubjudul' | 'butir' | 'nomor', RegExp> = {
    subjudul: /^## /,
    anakSubjudul: /^### /,
    butir: /^- /,
    nomor: /^\d+\. /,
};

/** Perluas pilihan ke batas baris penuh (untuk format per baris). */
function PerluasKeBaris(teks: string, mulai: number, akhir: number): [number, number] {
    const awalBaris = teks.lastIndexOf('\n', mulai - 1) + 1;
    const akhirIndeks = teks.indexOf('\n', akhir);

    return [awalBaris, akhirIndeks === -1 ? teks.length : akhirIndeks];
}

/** Beri awalan pada tiap baris terisi; bila semuanya sudah berawalan jenis itu, awalannya dilepas (tombol bergantian). */
function TerapkanPerBaris(teks: string, mulai: number, akhir: number, jenis: keyof typeof POLA_JENIS): HasilFormat {
    const [dari, sampai] = PerluasKeBaris(teks, mulai, akhir);
    const baris = teks.slice(dari, sampai).split('\n');
    const terisi = baris.filter((b) => b.trim() !== '');
    const lepas = terisi.length > 0 && terisi.every((b) => POLA_JENIS[jenis].test(b));
    let urut = 0;
    const baru = baris.map((b) => {
        if (b.trim() === '') {
            return b;
        }

        const polos = b.replace(POLA_AWALAN, '');

        if (lepas) {
            return polos;
        }

        urut += 1;
        const awalan = { subjudul: '## ', anakSubjudul: '### ', butir: '- ', nomor: `${String(urut)}. ` }[jenis];

        return `${awalan}${polos}`;
    });
    const gabung = baru.join('\n');

    return { teks: teks.slice(0, dari) + gabung + teks.slice(sampai), mulai: dari, akhir: dari + gabung.length };
}

export function TerapkanFormat(teks: string, mulai: number, akhir: number, jenis: JenisFormat): HasilFormat {
    if (jenis === 'tebal') {
        const dipilih = teks.slice(mulai, akhir);

        if (dipilih.startsWith('**') && dipilih.endsWith('**') && dipilih.length >= 4) {
            const isi = dipilih.slice(2, -2);

            return { teks: teks.slice(0, mulai) + isi + teks.slice(akhir), mulai, akhir: mulai + isi.length };
        }

        const isi = dipilih === '' ? 'teks tebal' : dipilih;

        return {
            teks: `${teks.slice(0, mulai)}**${isi}**${teks.slice(akhir)}`,
            mulai: mulai + 2,
            akhir: mulai + 2 + isi.length,
        };
    }

    if (jenis === 'tautan') {
        const dipilih = teks.slice(mulai, akhir);
        const label = dipilih === '' ? 'teks tautan' : dipilih;
        const alamat = 'https://';
        const awalAlamat = mulai + label.length + 3;

        return {
            teks: `${teks.slice(0, mulai)}[${label}](${alamat})${teks.slice(akhir)}`,
            mulai: awalAlamat,
            akhir: awalAlamat + alamat.length,
        };
    }

    return TerapkanPerBaris(teks, mulai, akhir, jenis);
}
