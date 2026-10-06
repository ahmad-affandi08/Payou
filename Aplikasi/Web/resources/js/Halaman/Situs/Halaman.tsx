import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import RenderBagian from '@/Komponen/Situs/Bagian/RenderBagian';
import TataLetakSitus from '@/TataLetak/TataLetakSitus';
import type { HalamanSitus, PropsHalamanSitus } from '@/Tipe/Situs';

/** Pesan antara editor visual di konsol dan pratinjau yang dibingkainya (D-63). */
type PesanEditor = { tipe: 'payoung:isi'; halaman: HalamanSitus } | { tipe: 'payoung:pilih'; indeks: number | null };

/** Satu halaman situs pemasaran (D-21): beranda, fitur, harga, solusi, dan halaman buatan konsol. */
export default function Halaman() {
    const { Halaman: awal } = usePage<PropsHalamanSitus>().props;
    // Isi kiriman editor berlaku hanya untuk halaman dasar yang sama; berpindah halaman otomatis kembali ke isi server.
    const [kiriman, AturKiriman] = useState<{ dasar: HalamanSitus; isi: HalamanSitus } | null>(null);
    const [terpilih, AturTerpilih] = useState<number | null>(null);
    const halaman = kiriman !== null && kiriman.dasar === awal ? kiriman.isi : awal;
    const asal =
        awal.Pratinjau === true && typeof window !== 'undefined' && window.parent !== window
            ? awal.AsalEditor
            : undefined;

    // Hanya menerima pesan dari konsol yang membingkai halaman ini; sisanya diabaikan.
    useEffect(() => {
        if (!asal) {
            return;
        }

        const SaatPesan = (peristiwa: MessageEvent<PesanEditor>) => {
            if (peristiwa.origin !== asal || peristiwa.source !== window.parent) {
                return;
            }

            const pesan = peristiwa.data;

            if (pesan.tipe === 'payoung:isi') {
                AturKiriman({ dasar: awal, isi: pesan.halaman });
            } else if (pesan.tipe === 'payoung:pilih') {
                AturTerpilih(pesan.indeks);

                if (pesan.indeks !== null) {
                    document
                        .querySelector(`[data-blok="${String(pesan.indeks)}"]`)
                        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        };

        window.addEventListener('message', SaatPesan);
        window.parent.postMessage({ tipe: 'payoung:siap' }, asal);

        return () => window.removeEventListener('message', SaatPesan);
    }, [asal, awal]);

    const penyunting = useMemo(
        () =>
            asal
                ? {
                      terpilih,
                      saatPilih: (indeks: number) => {
                          AturTerpilih(indeks);
                          window.parent.postMessage({ tipe: 'payoung:klik-blok', indeks }, asal);
                      },
                  }
                : undefined,
        [asal, terpilih],
    );

    return (
        <TataLetakSitus judul={halaman.Seo.Judul} pratinjau={halaman.Pratinjau === true}>
            <RenderBagian bagian={halaman.Bagian} {...(penyunting ? { penyunting } : {})} />
        </TataLetakSitus>
    );
}
