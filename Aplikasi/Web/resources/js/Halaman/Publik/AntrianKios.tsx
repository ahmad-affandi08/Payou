import { Head } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';

import { KirimJsonKios } from '@/Fitur/Kios/KlienKios';
import { KunciKueri } from '@/Pustaka/KunciKueri';

type DataAntrian = { Disiapkan: string[]; Siap: string[] };

export type PropsAntrianKios = {
    Aktif: boolean;
    Slug: string;
    Token: string;
    Toko: { Nama: string } | null;
    Antrian: DataAntrian;
};

/**
 * F-17 bagian 4: layar antrian untuk monitor/TV di outlet. Dua kolom: nomor yang sedang disiapkan dan yang siap
 * diambil. Memuat ulang datanya tiap 4 detik; tidak ada yang bisa disentuh.
 */
export default function HalamanAntrianKios({ Aktif, Slug, Token, Toko, Antrian }: PropsAntrianKios) {
    const kueri = useQuery({
        queryKey: KunciKueri.Kios.Antrian(Token),
        queryFn: ({ signal }) => KirimJsonKios<DataAntrian>(`/${Slug}/kios/${Token}/antrian/data`, undefined, signal),
        initialData: Antrian,
        refetchInterval: 4000,
        retry: true,
        enabled: Aktif,
    });
    const data = kueri.data;

    return (
        <>
            <Head title={`Antrian ${Toko?.Nama ?? ''}`} />
            <main className="flex min-h-screen flex-col gap-6 bg-brand-gelap p-8 text-brand-teks">
                <h1 className="text-center text-sorotan font-bold">{Toko?.Nama ?? 'Antrian'}</h1>
                {!Aktif || Toko === null ? (
                    <p className="m-auto text-judul">Layar antrian tidak aktif.</p>
                ) : (
                    <div className="grid flex-1 grid-cols-1 gap-6 md:grid-cols-2">
                        <KolomAntrian judul="Sedang disiapkan" nomor={data.Disiapkan} terang={false} />
                        <KolomAntrian judul="Siap diambil" nomor={data.Siap} terang />
                    </div>
                )}
            </main>
        </>
    );
}

function KolomAntrian({ judul, nomor, terang }: { judul: string; nomor: string[]; terang: boolean }) {
    return (
        <section
            aria-label={judul}
            className={`flex flex-col gap-4 rounded-panel p-6 ${terang ? 'bg-permukaan text-teks-utama' : 'border-2 border-brand-teks/40'}`}
        >
            <h2 className="text-center text-sorotan font-semibold">{judul}</h2>
            {nomor.length === 0 ? (
                <p className="m-auto text-judul opacity-70">Belum ada</p>
            ) : (
                <ul className="grid grid-cols-2 gap-3 text-center">
                    {nomor.map((n) => (
                        <li key={n} className="text-sorotan-besar font-bold tabular-nums">
                            {n}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
