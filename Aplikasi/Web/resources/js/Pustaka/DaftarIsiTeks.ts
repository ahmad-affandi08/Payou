export type ButirDaftarIsi = { Id: string; Judul: string; Tingkat: 1 | 2 };

/** Id jangkar dari teks judul: huruf kecil, tanpa tanda baca, spasi jadi strip ("2.1 Pendaftaran" → "2-1-pendaftaran"). */
export function BuatIdJudul(judul: string): string {
    const id = judul
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return id === '' ? 'bagian' : id;
}

/**
 * Daftar isi dari judul `# ` (tingkat 1) dan `## ` (tingkat 2) pada teks kaya, dengan aturan blok yang sama seperti
 * `TeksKaya`: judul hanya dikenali bila berdiri sendiri satu baris dalam satu blok.
 */
export function AmbilDaftarIsi(teks: string): ButirDaftarIsi[] {
    const hasil: ButirDaftarIsi[] = [];

    for (const blok of teks.split(/\n{2,}/)) {
        const baris = blok.split('\n').filter((b) => b.trim() !== '');
        const satu = baris.length === 1 ? (baris[0] ?? '') : '';

        if (satu.startsWith('## ')) {
            hasil.push({ Id: BuatIdJudul(satu.slice(3)), Judul: satu.slice(3), Tingkat: 2 });
        } else if (satu.startsWith('# ')) {
            hasil.push({ Id: BuatIdJudul(satu.slice(2)), Judul: satu.slice(2), Tingkat: 1 });
        }
    }

    return hasil;
}
