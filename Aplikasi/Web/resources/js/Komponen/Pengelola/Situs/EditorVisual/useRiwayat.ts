import { useCallback, useState } from 'react';

const BATAS_LANGKAH = 80;
/** Ketikan beruntun dalam jeda ini dihitung satu langkah urungkan. */
const JEDA_GABUNG_MS = 900;

export type Riwayat<T> = {
    nilai: T;
    /** Ubah nilai; `gabung` menggabungkan dengan langkah sebelumnya bila terjadi dalam jeda singkat (mengetik). */
    ubah: (pembaruan: T | ((lama: T) => T), gabung?: boolean) => void;
    /** Ganti nilai tanpa mencatat riwayat (muat ulang dari server). */
    atur: (nilai: T) => void;
    urungkan: () => void;
    ulangi: () => void;
    bisaUrungkan: boolean;
    bisaUlangi: boolean;
};

type Keadaan<T> = { lalu: T[]; kini: T; depan: T[]; waktuUbah: number };

/** Riwayat urungkan/ulangi (Ctrl+Z / Ctrl+Y) untuk editor visual situs (D-63). */
export function useRiwayat<T>(awal: T): Riwayat<T> {
    const [keadaan, AturKeadaan] = useState<Keadaan<T>>({ lalu: [], kini: awal, depan: [], waktuUbah: 0 });

    const Ubah = useCallback((pembaruan: T | ((lama: T) => T), gabung = false) => {
        const sekarang = Date.now();

        AturKeadaan((s) => {
            const baru = typeof pembaruan === 'function' ? (pembaruan as (lama: T) => T)(s.kini) : pembaruan;
            const sambung = gabung && sekarang - s.waktuUbah < JEDA_GABUNG_MS && s.lalu.length > 0;

            return {
                lalu: sambung ? s.lalu : [...s.lalu.slice(-(BATAS_LANGKAH - 1)), s.kini],
                kini: baru,
                depan: [],
                waktuUbah: sekarang,
            };
        });
    }, []);

    const Atur = useCallback((baru: T) => AturKeadaan({ lalu: [], kini: baru, depan: [], waktuUbah: 0 }), []);

    const Urungkan = useCallback(() => {
        AturKeadaan((s) => {
            const sebelumnya = s.lalu[s.lalu.length - 1];

            return sebelumnya === undefined
                ? s
                : { lalu: s.lalu.slice(0, -1), kini: sebelumnya, depan: [...s.depan, s.kini], waktuUbah: 0 };
        });
    }, []);

    const Ulangi = useCallback(() => {
        AturKeadaan((s) => {
            const berikutnya = s.depan[s.depan.length - 1];

            return berikutnya === undefined
                ? s
                : { lalu: [...s.lalu, s.kini], kini: berikutnya, depan: s.depan.slice(0, -1), waktuUbah: 0 };
        });
    }, []);

    return {
        nilai: keadaan.kini,
        ubah: Ubah,
        atur: Atur,
        urungkan: Urungkan,
        ulangi: Ulangi,
        bisaUrungkan: keadaan.lalu.length > 0,
        bisaUlangi: keadaan.depan.length > 0,
    };
}
