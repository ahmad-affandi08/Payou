import { Head } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { KunciKueri } from '@/Pustaka/KunciKueri';

type KodeLayar = { NamaOutlet: string; WajibQr: boolean; Kode: string; BerlakuSampai: string; Qr: string };

export type PropsLayarAbsensi = KodeLayar & { NamaToko: string; AlamatKode: string };

/** Ambil kode baru sedikit sebelum kode lama berakhir, dan paling lambat tiap 10 detik. */
const SELANG_MAKS_MS = 10_000;

async function AmbilKode(alamat: string, sinyal: AbortSignal): Promise<KodeLayar> {
    const respons = await fetch(alamat, { headers: { Accept: 'application/json' }, signal: sinyal, cache: 'no-store' });

    if (!respons.ok) {
        throw new Error(String(respons.status));
    }

    return (await respons.json()) as KodeLayar;
}

export function HitungSisaDetik(berlakuSampai: string, sekarang: number): number {
    return Math.max(0, Math.ceil((Date.parse(berlakuSampai) - sekarang) / 1000));
}

/**
 * F-18 bagian 4 (D-37): layar QR absensi outlet untuk tablet/monitor di outlet. Karyawan memindai QR (atau mengetik 6
 * angkanya) dari halaman absen di HP. Kode berganti tiap 30 detik; layar memperbaruinya sendiri.
 */
export default function LayarAbsensi(props: PropsLayarAbsensi) {
    const [sekarang, AturSekarang] = useState(() => Date.now());
    const kueri = useQuery({
        queryKey: KunciKueri.Karyawan.KodeLayar(props.AlamatKode),
        queryFn: ({ signal }) => AmbilKode(props.AlamatKode, signal),
        initialData: props,
        refetchInterval: (k) =>
            Math.min(SELANG_MAKS_MS, Math.max(1000, Date.parse(k.state.data?.BerlakuSampai ?? '') - Date.now() + 500)),
        refetchIntervalInBackground: true,
        retry: true,
    });

    useEffect(() => {
        const detik = window.setInterval(() => AturSekarang(Date.now()), 1000);

        return () => window.clearInterval(detik);
    }, []);

    const data = kueri.data;
    const sisa = HitungSisaDetik(data.BerlakuSampai, sekarang);

    return (
        <main className="flex min-h-dvh flex-col items-center justify-center gap-6 bg-latar px-4 py-8 text-center">
            <Head title={`Absen | ${data.NamaOutlet}`} />
            <header className="flex flex-col gap-1">
                <p className="text-isi text-teks-sekunder">{props.NamaToko}</p>
                <JudulHalaman skala="situs">Absen {data.NamaOutlet}</JudulHalaman>
            </header>

            <img
                src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(data.Qr)}`}
                alt={`QR absensi berisi kode ${data.Kode}`}
                className="aspect-square w-full max-w-80 rounded-panel border border-garis bg-permukaan p-3"
            />
            <p
                className="font-mono text-tampilan font-semibold tracking-widest text-teks-utama tabular-nums"
                aria-label={`Kode ${data.Kode.split('').join(' ')}`}
            >
                {data.Kode.slice(0, 3)} {data.Kode.slice(3)}
            </p>
            <p className="text-isi text-teks-sekunder" aria-live="polite">
                Berganti dalam {sisa} detik
            </p>

            {kueri.isError ? (
                <Pemberitahuan jenis="peringatan">
                    Kode tidak bisa diperbarui. Periksa koneksi internet layar ini; kode lama tidak diterima setelah
                    berganti.
                </Pemberitahuan>
            ) : null}

            <p className="max-w-md text-keterangan text-teks-sekunder">
                Buka tautan absen di HP Anda, lalu pindai QR ini atau ketik 6 angkanya.
                {data.WajibQr ? ' Absen dari HP di outlet ini wajib memakai kode ini.' : ''}
            </p>
        </main>
    );
}
