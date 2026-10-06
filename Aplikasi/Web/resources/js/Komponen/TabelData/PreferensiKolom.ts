import { useCallback, useState } from 'react';

/**
 * Pilihan kolom per pengguna per tabel (tampil/sembunyi & urutan), disimpan di `localStorage` peramban (PRD §17.4.3).
 * `Tampil`/`Sembunyi` hanya berisi kolom yang diatur eksplisit, sehingga kolom berprioritas rendah tetap disembunyikan
 * otomatis di layar sempit selama pengguna belum memilihnya.
 */
export type PreferensiKolom = { Tampil: string[]; Sembunyi: string[]; Urutan: string[] };

const KOSONG: PreferensiKolom = { Tampil: [], Sembunyi: [], Urutan: [] };

function Kunci(id: string): string {
    return `Payoung:TabelData:${id}`;
}

export function BacaPreferensi(id: string): PreferensiKolom {
    try {
        const mentah = window.localStorage.getItem(Kunci(id));

        if (!mentah) {
            return KOSONG;
        }

        const data = JSON.parse(mentah) as Partial<PreferensiKolom>;
        const Daftar = (nilai: unknown) =>
            Array.isArray(nilai) ? nilai.filter((n): n is string => typeof n === 'string') : [];

        return { Tampil: Daftar(data.Tampil), Sembunyi: Daftar(data.Sembunyi), Urutan: Daftar(data.Urutan) };
    } catch {
        return KOSONG;
    }
}

export function usePreferensiKolom(id: string) {
    const [preferensi, AturPreferensi] = useState<PreferensiKolom>(() => BacaPreferensi(id));

    const Simpan = useCallback(
        (baru: PreferensiKolom) => {
            AturPreferensi(baru);

            try {
                window.localStorage.setItem(Kunci(id), JSON.stringify(baru));
            } catch {
                // Penyimpanan penuh/diblokir: pilihan tetap berlaku di sesi ini.
            }
        },
        [id],
    );

    const AturTampil = (kolom: string, tampil: boolean) =>
        Simpan({
            ...preferensi,
            Tampil: tampil ? [...new Set([...preferensi.Tampil, kolom])] : preferensi.Tampil.filter((k) => k !== kolom),
            Sembunyi: tampil
                ? preferensi.Sembunyi.filter((k) => k !== kolom)
                : [...new Set([...preferensi.Sembunyi, kolom])],
        });

    return {
        preferensi,
        AturTampil,
        AturUrutan: (urutan: string[]) => Simpan({ ...preferensi, Urutan: urutan }),
        Kembalikan: () => Simpan(KOSONG),
    };
}
