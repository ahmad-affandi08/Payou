import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import Panel from '@/Komponen/Kelola/Panel';
import KerangkaMemuat from '@/Komponen/Persediaan/KerangkaMemuat';
import TabKartuStok from '@/Komponen/Persediaan/TabKartuStok';
import PemilihProdukStok from '@/Komponen/Persediaan/PemilihProdukStok';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import {
    AmbilLabelPelacakan,
    FormatHppSatuan,
    FormatJumlahStok,
    FormatLabelGudang,
    FormatNilai,
} from '@/Pustaka/FormatPersediaan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisKartuStok, PropsKartuStok } from '@/Tipe/Persediaan';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';

const alamat = '/kelola/persediaan/kartu-stok';

type SaringKartu = PropsKartuStok['Saring'];
type ProdukKartu = { Uuid: string; Nama: string; Sku: string | null };

/** Parameter query kartu stok (DesainF05a D: `?produk=&gudang=&dari=&sampai=&halaman=`). */
export function BuatQueryKartuStok(saring: SaringKartu): Record<string, string> {
    const query: Record<string, string> = {};

    if (saring.UuidProduk) {
        query.produk = saring.UuidProduk;
    }

    if (saring.UuidGudang) {
        query.gudang = saring.UuidGudang;
    }

    if (saring.Dari) {
        query.dari = saring.Dari;
    }

    if (saring.Sampai) {
        query.sampai = saring.Sampai;
    }

    return query;
}

/** Galat lokal saringan (server tetap memeriksa ulang). */
export function PeriksaSaringKartu(saring: SaringKartu): Partial<Record<'Produk' | 'Gudang' | 'Sampai', string>> {
    const galat: Partial<Record<'Produk' | 'Gudang' | 'Sampai', string>> = {};

    if (!saring.UuidProduk) {
        galat.Produk = 'Pilih produk.';
    }

    if (!saring.UuidGudang) {
        galat.Gudang = 'Pilih lokasi stok.';
    }

    if (saring.Dari && saring.Sampai && saring.Dari > saring.Sampai) {
        galat.Sampai = 'Tanggal akhir tidak boleh sebelum tanggal awal.';
    }

    return galat;
}

function BidangTanggalSaring({
    id,
    label,
    nilai,
    saatBerubah,
    galat,
}: {
    id: string;
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
}) {
    return <PemilihTanggal id={id} label={label} nilai={nilai} saatBerubah={saatBerubah} galat={galat} />;
}

const kolomKartu: KolomTabel<BarisKartuStok>[] = [
    {
        id: 'Tanggal',
        header: 'Tanggal',
        enableSorting: false,
        meta: { label: 'Tanggal', prioritas: 'utama', wajib: true, kelasSel: 'whitespace-nowrap' },
        cell: ({ row: { original: mutasi } }) => (
            <>
                {FormatTanggal(mutasi.TanggalBisnis)}
                <span className="block text-keterangan font-normal text-teks-sekunder">
                    {FormatTanggalWaktu(mutasi.DicatatPada)}
                </span>
            </>
        ),
    },
    {
        id: 'Jenis',
        header: 'Jenis',
        enableSorting: false,
        meta: { label: 'Jenis', prioritas: 'penting' },
        cell: ({ row }) => row.original.LabelJenisMutasi,
    },
    {
        id: 'Referensi',
        header: 'Referensi',
        enableSorting: false,
        meta: { label: 'Referensi', prioritas: 'rendah' },
        cell: ({ row: { original: mutasi } }) =>
            mutasi.TautanReferensi && mutasi.NomorReferensi ? (
                <Link href={mutasi.TautanReferensi} className="font-mono font-semibold break-all text-brand underline">
                    {mutasi.NomorReferensi}
                </Link>
            ) : (
                <span className="font-mono break-all">{mutasi.NomorReferensi ?? '—'}</span>
            ),
    },
    {
        id: 'Masuk',
        header: 'Masuk',
        enableSorting: false,
        meta: { label: 'Masuk', angka: true, prioritas: 'penting' },
        cell: ({ row }) => (row.original.Masuk === null ? '' : FormatJumlahStok(row.original.Masuk)),
    },
    {
        id: 'Keluar',
        header: 'Keluar',
        enableSorting: false,
        meta: { label: 'Keluar', angka: true, prioritas: 'penting' },
        cell: ({ row }) => (row.original.Keluar === null ? '' : FormatJumlahStok(row.original.Keluar)),
    },
    {
        id: 'HppSatuan',
        header: 'HPP satuan',
        enableSorting: false,
        meta: { label: 'HPP satuan', angka: true, prioritas: 'rendah' },
        cell: ({ row }) => FormatHppSatuan(row.original.HppSatuan),
    },
    {
        id: 'TotalHpp',
        header: 'Nilai mutasi',
        enableSorting: false,
        meta: { label: 'Nilai mutasi', angka: true, prioritas: 'rendah' },
        cell: ({ row }) => FormatNilai(row.original.TotalHpp),
    },
    {
        id: 'SaldoSetelah',
        header: 'Saldo',
        enableSorting: false,
        meta: { label: 'Saldo', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatJumlahStok(row.original.SaldoSetelah),
    },
    {
        id: 'NilaiSetelah',
        header: 'Nilai saldo',
        enableSorting: false,
        meta: { label: 'Nilai saldo', angka: true, prioritas: 'rendah' },
        cell: ({ row }) => FormatNilai(row.original.NilaiSetelah),
    },
    {
        id: 'BatchSeri',
        header: 'Batch / seri',
        enableSorting: false,
        meta: { label: 'Batch / seri', prioritas: 'rendah', kelasSel: 'font-mono text-keterangan break-all' },
        cell: ({ row }) => row.original.NomorBatch ?? row.original.NomorSeri ?? '—',
    },
    {
        id: 'DicatatOleh',
        header: 'Dicatat oleh',
        enableSorting: false,
        meta: { label: 'Dicatat oleh', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
        cell: ({ row }) => row.original.DicatatOleh ?? 'Sistem',
    },
];

/** F-05a: kartu stok satu produk di satu lokasi, urut pencatatan (H-5), dengan saldo awal, berjalan, dan akhir. */
export default function HalamanKartuStok({
    Produk,
    Gudang,
    Saring,
    SaldoAwal,
    SaldoAkhir,
    Mutasi,
    OpsiGudang,
}: PropsKartuStok) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [produk, AturProduk] = useState<ProdukKartu | null>(Produk);
    const [saring, AturSaring] = useState<SaringKartu>(Saring);
    const [periksa, AturPeriksa] = useState(false);
    const [memuat, AturMemuat] = useState(false);
    const galat = periksa ? PeriksaSaringKartu(saring) : {};
    const simbol = Produk?.SimbolSatuan ?? '';
    const opsiGudang =
        OpsiGudang.some((gudang) => gudang.Uuid === Gudang?.Uuid) || Gudang === null
            ? OpsiGudang
            : [...OpsiGudang, Gudang];

    const Tampilkan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturPeriksa(true);

        if (Object.keys(PeriksaSaringKartu(saring)).length > 0) {
            return;
        }

        router.get(alamat, BuatQueryKartuStok(saring), {
            preserveScroll: true,
            onStart: () => AturMemuat(true),
            onFinish: () => AturMemuat(false),
        });
    };

    const pelacakan = Produk ? AmbilLabelPelacakan(Produk.Pelacakan) : null;

    return (
        <TataLetakAplikasi judul="Kartu stok">
            <DaftarGalatServer galat={props.errors} />
            <TabKartuStok aktif="produk" uuidProduk={Produk?.Uuid ?? null} />

            <Card className="gap-0 rounded-panel p-4 shadow-none">
                <form
                    onSubmit={Tampilkan}
                    noValidate
                    aria-label="Pilih produk, lokasi, dan rentang tanggal kartu stok"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div className="sm:col-span-2">
                        {produk ? (
                            <div className="flex flex-col gap-1">
                                <span className="text-label font-semibold text-teks-utama">Produk</span>
                                <div className="flex min-h-10 flex-wrap items-center gap-2">
                                    <span className="font-semibold break-words text-teks-utama">{produk.Nama}</span>
                                    <span className="font-mono text-keterangan text-teks-sekunder">
                                        {produk.Sku ?? 'Tanpa SKU'}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="link"
                                        className="h-auto px-0"
                                        onClick={() => {
                                            AturProduk(null);
                                            AturSaring({ ...saring, UuidProduk: null });
                                        }}
                                    >
                                        Ganti produk
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <PemilihProdukStok
                                label="Produk"
                                uuidGudang={saring.UuidGudang}
                                saatPilih={(pilihan) => {
                                    AturProduk({ Uuid: pilihan.Uuid, Nama: pilihan.Nama, Sku: pilihan.Sku });
                                    AturSaring({ ...saring, UuidProduk: pilihan.Uuid });
                                }}
                                galat={galat.Produk}
                            />
                        )}
                    </div>
                    <div className="sm:col-span-2">
                        <BidangPilihan
                            label="Lokasi stok"
                            nilai={saring.UuidGudang ?? ''}
                            kosong="Pilih lokasi stok"
                            opsi={opsiGudang.map((gudang) => ({
                                Nilai: gudang.Uuid,
                                Label: FormatLabelGudang(gudang),
                            }))}
                            saatBerubah={(nilai) => AturSaring({ ...saring, UuidGudang: nilai === '' ? null : nilai })}
                            galat={galat.Gudang}
                        />
                    </div>
                    <BidangTanggalSaring
                        id="kartu-stok-dari"
                        label="Dari tanggal"
                        nilai={saring.Dari}
                        saatBerubah={(nilai) => AturSaring({ ...saring, Dari: nilai })}
                    />
                    <BidangTanggalSaring
                        id="kartu-stok-sampai"
                        label="Sampai tanggal"
                        nilai={saring.Sampai}
                        saatBerubah={(nilai) => AturSaring({ ...saring, Sampai: nilai })}
                        galat={galat.Sampai}
                    />
                    <div className="flex items-end sm:col-span-2">
                        <Tombol type="submit" memproses={memuat}>
                            Tampilkan kartu stok
                        </Tombol>
                    </div>
                </form>
            </Card>

            {memuat ? (
                <KerangkaMemuat label="Memuat kartu stok…" />
            ) : Produk === null || Gudang === null || Mutasi === null ? (
                <KeadaanKosong judul="Pilih produk dan lokasi stok, lalu tekan Tampilkan kartu stok.">
                    <span>
                        Kartu stok berisi setiap barang masuk dan keluar beserta saldo berjalannya. Bisa juga dibuka
                        dari{' '}
                        <Link href="/kelola/persediaan/saldo" className="font-semibold text-brand underline">
                            Saldo stok
                        </Link>
                        .
                    </span>
                </KeadaanKosong>
            ) : (
                <Panel
                    judul={`${Produk.Nama} di ${Gudang.Nama}`}
                    idJudul="judul-kartu-stok"
                    keterangan={
                        <>
                            <span className="font-mono">{Produk.Sku ?? 'Tanpa SKU'}</span>
                            {pelacakan ? ` | ${pelacakan}` : ''} | {FormatLabelGudang(Gudang)} |{' '}
                            {Saring.Dari ? FormatTanggal(Saring.Dari) : 'awal pencatatan'} sampai{' '}
                            {Saring.Sampai ? FormatTanggal(Saring.Sampai) : 'hari ini'} | urut sesuai waktu pencatatan
                        </>
                    }
                >
                    <TabelData
                        id="persediaan-kartu-stok"
                        label={`Kartu stok ${Produk.Nama}`}
                        kolom={kolomKartu}
                        sumber={{ mode: 'server', alamat, awal: Mutasi }}
                        ambilIdBaris={(mutasi) => `${mutasi.DicatatPada}-${mutasi.SaldoSetelah}-${mutasi.NilaiSetelah}`}
                        ringkasan={() =>
                            SaldoAwal && SaldoAkhir ? (
                                <dl className="grid gap-3 sm:grid-cols-2" aria-label="Saldo periode">
                                    {[
                                        { label: 'Saldo awal', saldo: SaldoAwal },
                                        { label: 'Saldo akhir', saldo: SaldoAkhir },
                                    ].map(({ label, saldo }) => (
                                        <div
                                            key={label}
                                            className="rounded-panel border border-garis bg-permukaan-redup p-3"
                                        >
                                            <dt className="text-label font-semibold text-teks-sekunder">{label}</dt>
                                            <dd className="text-subjudul font-semibold text-teks-utama tabular-nums">
                                                {FormatJumlahStok(saldo.Jumlah, simbol)}
                                                <span className="block text-isi font-normal text-teks-sekunder">
                                                    {FormatNilai(saldo.Nilai)}
                                                </span>
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            ) : null
                        }
                        kosong={{ ilustrasi: true, judul: 'Tidak ada mutasi stok di rentang tanggal ini.' }}
                    />
                </Panel>
            )}
        </TataLetakAplikasi>
    );
}
