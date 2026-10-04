import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';

import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/Komponen/Ui/chart';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { AmbilTinggiGrafik } from '@/Pustaka/Laporan';
import type { TitikGrafik } from '@/Tipe/Laporan';

const konfigurasi = {
    Tinggi: { label: 'Penjualan bersih', color: 'var(--color-grafik-1)' },
} satisfies ChartConfig;

/** Label sumbu tanggal pendek: "7/10". */
function FormatTanggalPendek(tanggal: string): string {
    const [, bulan = '', hari = ''] = tanggal.split('-');

    return `${String(Number(hari))}/${String(Number(bulan))}`;
}

/** Label sumbu nilai ringkas: 1.250.000 → "1,3 jt"; 25.000 → "25 rb". */
function FormatSumbu(nilai: number): string {
    if (nilai >= 1_000_000) {
        return `${(nilai / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`;
    }

    return nilai >= 1_000
        ? `${(nilai / 1_000).toLocaleString('id-ID', { maximumFractionDigits: 0 })} rb`
        : String(nilai);
}

/**
 * Grafik batang penjualan bersih per hari (satu seri, warna token `grafik-1`, tanpa gradien) dengan tooltip. Nilai
 * juga tersedia sebagai daftar untuk pembaca layar (tabel alternatif grafik).
 */
export default function GrafikPenjualanHarian({ data, judul }: { data: TitikGrafik[]; judul: string }) {
    const titik = data.map((t) => ({ ...t, Tinggi: AmbilTinggiGrafik(t.Bersih) }));

    return (
        <figure className="flex flex-col gap-2">
            <figcaption className="sr-only">{judul}</figcaption>
            <ChartContainer config={konfigurasi} className="aspect-auto h-56 w-full" aria-hidden="true">
                <BarChart data={titik} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid vertical={false} stroke="var(--color-garis)" />
                    <XAxis
                        dataKey="Tanggal"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        tickFormatter={FormatTanggalPendek}
                        interval="preserveStartEnd"
                    />
                    <YAxis tickLine={false} axisLine={false} width={56} tickFormatter={FormatSumbu} />
                    <ChartTooltip
                        cursor={{ fill: 'var(--color-permukaan-redup)' }}
                        content={
                            <ChartTooltipContent
                                labelFormatter={(_, isi) => FormatTanggal(String(isi[0]?.payload?.Tanggal ?? ''))}
                                formatter={(_, __, item) => (
                                    <span className="tabular-nums">
                                        {FormatRupiah(String(item.payload?.Bersih ?? '0'))} |{' '}
                                        {String(item.payload?.JumlahTransaksi ?? 0)} transaksi
                                    </span>
                                )}
                            />
                        }
                    />
                    <Bar dataKey="Tinggi" fill="var(--color-Tinggi)" radius={[4, 4, 0, 0]} maxBarSize={28} />
                </BarChart>
            </ChartContainer>
            <ul className="sr-only">
                {data.map((t) => (
                    <li key={t.Tanggal}>
                        {FormatTanggal(t.Tanggal)}: {FormatRupiah(t.Bersih)}, {String(t.JumlahTransaksi)} transaksi
                    </li>
                ))}
            </ul>
        </figure>
    );
}
