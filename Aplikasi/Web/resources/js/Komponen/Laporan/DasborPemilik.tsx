import { Link } from '@inertiajs/react';
import { lazy, Suspense, type ReactNode } from 'react';

import { Button } from '@/Komponen/Ui/button';
import Panel from '@/Komponen/Kelola/Panel';
import { Card, CardContent, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { Skeleton } from '@/Komponen/Ui/skeleton';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatJumlahStok } from '@/Pustaka/FormatPersediaan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { AmbilTandaDesimal } from '@/Pustaka/HitungDesimal';
import { HitungPerubahanPersen, NamaHari } from '@/Pustaka/Laporan';
import type { AngkaPenjualan, DasborPemilik as DataDasbor } from '@/Tipe/Laporan';

// T1 (PRD v3.30): pustaka grafik (recharts) dimuat belakangan supaya Beranda tampil tanpa menunggunya.
const GrafikPenjualanHarian = lazy(() => import('./GrafikPenjualanHarian'));

type Ukuran = 'uang' | 'bilangan';

/** Teks perbandingan: "+12,5% dari kemarin (Rp 10.000.000)" atau "Rabu lalu: belum ada penjualan". */
function TeksBanding({
    sekarang,
    pembanding,
    nama,
    tampil,
}: {
    sekarang: string;
    pembanding: string;
    nama: string;
    tampil: string;
}) {
    const persen = HitungPerubahanPersen(sekarang, pembanding);

    return (
        <span className="block">
            {persen === null ? `${nama}: belum ada penjualan` : `${persen} dari ${nama} (${tampil})`}
        </span>
    );
}

function KartuAngka({
    judul,
    kunci,
    ukuran,
    data,
    namaMingguLalu,
}: {
    judul: string;
    kunci: keyof AngkaPenjualan;
    ukuran: Ukuran;
    data: DataDasbor;
    namaMingguLalu: string;
}) {
    const Ambil = (a: AngkaPenjualan) => String(a[kunci] ?? '0');
    const Tampil = (nilai: string) => (ukuran === 'uang' ? FormatRupiah(nilai) : nilai);
    const KeAngka = (nilai: string) => (ukuran === 'uang' ? nilai : `${nilai}.00`);

    return (
        <Card className="gap-1 py-4 rounded-panel shadow-none">
            <CardHeader className="px-4">
                <CardTitle>
                    <h3 className="text-label font-semibold text-teks-sekunder">{judul}</h3>
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-1 px-4">
                <p className="text-judul font-semibold text-teks-utama tabular-nums">{Tampil(Ambil(data.HariIni))}</p>
                <p className="text-keterangan text-teks-sekunder tabular-nums">
                    <TeksBanding
                        sekarang={KeAngka(Ambil(data.HariIni))}
                        pembanding={KeAngka(Ambil(data.Kemarin))}
                        nama="kemarin"
                        tampil={Tampil(Ambil(data.Kemarin))}
                    />
                    <TeksBanding
                        sekarang={KeAngka(Ambil(data.HariIni))}
                        pembanding={KeAngka(Ambil(data.MingguLalu))}
                        nama={`${namaMingguLalu} lalu`}
                        tampil={Tampil(Ambil(data.MingguLalu))}
                    />
                </p>
            </CardContent>
        </Card>
    );
}

function TautanPanel({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Button asChild variant="link" className="h-auto p-0 text-label font-semibold">
            <Link href={href}>{children}</Link>
        </Button>
    );
}

function BarisDaftar({ kiri, kanan, keterangan }: { kiri: ReactNode; kanan: ReactNode; keterangan?: ReactNode }) {
    return (
        <li className="flex items-start justify-between gap-3 border-b border-garis py-2 last:border-b-0">
            <span className="min-w-0">
                <span className="block break-words text-isi text-teks-utama">{kiri}</span>
                {keterangan ? <span className="block text-keterangan text-teks-sekunder">{keterangan}</span> : null}
            </span>
            <span className="shrink-0 text-right text-isi text-teks-utama tabular-nums">{kanan}</span>
        </li>
    );
}

/**
 * Dasbor pemilik F-14a di beranda: angka hari ini vs kemarin & hari yang sama minggu lalu, grafik 14 hari, produk
 * terlaris, penjualan per outlet, dan hal yang perlu perhatian (stok kritis, shift, selisih kas, penjualan ditinjau).
 */
export default function DasborPemilik({ data }: { data: DataDasbor }) {
    const indeksHari = (new Date(`${data.Tanggal}T00:00:00`).getDay() + 6) % 7;
    const namaMingguLalu = NamaHari[indeksHari] ?? 'minggu';

    return (
        <section aria-labelledby="judul-dasbor" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="judul-dasbor" className="text-subjudul font-semibold text-teks-utama">
                    Ringkasan hari ini | {FormatTanggal(data.Tanggal)}
                </h2>
                <TautanPanel href="/kelola/laporan/penjualan">Buka laporan penjualan</TautanPanel>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KartuAngka
                    judul="Penjualan bersih"
                    kunci="Bersih"
                    ukuran="uang"
                    data={data}
                    namaMingguLalu={namaMingguLalu}
                />
                <KartuAngka
                    judul="Transaksi"
                    kunci="JumlahTransaksi"
                    ukuran="bilangan"
                    data={data}
                    namaMingguLalu={namaMingguLalu}
                />
                <KartuAngka
                    judul="Rata-rata keranjang"
                    kunci="RataRataKeranjang"
                    ukuran="uang"
                    data={data}
                    namaMingguLalu={namaMingguLalu}
                />
                <KartuAngka
                    judul="Laba kotor"
                    kunci="LabaKotor"
                    ukuran="uang"
                    data={data}
                    namaMingguLalu={namaMingguLalu}
                />
            </div>

            <Panel tingkat="h3" judul="Penjualan bersih 14 hari terakhir">
                <Suspense fallback={<Skeleton className="h-64 w-full" aria-label="Memuat grafik" />}>
                    <GrafikPenjualanHarian data={data.Grafik} judul="Penjualan bersih 14 hari terakhir" />
                </Suspense>
            </Panel>

            <div className="grid gap-3 lg:grid-cols-2">
                <Panel
                    tingkat="h3"
                    judul="Produk terlaris 7 hari"
                    aksi={<TautanPanel href="/kelola/laporan/penjualan?tab=produk">Semua produk</TautanPanel>}
                >
                    {data.ProdukTerlaris.length === 0 ? (
                        <p className="text-isi text-teks-sekunder">Belum ada penjualan dalam 7 hari terakhir.</p>
                    ) : (
                        <ol>
                            {data.ProdukTerlaris.map((p) => (
                                <BarisDaftar
                                    key={p.Kunci}
                                    kiri={p.NamaProduk}
                                    keterangan={`${FormatJumlahStok(p.Qty)} terjual`}
                                    kanan={FormatRupiah(p.Bersih)}
                                />
                            ))}
                        </ol>
                    )}
                </Panel>

                <Panel tingkat="h3" judul="Penjualan per outlet hari ini">
                    {data.PerOutlet.length === 0 ? (
                        <p className="text-isi text-teks-sekunder">Belum ada outlet.</p>
                    ) : (
                        <ul>
                            {data.PerOutlet.map((o) => (
                                <BarisDaftar
                                    key={o.Kunci}
                                    kiri={o.NamaOutlet}
                                    keterangan={`${String(o.JumlahTransaksi)} transaksi`}
                                    kanan={FormatRupiah(o.Bersih)}
                                />
                            ))}
                        </ul>
                    )}
                </Panel>

                <Panel
                    tingkat="h3"
                    judul="Perlu perhatian"
                    aksi={
                        data.JumlahPerluTinjauan > 0 ? (
                            <TautanPanel href="/kelola/penjualan?saring[PerluTinjauan]=1">Tinjau penjualan</TautanPanel>
                        ) : null
                    }
                >
                    <ul>
                        <BarisDaftar
                            kiri="Penjualan perlu ditinjau"
                            kanan={
                                <LabelStatus
                                    jenis={data.JumlahPerluTinjauan > 0 ? 'peringatan' : 'netral'}
                                    teks={String(data.JumlahPerluTinjauan)}
                                />
                            }
                        />
                        <BarisDaftar
                            kiri="Shift masih terbuka"
                            keterangan={
                                data.Shift.Terbuka.map((s) => `${s.NamaKasir} | ${s.NamaOutlet}`).join(', ') || null
                            }
                            kanan={String(data.Shift.JumlahTerbuka)}
                        />
                        {data.StokKritis ? (
                            <BarisDaftar
                                kiri={
                                    <Link href="/kelola/laporan/stok?tab=kritis" className="text-brand underline">
                                        Stok kritis
                                    </Link>
                                }
                                keterangan={
                                    data.StokKritis.Baris.map(
                                        (b) => `${b.NamaProduk} (${FormatJumlahStok(b.Saldo, b.SimbolSatuan)})`,
                                    ).join(', ') || null
                                }
                                kanan={
                                    <LabelStatus
                                        jenis={data.StokKritis.Jumlah > 0 ? 'peringatan' : 'netral'}
                                        teks={String(data.StokKritis.Jumlah)}
                                    />
                                }
                            />
                        ) : null}
                    </ul>
                </Panel>

                <Panel
                    tingkat="h3"
                    judul="Selisih kas shift terbaru"
                    aksi={<TautanPanel href="/kelola/kasir/shift">Semua shift</TautanPanel>}
                >
                    {data.Shift.Tertutup.length === 0 ? (
                        <p className="text-isi text-teks-sekunder">Belum ada shift yang ditutup.</p>
                    ) : (
                        <ul>
                            {data.Shift.Tertutup.map((s) => {
                                const tanda = s.Selisih === null ? 0 : AmbilTandaDesimal(s.Selisih);

                                return (
                                    <BarisDaftar
                                        key={s.Uuid}
                                        kiri={
                                            <Link
                                                href={`/kelola/kasir/shift/${s.Uuid}`}
                                                className="text-brand underline"
                                            >
                                                {s.NamaKasir || 'Shift'} | {s.NamaOutlet}
                                            </Link>
                                        }
                                        keterangan={FormatTanggalWaktu(s.DitutupPada)}
                                        kanan={
                                            <span className="flex flex-col items-end gap-1">
                                                <span>{s.Selisih === null ? '—' : FormatRupiah(s.Selisih)}</span>
                                                <LabelStatus
                                                    jenis={tanda === 0 ? 'sukses' : 'peringatan'}
                                                    teks={tanda === 0 ? 'Pas' : tanda > 0 ? 'Lebih' : 'Kurang'}
                                                />
                                            </span>
                                        }
                                    />
                                );
                            })}
                        </ul>
                    )}
                </Panel>
            </div>
        </section>
    );
}
