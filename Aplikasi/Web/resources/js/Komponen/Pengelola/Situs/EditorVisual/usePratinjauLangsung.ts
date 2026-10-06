import { useEffect, useState } from 'react';

import { AmbilTokenXsrf } from '@/Pustaka/PermintaanJson';
import type { HalamanSitus } from '@/Tipe/Situs';

import { BuatMuatan, type Draf } from './Tipe';

const JEDA_MS = 450;

type Jawaban = { Halaman: HalamanSitus; Galat: Record<string, string> };

/**
 * Pratinjau langsung (D-63): mengirim draf yang belum tersimpan ke server, yang menjawab props halaman yang sudah
 * dirender (harga paket, tautan pintasan, gambar). Permintaan lama dibatalkan bila ada ketikan baru.
 */
export function usePratinjauLangsung(url: string, draf: Draf) {
    const [halaman, AturHalaman] = useState<HalamanSitus | null>(null);
    const [galat, AturGalat] = useState<Record<string, string>>({});
    const [memuat, AturMemuat] = useState(false);

    useEffect(() => {
        const pembatal = new AbortController();
        const pengatur = window.setTimeout(() => {
            AturMemuat(true);
            fetch(`${url}/pratinjau-langsung`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': AmbilTokenXsrf(document.cookie),
                },
                credentials: 'same-origin',
                signal: pembatal.signal,
                body: JSON.stringify(BuatMuatan(draf)),
            })
                .then(async (respons) => (respons.ok ? ((await respons.json()) as Jawaban) : null))
                .then((jawaban) => {
                    if (jawaban) {
                        AturHalaman(jawaban.Halaman);
                        AturGalat(jawaban.Galat);
                    }
                })
                .catch(() => undefined)
                .finally(() => {
                    if (!pembatal.signal.aborted) {
                        AturMemuat(false);
                    }
                });
        }, JEDA_MS);

        return () => {
            window.clearTimeout(pengatur);
            pembatal.abort();
        };
    }, [url, draf]);

    return { halaman, galat, memuat };
}
