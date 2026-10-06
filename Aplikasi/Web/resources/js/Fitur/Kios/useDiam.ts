import { useEffect, useRef, useState } from 'react';

/** Detik terakhir sebelum reset, saat pelanggan diminta memastikan masih memesan. */
export const DETIK_PERINGATAN = 10;

const PERISTIWA = ['pointerdown', 'keydown', 'touchstart'] as const;

/**
 * Kios dipakai bergantian oleh orang berbeda: bila tidak disentuh selama `detik` layar kembali ke awal dan keranjang
 * dikosongkan, supaya pesanan orang sebelumnya tidak terlihat atau tercampur. Mengembalikan sisa detik peringatan
 * (null selama belum masuk masa peringatan) dan fungsi `Lanjutkan` untuk membatalkan reset.
 */
export function useDiam(
    aktif: boolean,
    detik: number,
    saatHabis: () => void,
): { sisa: number | null; Lanjutkan: () => void } {
    const [sisa, AturSisa] = useState<number | null>(null);
    const terakhir = useRef(0);
    const habis = useRef(saatHabis);

    useEffect(() => {
        habis.current = saatHabis;
    }, [saatHabis]);

    useEffect(() => {
        if (!aktif) {
            return;
        }

        terakhir.current = Date.now();
        const Sentuh = () => {
            terakhir.current = Date.now();
            AturSisa(null);
        };

        PERISTIWA.forEach((nama) => window.addEventListener(nama, Sentuh, { passive: true }));
        const pewaktu = window.setInterval(() => {
            const berlalu = (Date.now() - terakhir.current) / 1000;

            if (berlalu >= detik) {
                AturSisa(null);
                habis.current();
            } else if (berlalu >= detik - DETIK_PERINGATAN) {
                AturSisa(Math.ceil(detik - berlalu));
            }
        }, 1000);

        return () => {
            PERISTIWA.forEach((nama) => window.removeEventListener(nama, Sentuh));
            window.clearInterval(pewaktu);
        };
    }, [aktif, detik]);

    return {
        // Di luar masa aktif sisa lama bisa tertinggal; tidak boleh ikut ditampilkan.
        sisa: aktif ? sisa : null,
        Lanjutkan: () => {
            terakhir.current = Date.now();
            AturSisa(null);
        },
    };
}
