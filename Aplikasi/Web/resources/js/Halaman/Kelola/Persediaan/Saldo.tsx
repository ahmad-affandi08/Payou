import { Link, usePage } from '@inertiajs/react';

import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { cn } from '@/Komponen/Ui/utils';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import {
    AmbilLabelMetodeHpp,
    AmbilLabelPelacakan,
    FormatHppSatuan,
    FormatJumlahStok,
    FormatLabelGudang,
    FormatNilai,
} from '@/Pustaka/FormatPersediaan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { AmbilTandaDesimal } from '@/Pustaka/HitungDesimal';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant } from '@/Tipe/Organisasi';
import type { BarisSaldoStok, PropsSaldoStok, RingkasanSaldoStok } from '@/Tipe/Persediaan';

const alamat = '/kelola/persediaan/saldo';
/** Batch yang tampil langsung per baris; sisanya diringkas "dan N batch lain" (data ekstrem). */
const batasBatchTampil = 3;

function RincianPelacakan({ saldo }: { saldo: BarisSaldoStok }) {
    if (saldo.Pelacakan === 'Seri') {
        return (
            <span className="block text-keterangan text-teks-sekunder">
                {(saldo.JumlahNomorSeri ?? 0).toLocaleString('id-ID')} nomor seri tersedia
            </span>
        );
    }

    if (saldo.Pelacakan !== 'Batch' || saldo.Batch.length === 0) {
        return null;
    }

    const sisa = saldo.Batch.length - batasBatchTampil;

    return (
        <ul className="text-keterangan text-teks-sekunder" aria-label={`Batch ${saldo.NamaProduk}`}>
            {saldo.Batch.slice(0, batasBatchTampil).map((batch) => (
                <li key={batch.NomorBatch}>
                    <span className="font-mono">{batch.NomorBatch}</span> |{' '}
                    {batch.TanggalKedaluwarsa
                        ? `kedaluwarsa ${FormatTanggal(batch.TanggalKedaluwarsa)}`
                        : 'tanpa tanggal'}{' '}
                    | {FormatJumlahStok(batch.JumlahSisa, saldo.SimbolSatuan)}
                </li>
            ))}
            {sisa > 0 ? <li>dan {sisa.toLocaleString('id-ID')} batch lain (lihat kartu stok)</li> : null}
        </ul>
    );
}

const kolom: KolomTabel<BarisSaldoStok>[] = [
    {
        id: 'Nama',
        accessorKey: 'NamaProduk',
        header: 'Produk',
        meta: { label: 'Produk', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: saldo } }) => {
            const pelacakan = AmbilLabelPelacakan(saldo.Pelacakan);

            return (
                <>
                    <span className="block font-semibold break-words text-teks-utama">{saldo.NamaProduk}</span>
                    <span className="block text-keterangan font-normal text-teks-sekunder">
                        <span className="font-mono">{saldo.Sku ?? 'Tanpa SKU'}</span>
                        {pelacakan ? ` | ${pelacakan}` : null}
                    </span>
                    <RincianPelacakan saldo={saldo} />
                </>
            );
        },
    },
    {
        id: 'Gudang',
        header: 'Lokasi stok',
        enableSorting: false,
        meta: { label: 'Lokasi stok', prioritas: 'penting' },
        cell: ({ row: { original: saldo } }) => (
            <>
                <span className="block break-words text-teks-utama">{saldo.NamaGudang}</span>
                {saldo.NamaOutlet ? (
                    <span className="block text-keterangan text-teks-sekunder">{saldo.NamaOutlet}</span>
                ) : null}
                {!saldo.GudangAktif ? <LabelStatus jenis="netral" teks="Diarsipkan" /> : null}
            </>
        ),
    },
    {
        id: 'Jumlah',
        accessorKey: 'JumlahTersedia',
        header: 'Jumlah tersedia',
        meta: { label: 'Jumlah tersedia', angka: true, prioritas: 'penting' },
        cell: ({ row: { original: saldo } }) => {
            const minus = AmbilTandaDesimal(saldo.JumlahTersedia) < 0;

            return (
                <>
                    <span className={minus ? 'font-semibold text-bahaya' : undefined}>
                        {FormatJumlahStok(saldo.JumlahTersedia, saldo.SimbolSatuan)}
                    </span>
                    {minus ? (
                        <span className="block">
                            <LabelStatus jenis="bahaya" teks="Minus" />
                        </span>
                    ) : null}
                </>
            );
        },
    },
    {
        id: 'HppRataRata',
        header: 'HPP rata-rata',
        enableSorting: false,
        meta: { label: 'HPP rata-rata', angka: true, prioritas: 'rendah' },
        cell: ({ row }) =>
            row.original.HppRataRata === null ? (
                <span className="text-teks-sekunder">Belum diketahui</span>
            ) : (
                FormatHppSatuan(row.original.HppRataRata)
            ),
    },
    {
        id: 'Nilai',
        accessorKey: 'NilaiPersediaan',
        header: 'Nilai persediaan',
        meta: { label: 'Nilai persediaan', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatNilai(row.original.NilaiPersediaan),
    },
    {
        id: 'KartuStok',
        header: () => <span className="sr-only">Kartu stok</span>,
        enableSorting: false,
        meta: { label: 'Kartu stok', wajib: true, kelasSel: 'text-right whitespace-nowrap' },
        cell: ({ row: { original: saldo } }) => (
            <Link
                href={saldo.TautanKartuStok}
                className="font-semibold text-brand underline"
                aria-label={`Kartu stok ${saldo.NamaProduk} di ${saldo.NamaGudang}`}
            >
                Kartu stok
            </Link>
        ),
    },
];

function KartuRingkasan({ ringkasan, metodeHpp }: { ringkasan: RingkasanSaldoStok; metodeHpp: string }) {
    return (
        <section aria-label="Ringkasan saldo stok" className="grid gap-3 sm:grid-cols-3">
            <Card className="gap-1 rounded-panel p-4 shadow-none">
                <span className="text-label font-semibold text-teks-sekunder">Total nilai persediaan</span>
                <span className="text-subjudul font-semibold break-all text-teks-utama tabular-nums">
                    {FormatNilai(ringkasan.TotalNilai)}
                </span>
                <span className="text-keterangan text-teks-sekunder">Metode HPP: {metodeHpp}</span>
            </Card>
            <Card className="gap-1 rounded-panel p-4 shadow-none">
                <span className="text-label font-semibold text-teks-sekunder">Produk × lokasi</span>
                <span className="text-subjudul font-semibold text-teks-utama tabular-nums">
                    {ringkasan.JumlahBaris.toLocaleString('id-ID')}
                </span>
            </Card>
            <Card className="gap-1 rounded-panel p-4 shadow-none">
                <span className="text-label font-semibold text-teks-sekunder">Stok minus</span>
                <span
                    className={cn(
                        'text-subjudul font-semibold tabular-nums',
                        ringkasan.JumlahMinus > 0 ? 'text-bahaya' : 'text-teks-utama',
                    )}
                >
                    {ringkasan.JumlahMinus.toLocaleString('id-ID')}
                </span>
                {ringkasan.JumlahMinus > 0 ? (
                    <Link
                        href={`${alamat}?saring%5BKeadaan%5D=Minus`}
                        className="self-start text-keterangan font-semibold text-brand underline"
                    >
                        Tampilkan stok minus
                    </Link>
                ) : null}
            </Card>
        </section>
    );
}

/** F-05a: saldo stok per produk per lokasi (TabelData D-16), nilai persediaan, dan tautan kartu stok. */
export default function HalamanSaldoStok({ Saldo, OpsiGudang, MetodeHpp }: PropsSaldoStok) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const bolehKelola = PunyaIzinTenant(props.Akses, IzinTenant.PersediaanKelola);
    const saring: DefinisiSaring[] = [
        {
            id: 'Gudang',
            label: 'Lokasi stok',
            jenis: 'pilihan',
            opsi: OpsiGudang.map((gudang) => ({ nilai: gudang.Uuid, label: FormatLabelGudang(gudang) })),
        },
        {
            id: 'Keadaan',
            label: 'Keadaan stok',
            jenis: 'pilihan',
            opsi: [
                { nilai: 'Ada', label: 'Ada stok' },
                { nilai: 'Nol', label: 'Stok nol' },
                { nilai: 'Minus', label: 'Stok minus' },
            ],
        },
    ];

    return (
        <TataLetakAplikasi judul="Saldo stok">
            <DaftarGalatServer galat={props.errors} />

            <TabelData
                id="persediaan-saldo"
                label="Saldo stok"
                kolom={kolom}
                sumber={{ mode: 'server', alamat, awal: Saldo }}
                ambilIdBaris={(saldo) => `${saldo.UuidProduk}-${saldo.UuidGudang}`}
                urutBawaan="Nama"
                cari="Cari nama atau SKU produk"
                saring={saring}
                ringkasan={(hasil) => (
                    <KartuRingkasan
                        ringkasan={(hasil?.Ringkasan as RingkasanSaldoStok | undefined) ?? Saldo.Ringkasan}
                        metodeHpp={AmbilLabelMetodeHpp(MetodeHpp)}
                    />
                )}
                kosong={{
                    ilustrasi: true,
                    judul: 'Belum ada stok tercatat. Isi stok awal agar saldo dan HPP benar sejak hari pertama.',
                    aksi: bolehKelola ? (
                        <Button asChild>
                            <Link href="/kelola/persediaan/stok-awal/buat">Buat stok awal</Link>
                        </Button>
                    ) : (
                        <span>Minta pengelola persediaan mengisi stok awal.</span>
                    ),
                }}
            />
        </TataLetakAplikasi>
    );
}
