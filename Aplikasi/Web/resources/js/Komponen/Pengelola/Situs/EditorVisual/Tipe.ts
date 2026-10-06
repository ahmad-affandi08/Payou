import type { NilaiBlok } from '../Tipe';

/** Blok di editor visual: `_id` hanya penanda tetap untuk seret-dan-lepas, tidak pernah dikirim ke server. */
export type BlokDraf = NilaiBlok & { _id: string };

/** Seluruh isi halaman yang sedang disunting (satu unit urungkan/ulangi). */
export type Draf = {
    Slug: string;
    Judul: string;
    JudulSeo: string;
    DeskripsiSeo: string;
    UuidGambarOg: string | null;
    TampilDiSitemap: boolean;
    Bagian: BlokDraf[];
};

export type Perangkat = 'komputer' | 'tablet' | 'hp';

let penghitung = 0;

export function BuatIdBlok(): string {
    penghitung += 1;

    return `b${Date.now().toString(36)}${penghitung.toString(36)}`;
}

/** Beri penanda tetap pada blok dari server. */
export function BerinyaIdBlok(bagian: NilaiBlok[]): BlokDraf[] {
    return bagian.map((blok) => ({ ...blok, _id: BuatIdBlok() }));
}

/** Isi yang dikirim ke server: tanpa penanda editor. */
export function BuatMuatan(draf: Draf): Omit<Draf, 'Bagian'> & { Bagian: NilaiBlok[] } {
    return {
        ...draf,
        Bagian: draf.Bagian.map((blok) => {
            const salinan: NilaiBlok = { ...blok };
            delete salinan._id;

            return salinan;
        }),
    };
}

/** Kunci pembanding "sudah disimpan atau belum". */
export function KunciDraf(draf: Draf): string {
    return JSON.stringify(BuatMuatan(draf));
}
