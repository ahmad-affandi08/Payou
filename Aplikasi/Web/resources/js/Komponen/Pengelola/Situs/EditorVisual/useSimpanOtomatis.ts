import { useCallback, useEffect, useRef, useState } from 'react';

import { AmbilTokenXsrf } from '@/Pustaka/PermintaanJson';

import { BuatMuatan, KunciDraf, type Draf } from './Tipe';

const JEDA_SIMPAN_MS = 2500;

export type StatusSimpan = 'diam' | 'menunggu' | 'menyimpan' | 'tersimpan' | 'galat';

type Opsi = {
    draf: Draf;
    /** Kunci (lihat `KunciDraf`) isi yang terakhir tersimpan di server. */
    kunciTersimpan: string;
    url: string;
    aktif: boolean;
    saatTersimpan: (kunci: string, adaPerubahan: boolean) => void;
};

/** Pesan galat per bidang dari jawaban 422 Laravel (`errors`) atau bentuk galat Payoung. */
function AmbilGalatBidang(isi: unknown): { pesan: string; bidang: Record<string, string> } {
    const hasil: Record<string, string> = {};
    let pesan = 'Draf belum bisa disimpan. Periksa isian yang ditandai merah.';

    if (typeof isi === 'object' && isi !== null) {
        const kumpulan = (isi as { errors?: Record<string, unknown> }).errors;

        if (kumpulan && typeof kumpulan === 'object') {
            for (const [kunci, nilai] of Object.entries(kumpulan)) {
                const teks = Array.isArray(nilai) ? String(nilai[0] ?? '') : typeof nilai === 'string' ? nilai : '';

                if (teks !== '') {
                    hasil[kunci] = teks;
                }
            }
        }

        const galat = (isi as { Galat?: { Pesan?: unknown } }).Galat;

        if (galat && typeof galat.Pesan === 'string') {
            pesan = galat.Pesan;
        }
    }

    const pertama = Object.values(hasil)[0];

    return { pesan: pertama ?? pesan, bidang: hasil };
}

/**
 * Simpan draf otomatis (D-63): 2,5 detik setelah perubahan terakhir. Hanya menyimpan DRAF; situs publik berubah
 * setelah "Terbitkan". Bila isian belum lengkap, penyimpanan ditolak server dan galatnya ditampilkan per bidang.
 */
export function useSimpanOtomatis({ draf, kunciTersimpan, url, aktif, saatTersimpan }: Opsi) {
    const [status, AturStatus] = useState<StatusSimpan>('diam');
    const [pesan, AturPesan] = useState<string | null>(null);
    const [galat, AturGalat] = useState<Record<string, string>>({});
    const [disimpanPada, AturDisimpanPada] = useState<Date | null>(null);
    const drafTerbaru = useRef(draf);
    const kunciTerbaru = useRef(kunciTersimpan);
    const sibuk = useRef(false);
    const pengatur = useRef<number | undefined>(undefined);
    useEffect(() => {
        drafTerbaru.current = draf;
        kunciTerbaru.current = kunciTersimpan;
    }, [draf, kunciTersimpan]);
    const kunciKini = KunciDraf(draf);
    const kotor = kunciKini !== kunciTersimpan;

    const Simpan = useCallback(async (): Promise<boolean> => {
        if (sibuk.current) {
            return false;
        }

        window.clearTimeout(pengatur.current);
        const contoh = drafTerbaru.current;
        const kunci = KunciDraf(contoh);

        if (kunci === kunciTerbaru.current) {
            return true;
        }

        sibuk.current = true;
        AturStatus('menyimpan');

        try {
            const respons = await fetch(`${url}/draf-otomatis`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': AmbilTokenXsrf(document.cookie),
                },
                credentials: 'same-origin',
                body: JSON.stringify(BuatMuatan(contoh)),
            });
            const isi: unknown = await respons.json().catch(() => null);

            if (respons.ok) {
                kunciTerbaru.current = kunci;
                saatTersimpan(kunci, (isi as { AdaPerubahan?: boolean } | null)?.AdaPerubahan !== false);
                AturGalat({});
                AturPesan(null);
                AturDisimpanPada(new Date());
                AturStatus('tersimpan');

                return true;
            }

            if (respons.status === 422) {
                const { pesan: teks, bidang } = AmbilGalatBidang(isi);
                AturGalat(bidang);
                AturPesan(teks);
            } else if (respons.status === 419) {
                AturPesan('Sesi habis. Muat ulang halaman, lalu coba lagi.');
            } else {
                AturPesan('Draf belum tersimpan. Periksa koneksi, lalu coba lagi.');
            }

            AturStatus('galat');

            return false;
        } catch {
            AturPesan('Draf belum tersimpan. Periksa koneksi, lalu coba lagi.');
            AturStatus('galat');

            return false;
        } finally {
            sibuk.current = false;
        }
    }, [saatTersimpan, url]);

    useEffect(() => {
        if (!aktif || !kotor) {
            return;
        }

        pengatur.current = window.setTimeout(() => void Simpan(), JEDA_SIMPAN_MS);

        return () => window.clearTimeout(pengatur.current);
        // `kunciKini` berganti setiap isi berubah; itulah yang memulai ulang hitungan jeda.
    }, [aktif, kotor, kunciKini, Simpan]);

    return {
        status: kotor && status === 'tersimpan' ? 'menunggu' : status,
        pesan,
        galat,
        disimpanPada,
        kotor,
        simpanSekarang: Simpan,
    };
}
