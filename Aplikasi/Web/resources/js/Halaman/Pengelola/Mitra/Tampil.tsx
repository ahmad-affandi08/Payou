import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import Panel from '@/Komponen/Kelola/Panel';
import BidangTanggal from '@/Komponen/Pengelola/BidangTanggal';
import FormMitra, { type DataMitraForm } from '@/Komponen/Pengelola/Mitra/FormMitra';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatPersen, FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type TenantRujukan = {
    Nama: string;
    Sumber: string;
    MulaiPada: string;
    StatusLangganan: string | null;
    Berbayar: boolean;
};
type Komisi = {
    Uuid: string;
    NomorTagihan: string;
    NamaTenant: string;
    DasarKomisi: string;
    PersenKomisi: string;
    Jumlah: string;
    Status: 'Tertunda' | 'Dibayar' | 'Dibatalkan';
    AlasanBatal: string | null;
    DibuatPada: string | null;
};
type Pencairan = {
    Uuid: string;
    Periode: string;
    Total: string;
    PotonganPajak: string;
    JumlahBersih: string;
    DibayarPada: string;
    Catatan: string | null;
};
type RincianMitra = DataMitraForm & {
    LabelJenis: string;
    TautanPendaftaran: string;
    Tenant: TenantRujukan[];
    Komisi: Komisi[];
    Pencairan: Pencairan[];
};
type PropsTampilMitra = { Mitra: RincianMitra; OpsiJenis: { Nilai: string; Label: string }[] };

const kolomTenant: KolomTabel<TenantRujukan>[] = [
    { id: 'Nama', accessorKey: 'Nama', header: 'Tenant', meta: { label: 'Tenant', prioritas: 'utama', wajib: true } },
    {
        id: 'MulaiPada',
        accessorKey: 'MulaiPada',
        header: 'Mendaftar',
        meta: { label: 'Mendaftar', prioritas: 'penting' },
        cell: ({ row }) => FormatTanggal(row.original.MulaiPada),
    },
    {
        id: 'StatusLangganan',
        accessorKey: 'StatusLangganan',
        header: 'Langganan',
        meta: { label: 'Langganan', prioritas: 'penting' },
        cell: ({ row }) => (
            <LabelStatus
                jenis={row.original.Berbayar ? 'sukses' : 'netral'}
                teks={row.original.StatusLangganan ?? 'Belum ada'}
            />
        ),
    },
];

const kolomKomisi: KolomTabel<Komisi>[] = [
    {
        id: 'NomorTagihan',
        accessorKey: 'NomorTagihan',
        header: 'Tagihan',
        meta: { label: 'Tagihan', prioritas: 'utama', wajib: true, kelasSel: 'font-mono text-label' },
    },
    { id: 'NamaTenant', accessorKey: 'NamaTenant', header: 'Tenant', meta: { label: 'Tenant', prioritas: 'penting' } },
    {
        id: 'DasarKomisi',
        accessorKey: 'DasarKomisi',
        header: 'Dasar',
        meta: { label: 'Dasar', angka: true, prioritas: 'rendah' },
        cell: ({ row }) => `${FormatRupiah(row.original.DasarKomisi)} × ${FormatPersen(row.original.PersenKomisi)}%`,
    },
    {
        id: 'Jumlah',
        accessorKey: 'Jumlah',
        header: 'Komisi',
        meta: { label: 'Komisi', angka: true, prioritas: 'utama' },
        cell: ({ row }) => FormatRupiah(row.original.Jumlah),
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row: { original: k } }) => (
            <span title={k.AlasanBatal ?? undefined}>
                <LabelStatus
                    jenis={k.Status === 'Dibayar' ? 'sukses' : k.Status === 'Dibatalkan' ? 'bahaya' : 'peringatan'}
                    teks={k.Status}
                />
            </span>
        ),
    },
];

const kolomPencairan: KolomTabel<Pencairan>[] = [
    {
        id: 'Periode',
        accessorKey: 'Periode',
        header: 'Periode',
        meta: { label: 'Periode', prioritas: 'utama', wajib: true, kelasSel: 'font-mono text-label' },
    },
    {
        id: 'Total',
        accessorKey: 'Total',
        header: 'Total komisi',
        meta: { label: 'Total komisi', angka: true, prioritas: 'penting' },
        cell: ({ row }) => FormatRupiah(row.original.Total),
    },
    {
        id: 'PotonganPajak',
        accessorKey: 'PotonganPajak',
        header: 'Potongan pajak',
        meta: { label: 'Potongan pajak', angka: true, prioritas: 'rendah' },
        cell: ({ row }) => FormatRupiah(row.original.PotonganPajak),
    },
    {
        id: 'JumlahBersih',
        accessorKey: 'JumlahBersih',
        header: 'Dibayar',
        meta: { label: 'Dibayar', angka: true, prioritas: 'utama' },
        cell: ({ row }) => FormatRupiah(row.original.JumlahBersih),
    },
    {
        id: 'DibayarPada',
        accessorKey: 'DibayarPada',
        header: 'Tanggal bayar',
        meta: { label: 'Tanggal bayar', prioritas: 'penting' },
        cell: ({ row }) => FormatTanggal(row.original.DibayarPada),
    },
];

/** P-12 rincian mitra: tautan pendaftaran, tenant rujukan, komisi per tagihan lunas, dan pencairan bulanan. */
export default function HalamanTampilMitra({ Mitra, OpsiJenis }: PropsTampilMitra) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.MitraKelola);
    const bolehCairkan = PunyaIzin(props.Pengguna, IzinPengelola.MitraPencairan);
    const [ubah, AturUbah] = useState(false);
    const [cairkan, AturCairkan] = useState(false);
    const [batal, AturBatal] = useState<Komisi | null>(null);
    const [tersalin, AturTersalin] = useState(false);
    const tertunda = Mitra.Komisi.filter((k) => k.Status === 'Tertunda');
    // Tombol ubah tinggal di kepala panel profil: halaman ini memuat tabel, jadi kepala halaman dibiarkan kosong (D-27).
    const aksiProfil = bolehKelola ? <Tombol onClick={() => AturUbah(true)}>Ubah mitra</Tombol> : undefined;

    return (
        <TataLetakPengelola judul={Mitra.Nama} jejak={[{ label: 'Mitra & referral', href: '/mitra' }]}>
            {ubah ? <FormMitra mitra={Mitra} opsiJenis={OpsiJenis} saatSelesai={() => AturUbah(false)} /> : null}
            {cairkan ? <FormPencairan uuidMitra={Mitra.Uuid} saatSelesai={() => AturCairkan(false)} /> : null}
            {batal ? <FormBatalKomisi komisi={batal} saatSelesai={() => AturBatal(null)} /> : null}
            <Panel judul="Profil mitra" aksi={aksiProfil}>
                <dl className="grid gap-x-6 gap-y-2 sm:grid-cols-[auto_1fr]">
                    <dt className="text-teks-sekunder">Kode</dt>
                    <dd className="font-mono">{Mitra.Kode}</dd>
                    <dt className="text-teks-sekunder">Jenis</dt>
                    <dd>{Mitra.LabelJenis}</dd>
                    <dt className="text-teks-sekunder">Komisi</dt>
                    <dd>
                        {FormatPersen(Mitra.PersenKomisi)}% dari tagihan lunas sebelum PPN,{' '}
                        {Mitra.KomisiBerulang ? 'setiap tagihan' : 'hanya tagihan pertama'}
                    </dd>
                    <dt className="text-teks-sekunder">Status</dt>
                    <dd>
                        <LabelStatus jenis={Mitra.Status === 'Aktif' ? 'sukses' : 'netral'} teks={Mitra.Status} />
                    </dd>
                    <dt className="text-teks-sekunder">Rekening</dt>
                    <dd>
                        {[Mitra.NamaBank, Mitra.RekeningTersamar, Mitra.NamaPemilikRekening]
                            .filter(Boolean)
                            .join(' | ') || 'Belum diisi'}
                    </dd>
                    <dt className="text-teks-sekunder">Tautan pendaftaran</dt>
                    <dd className="flex flex-wrap items-center gap-2">
                        <code className="break-all font-mono text-keterangan">{Mitra.TautanPendaftaran}</code>
                        <Tombol
                            varian="sekunder"
                            onClick={() => {
                                void navigator.clipboard
                                    .writeText(Mitra.TautanPendaftaran)
                                    .then(() => AturTersalin(true));
                            }}
                        >
                            {tersalin ? 'Tautan tersalin' : 'Salin tautan'}
                        </Tombol>
                    </dd>
                </dl>
            </Panel>
            <section className="flex flex-col gap-3">
                <h2 className="text-subjudul font-semibold text-teks-utama">Tenant rujukan ({Mitra.Tenant.length})</h2>
                <TabelData
                    id="pengelola-mitra-tenant"
                    label="Tenant rujukan mitra"
                    kolom={kolomTenant}
                    sumber={{ mode: 'lokal', data: Mitra.Tenant }}
                    ambilIdBaris={(t) => `${t.Nama}-${t.MulaiPada}`}
                    urutBawaan="-MulaiPada"
                    cari="Cari nama tenant"
                    kosong={{ judul: 'Belum ada tenant yang mendaftar lewat tautan mitra ini.' }}
                />
            </section>
            <section className="flex flex-col gap-3">
                <h2 className="text-subjudul font-semibold text-teks-utama">
                    Komisi | tertunda {FormatRupiah(tertunda.reduce((t, k) => t + Number(k.Jumlah), 0).toFixed(2))}
                </h2>
                <TabelData
                    id="pengelola-mitra-komisi"
                    label="Komisi mitra"
                    kolom={kolomKomisi}
                    sumber={{ mode: 'lokal', data: Mitra.Komisi }}
                    ambilIdBaris={(k) => k.Uuid}
                    urutBawaan="-NomorTagihan"
                    cari="Cari nomor tagihan atau tenant"
                    {...(bolehKelola
                        ? {
                              aksiBaris: (k: Komisi) =>
                                  k.Status === 'Tertunda' ? (
                                      <DropdownMenuItem onSelect={() => AturBatal(k)}>Batalkan komisi</DropdownMenuItem>
                                  ) : null,
                          }
                        : {})}
                    kosong={{ judul: 'Belum ada komisi. Komisi muncul saat tagihan tenant rujukan lunas.' }}
                />
            </section>
            <section className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-subjudul font-semibold text-teks-utama">Pencairan</h2>
                    {bolehCairkan && tertunda.length > 0 ? (
                        <div className="ml-auto">
                            <Tombol onClick={() => AturCairkan(true)}>Catat pencairan</Tombol>
                        </div>
                    ) : null}
                </div>
                <TabelData
                    id="pengelola-mitra-pencairan"
                    label="Pencairan komisi mitra"
                    kolom={kolomPencairan}
                    sumber={{ mode: 'lokal', data: Mitra.Pencairan }}
                    ambilIdBaris={(p) => p.Uuid}
                    urutBawaan="-Periode"
                    cari="Cari periode"
                    kosong={{ judul: 'Belum ada pencairan.' }}
                />
            </section>
        </TataLetakPengelola>
    );
}

function BulanLalu(): string {
    const sekarang = new Date();
    const lalu = new Date(sekarang.getFullYear(), sekarang.getMonth() - 1, 1);
    return `${String(lalu.getFullYear())}-${String(lalu.getMonth() + 1).padStart(2, '0')}`;
}

function FormPencairan({ uuidMitra, saatSelesai }: { uuidMitra: string; saatSelesai: () => void }) {
    const formulir = useForm({ Periode: BulanLalu(), PotonganPajak: '0', DibayarPada: '', Catatan: '' });
    const Kirim = (e: FormEvent) => {
        e.preventDefault();
        formulir.post(`/mitra/${uuidMitra}/pencairan`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <DialogFormulir
            judul="Catat pencairan komisi"
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4" noValidate>
                <BidangTeks
                    label="Periode (TTTT-BB)"
                    kode
                    keterangan="Semua komisi tertunda sampai akhir bulan ini ikut dicairkan."
                    nilai={formulir.data.Periode}
                    saatBerubah={(nilai) => formulir.setData('Periode', nilai)}
                    galat={formulir.errors.Periode}
                    required
                />
                <BidangTeks
                    label="Potongan pajak (Rp)"
                    inputMode="decimal"
                    keterangan="Sesuai bukti potong PPh. Isi 0 bila tidak dipotong."
                    nilai={formulir.data.PotonganPajak}
                    saatBerubah={(nilai) => formulir.setData('PotonganPajak', nilai)}
                    galat={formulir.errors.PotonganPajak}
                    required
                />
                <BidangTanggal
                    label="Tanggal transfer"
                    nilai={formulir.data.DibayarPada}
                    saatBerubah={(nilai) => formulir.setData('DibayarPada', nilai)}
                    galat={formulir.errors.DibayarPada}
                    required
                />
                <BidangTeks
                    label="Catatan"
                    nilai={formulir.data.Catatan}
                    saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                    galat={formulir.errors.Catatan}
                />
                <DialogFooter className="sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan pencairan
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

function FormBatalKomisi({ komisi, saatSelesai }: { komisi: Komisi; saatSelesai: () => void }) {
    const formulir = useForm({ Alasan: '' });
    const Kirim = (e: FormEvent) => {
        e.preventDefault();
        formulir.post(`/mitra/komisi/${komisi.Uuid}/batal`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <DialogFormulir
            judul={`Batalkan komisi ${komisi.NomorTagihan}`}
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4" noValidate>
                <p className="text-teks-sekunder">
                    Komisi {FormatRupiah(komisi.Jumlah)} untuk {komisi.NamaTenant} tidak akan dicairkan. Pakai bila
                    tagihannya dikembalikan (refund) atau atribusinya keliru.
                </p>
                <BidangTeks
                    label="Alasan"
                    nilai={formulir.data.Alasan}
                    saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                    galat={formulir.errors.Alasan}
                    required
                />
                <DialogFooter className="sm:justify-start">
                    <Tombol type="submit" varian="bahaya" memproses={formulir.processing}>
                        Batalkan komisi
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Tutup
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}
