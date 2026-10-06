import { Loader2, Monitor, Smartphone, Tablet } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import type { HalamanSitus } from '@/Tipe/Situs';

import type { Perangkat } from './Tipe';

const LEBAR_PERANGKAT: Record<Perangkat, number> = { komputer: 1280, tablet: 820, hp: 390 };

export const OPSI_PERANGKAT: { kunci: Perangkat; label: string; ikon: typeof Monitor }[] = [
    { kunci: 'komputer', label: 'Komputer', ikon: Monitor },
    { kunci: 'tablet', label: 'Tablet', ikon: Tablet },
    { kunci: 'hp', label: 'HP', ikon: Smartphone },
];

type PropsPanelPratinjau = {
    urlBingkai: string;
    halaman: HalamanSitus | null;
    memuat: boolean;
    terpilih: number | null;
    perangkat: Perangkat;
    saatKlikBlok: (indeks: number) => void;
};

/**
 * Pratinjau situs sungguhan di dalam bingkai (D-63). Bingkai memuat halaman pratinjau di domain situs; isi yang
 * sedang diketik dikirim lewat `postMessage` ke asal itu saja, dan klik pada blok di pratinjau dikirim balik.
 * Lebar bingkai meniru perangkat (komputer/tablet/HP) lalu diperkecil agar muat, jadi tata letak responsif tampil benar.
 */
export default function PanelPratinjau({
    urlBingkai,
    halaman,
    memuat,
    terpilih,
    perangkat,
    saatKlikBlok,
}: PropsPanelPratinjau) {
    const bingkai = useRef<HTMLIFrameElement>(null);
    const wadah = useRef<HTMLDivElement>(null);
    const [ukuran, AturUkuran] = useState({ lebar: 800, tinggi: 600 });
    const [siap, AturSiap] = useState(false);
    const asal = new URL(urlBingkai, window.location.href).origin;

    useEffect(() => {
        const elemen = wadah.current;

        if (!elemen) {
            return;
        }

        const Ukur = () => AturUkuran({ lebar: elemen.clientWidth, tinggi: elemen.clientHeight });
        Ukur();
        const pengamat = new ResizeObserver(Ukur);
        pengamat.observe(elemen);

        return () => pengamat.disconnect();
    }, []);

    useEffect(() => {
        const SaatPesan = (peristiwa: MessageEvent<{ tipe?: string; indeks?: number }>) => {
            if (peristiwa.origin !== asal || peristiwa.source !== bingkai.current?.contentWindow) {
                return;
            }

            if (peristiwa.data.tipe === 'payoung:siap') {
                AturSiap(true);
            } else if (peristiwa.data.tipe === 'payoung:klik-blok' && typeof peristiwa.data.indeks === 'number') {
                saatKlikBlok(peristiwa.data.indeks);
            }
        };

        window.addEventListener('message', SaatPesan);

        return () => window.removeEventListener('message', SaatPesan);
    }, [asal, saatKlikBlok]);

    useEffect(() => {
        if (siap && halaman) {
            bingkai.current?.contentWindow?.postMessage({ tipe: 'payoung:isi', halaman }, asal);
        }
    }, [siap, halaman, asal]);

    useEffect(() => {
        if (siap) {
            bingkai.current?.contentWindow?.postMessage({ tipe: 'payoung:pilih', indeks: terpilih }, asal);
        }
    }, [siap, terpilih, asal]);

    const lebarAsli = LEBAR_PERANGKAT[perangkat];
    const skala = Math.min(1, Math.max(0.2, (ukuran.lebar - 2) / lebarAsli));

    return (
        <div
            ref={wadah}
            className="relative h-full min-h-[420px] w-full overflow-hidden rounded-panel border border-garis bg-latar"
        >
            <div className="mx-auto h-full" style={{ width: lebarAsli * skala }}>
                <iframe
                    ref={bingkai}
                    src={urlBingkai}
                    title="Pratinjau halaman situs"
                    onLoad={() => AturSiap(false)}
                    className="block border-0 bg-permukaan"
                    style={{
                        width: lebarAsli,
                        height: ukuran.tinggi / skala,
                        transform: `scale(${String(skala)})`,
                        transformOrigin: 'top left',
                    }}
                />
            </div>
            {memuat ? (
                <span className="pointer-events-none absolute right-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-permukaan px-3 py-1 text-keterangan font-medium text-teks-sekunder shadow-sm">
                    <Loader2 className="size-3.5 animate-spin" aria-hidden /> Memperbarui…
                </span>
            ) : null}
        </div>
    );
}
