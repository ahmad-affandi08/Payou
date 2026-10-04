import { Head } from '@inertiajs/react';
import {
    CheckIcon,
    ClockIcon,
    MapPinIcon,
    MinusIcon,
    PlusIcon,
    QrCodeIcon,
    ReceiptTextIcon,
    SearchIcon,
    ShoppingBagIcon,
    StoreIcon,
    TicketPercentIcon,
    Trash2Icon,
    TruckIcon,
    UserRoundIcon,
    UtensilsCrossedIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogMasukPembeli, {
    type AlamatPembeli,
    type HasilMasukPembeli,
    type ProfilPembeli,
} from '@/Komponen/TokoOnline/DialogMasukPembeli';
import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { BuatUlid } from '@/Pustaka/Ulid';

type Pilihan = { Uuid: string; Nama: string; Harga: string };
type Kelompok = { Uuid: string; Nama: string; MinimalPilih: number; MaksimalPilih: number; Pilihan: Pilihan[] };
type Produk = {
    Uuid: string;
    Nama: string;
    Harga: string;
    UrlGambar: string | null;
    UuidKategori: string | null;
    KelompokPilihan: Kelompok[];
    Varian?: { Uuid: string; Nama: string; Harga: string | null; Tersedia: boolean }[];
};
type Baris = {
    Uuid: string;
    UuidProduk: string;
    UuidVarian: string | null;
    Jumlah: number;
    Pilihan: string[];
    Catatan: string;
};
type Hasil = {
    Subtotal: string;
    Diskon: string;
    BiayaLayanan: string;
    Pajak: { Nama: string; Tarif: string; Jumlah: string }[];
    Ongkir: string;
    /** Potongan promo gratis ongkir (F-16c); yang dibayar = Ongkir − DiskonOngkir, sudah termasuk di Total. */
    DiskonOngkir: string;
    Total: string;
    Zona: { Nama: string; EstimasiHariMin: number; EstimasiHariMaks: number } | null;
    /** v3.46: voucher yang berlaku di perhitungan ini (diperiksa server). */
    Voucher?: { Kode: string; NamaPromo: string } | null;
};
type Props = {
    Aktif: boolean;
    Slug: string;
    Toko: { Nama: string; NamaOutlet: string; Alamat: string | null } | null;
    Outlet: { Uuid: string; Nama: string }[];
    OutletDipilih: string;
    Pemenuhan: { AmbilSendiri: boolean; Kirim: boolean };
    Pembayaran: { BayarSaatAmbil: boolean; Cod: boolean; QrisOnline: boolean };
    MinimalPesanan: string;
    PesanTutup: string | null;
    Menu: { Kategori: { Uuid: string; Nama: string }[]; Produk: Produk[] };
    /** F-17 bagian 3: akun pembeli opsional (masuk dengan kode WhatsApp). */
    Akun: { Aktif: boolean; Pelanggan: ProfilPembeli | null; AlamatTerakhir: AlamatPembeli | null };
};

async function Kirim<T>(url: string, badan: unknown): Promise<T> {
    const respons = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(badan),
    });
    const json = (await respons.json()) as { Galat?: { Pesan?: string } } & T;
    if (!respons.ok) throw new Error(json.Galat?.Pesan ?? 'Permintaan belum berhasil. Coba lagi.');
    return json;
}

export default function TokoOnline({
    Aktif,
    Slug,
    Toko,
    Outlet,
    OutletDipilih,
    Pemenuhan,
    Pembayaran,
    MinimalPesanan,
    PesanTutup,
    Menu,
    Akun,
}: Props) {
    const [pembeli, AturPembeli] = useState<ProfilPembeli | null>(Akun.Pelanggan);
    const [dialogMasuk, AturDialogMasuk] = useState(false);
    const [baris, AturBaris] = useState<Baris[]>([]);
    const [pilihanProduk, AturPilihanProduk] = useState<Record<string, { varian: string; pilihan: string[] }>>({});
    const [jenis, AturJenis] = useState<'AmbilSendiri' | 'Kirim'>(Pemenuhan.AmbilSendiri ? 'AmbilSendiri' : 'Kirim');
    const [kodePos, AturKodePos] = useState(Akun.AlamatTerakhir?.KodePos ?? '');
    const [bayar, AturBayar] = useState<'BayarSaatAmbil' | 'Cod' | 'QrisOnline'>(
        Pembayaran.QrisOnline ? 'QrisOnline' : Pemenuhan.AmbilSendiri ? 'BayarSaatAmbil' : 'Cod',
    );
    const [hasil, AturHasil] = useState<Hasil | null>(null);
    const [galat, AturGalat] = useState<string | null>(null);
    // v3.46: kode yang diketik, kode yang sedang dipakai di perhitungan, dan galat khusus voucher.
    const [isianVoucher, AturIsianVoucher] = useState('');
    const [voucher, AturVoucher] = useState('');
    const [galatVoucher, AturGalatVoucher] = useState<string | null>(null);
    const [memproses, AturMemproses] = useState(false);
    const [setuju, AturSetuju] = useState(false);
    const [pelanggan, AturPelanggan] = useState(() => ({
        NamaPelanggan: Akun.Pelanggan?.Nama ?? '',
        NoHp: Akun.Pelanggan?.NoHp ?? '',
        Email: Akun.Pelanggan?.Email ?? '',
        ...IsiAlamat(Akun.AlamatTerakhir),
        Catatan: '',
    }));
    const produk = useMemo(() => new Map(Menu.Produk.map((p) => [p.Uuid, p])), [Menu.Produk]);
    const hasilBerlaku = baris.length > 0 && (jenis !== 'Kirim' || kodePos.length === 5) ? hasil : null;
    // Bayar saat ambil hanya untuk ambil sendiri, COD hanya untuk kirim; QRIS berlaku untuk keduanya.
    const opsiBayar = useMemo(() => {
        const opsi: { Nilai: string; Label: string }[] = [];
        if (Pembayaran.QrisOnline) opsi.push({ Nilai: 'QrisOnline', Label: 'Bayar sekarang (QRIS)' });
        if (jenis === 'AmbilSendiri' && Pembayaran.BayarSaatAmbil)
            opsi.push({ Nilai: 'BayarSaatAmbil', Label: 'Bayar saat ambil' });
        if (jenis === 'Kirim' && Pembayaran.Cod) opsi.push({ Nilai: 'Cod', Label: 'Bayar di tempat (COD)' });
        return opsi;
    }, [Pembayaran, jenis]);

    // Pilihan yang tidak berlaku lagi (mis. ganti Ambil sendiri → Kirim) diturunkan ke opsi pertama, bukan
    // disinkronkan lewat efek: state tetap apa yang pelanggan pilih, tampilan & kiriman memakai yang berlaku.
    const bayarBerlaku = opsiBayar.some((o) => o.Nilai === bayar)
        ? bayar
        : ((opsiBayar[0]?.Nilai ?? bayar) as typeof bayar);

    useEffect(() => {
        if (baris.length === 0 || (jenis === 'Kirim' && kodePos.length !== 5)) {
            return;
        }
        const tunda = window.setTimeout(() => {
            void Kirim<Hasil>(`/${Slug}/keranjang/hitung`, {
                Outlet: OutletDipilih,
                JenisPemenuhan: jenis,
                KodePos: kodePos || null,
                KodeVoucher: voucher || null,
                Baris: baris,
            })
                .then((h) => {
                    AturHasil(h);
                    AturGalat(null);
                })
                .catch((e: unknown) => {
                    const pesan = e instanceof Error ? e.message : 'Gagal menghitung keranjang.';
                    // Voucher yang ditolak dilepas lalu keranjang dihitung ulang tanpa voucher.
                    if (voucher !== '') {
                        AturGalatVoucher(pesan);
                        AturVoucher('');
                        return;
                    }
                    AturHasil(null);
                    AturGalat(pesan);
                });
        }, 250);
        return () => window.clearTimeout(tunda);
        // Masuk/keluar mengubah harga (harga tier & promo pelanggan dihitung server), jadi keranjang dihitung ulang.
    }, [Slug, OutletDipilih, baris, jenis, kodePos, pembeli?.Uuid, voucher]);

    function PakaiVoucher() {
        const kode = isianVoucher.trim().toUpperCase();
        AturGalatVoucher(null);
        AturVoucher(kode);
    }

    function Tambah(p: Produk) {
        const dipilih = pilihanProduk[p.Uuid] ?? { varian: '', pilihan: [] };
        if ((p.Varian?.length ?? 0) > 0 && dipilih.varian === '') {
            AturGalat(`Pilih varian ${p.Nama}.`);
            return;
        }
        for (const k of p.KelompokPilihan) {
            const jumlah = k.Pilihan.filter((x) => dipilih.pilihan.includes(x.Uuid)).length;
            if (jumlah < k.MinimalPilih || jumlah > k.MaksimalPilih) {
                AturGalat(`Pilihan ${k.Nama} untuk ${p.Nama} belum sesuai.`);
                return;
            }
        }
        AturBaris((lama) => [
            ...lama,
            {
                Uuid: BuatUlid(),
                UuidProduk: p.Uuid,
                UuidVarian: dipilih.varian || null,
                Jumlah: 1,
                Pilihan: dipilih.pilihan,
                Catatan: '',
            },
        ]);
        AturPilihanProduk((lama) => ({ ...lama, [p.Uuid]: { varian: '', pilihan: [] } }));
        AturGalat(null);
    }

    function SaatMasuk(hasil: HasilMasukPembeli) {
        AturPembeli(hasil.Pelanggan);
        AturDialogMasuk(false);
        // Data akun mengisi yang masih kosong; ketikan pembeli tidak ditimpa.
        AturPelanggan((x) => {
            const alamat = IsiAlamat(hasil.AlamatTerakhir);
            return {
                ...x,
                NamaPelanggan: x.NamaPelanggan || hasil.Pelanggan.Nama,
                NoHp: hasil.Pelanggan.NoHp,
                Email: x.Email || (hasil.Pelanggan.Email ?? ''),
                ...(x.Alamat === '' ? alamat : {}),
            };
        });
        if (kodePos === '' && hasil.AlamatTerakhir?.KodePos) AturKodePos(hasil.AlamatTerakhir.KodePos);
    }

    function UbahJumlah(uuid: string, selisih: number) {
        AturBaris((lama) => lama.map((b) => (b.Uuid === uuid ? { ...b, Jumlah: Math.max(1, b.Jumlah + selisih) } : b)));
    }

    async function Pesan(e: FormEvent) {
        e.preventDefault();
        AturMemproses(true);
        AturGalat(null);
        try {
            const respons = await Kirim<{ UrlStatus: string }>(`/${Slug}/pesan`, {
                Uuid: BuatUlid(),
                ...pelanggan,
                Outlet: OutletDipilih,
                JenisPemenuhan: jenis,
                MetodePembayaran: bayarBerlaku,
                KodePos: kodePos || null,
                KodeVoucher: hasilBerlaku?.Voucher?.Kode ?? null,
                SetujuDataPribadi: setuju,
                Baris: baris,
            });
            window.location.assign(respons.UrlStatus);
        } catch (e2: unknown) {
            AturGalat(e2 instanceof Error ? e2.message : 'Pesanan belum berhasil dikirim.');
            AturMemproses(false);
        }
    }

    const [kategoriAktif, AturKategoriAktif] = useState('');
    const [cari, AturCari] = useState('');
    const jumlahItem = baris.reduce((n, b) => n + b.Jumlah, 0);
    const jumlahPerProduk = useMemo(() => {
        const peta = new Map<string, number>();
        for (const b of baris) {
            peta.set(b.UuidProduk, (peta.get(b.UuidProduk) ?? 0) + b.Jumlah);
        }
        return peta;
    }, [baris]);
    const produkTampil = useMemo(() => {
        const kata = cari.trim().toLowerCase();

        return Menu.Produk.filter(
            (p) =>
                (kategoriAktif === '' || p.UuidKategori === kategoriAktif) &&
                (kata === '' || p.Nama.toLowerCase().includes(kata)),
        );
    }, [Menu.Produk, kategoriAktif, cari]);
    const opsiPemenuhan = [
        { nilai: 'AmbilSendiri' as const, label: 'Ambil sendiri', ikon: StoreIcon, ada: Pemenuhan.AmbilSendiri },
        { nilai: 'Kirim' as const, label: 'Dikirim', ikon: TruckIcon, ada: Pemenuhan.Kirim },
    ].filter((o) => o.ada);

    return (
        <div className="min-h-dvh bg-latar text-teks-utama">
            <Head title={Toko?.Nama ?? 'Toko online'} />

            <header className="bg-brand-gelap text-permukaan">
                <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 pt-5 pb-7">
                    <div className="flex items-center justify-between gap-3">
                        <span className="inline-flex items-center gap-1.5 text-label font-semibold text-brand-gelap-teks">
                            <ShoppingBagIcon aria-hidden="true" className="size-4" />
                            Toko online
                        </span>
                        <div className="flex items-center gap-2">
                            {pembeli ? (
                                <a
                                    href={`/${Slug}/akun`}
                                    className="flex min-h-10 items-center gap-2 rounded-full bg-brand-gelap-sorot px-3 text-label font-semibold text-permukaan"
                                >
                                    <UserRoundIcon aria-hidden="true" className="size-4" />
                                    <span className="max-w-32 truncate">{pembeli.Nama}</span>
                                </a>
                            ) : Akun.Aktif ? (
                                <button
                                    type="button"
                                    onClick={() => AturDialogMasuk(true)}
                                    className="flex min-h-10 items-center gap-2 rounded-full bg-brand-gelap-sorot px-3 text-label font-semibold text-permukaan"
                                >
                                    <UserRoundIcon aria-hidden="true" className="size-4" />
                                    Masuk
                                </button>
                            ) : null}
                            <a
                                href="#keranjang"
                                aria-label={`Keranjang, ${String(jumlahItem)} item`}
                                className="relative flex size-10 items-center justify-center rounded-full bg-permukaan text-brand-gelap"
                            >
                                <ShoppingBagIcon aria-hidden="true" className="size-5" />
                                {jumlahItem > 0 ? (
                                    <span className="absolute -top-1 -right-1 grid min-w-5 place-items-center rounded-full bg-aksen px-1 text-keterangan font-semibold text-teks-utama tabular-nums">
                                        {jumlahItem}
                                    </span>
                                ) : null}
                            </a>
                        </div>
                    </div>
                    <div className="flex flex-col gap-2">
                        <JudulHalaman className="text-permukaan">{Toko?.Nama ?? 'Toko tidak ditemukan'}</JudulHalaman>
                        {Toko ? (
                            <p className="inline-flex items-start gap-1.5 text-isi text-brand-gelap-teks">
                                <MapPinIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    {Toko.NamaOutlet}
                                    {Toko.Alamat ? ` | ${Toko.Alamat}` : ''}
                                </span>
                            </p>
                        ) : null}
                    </div>
                    {Toko ? (
                        <ul className="flex flex-wrap gap-2 text-keterangan font-semibold">
                            <li
                                className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 ${Aktif ? 'bg-sukses text-permukaan' : 'bg-peringatan text-permukaan'}`}
                            >
                                <ClockIcon aria-hidden="true" className="size-3.5" />
                                {Aktif ? 'Terima pesanan' : 'Sedang tutup'}
                            </li>
                            {Pemenuhan.AmbilSendiri ? (
                                <li className="inline-flex items-center gap-1.5 rounded-full bg-brand-gelap-sorot px-2.5 py-1">
                                    <StoreIcon aria-hidden="true" className="size-3.5" />
                                    Ambil sendiri
                                </li>
                            ) : null}
                            {Pemenuhan.Kirim ? (
                                <li className="inline-flex items-center gap-1.5 rounded-full bg-brand-gelap-sorot px-2.5 py-1">
                                    <TruckIcon aria-hidden="true" className="size-3.5" />
                                    Dikirim
                                </li>
                            ) : null}
                            {Pembayaran.QrisOnline ? (
                                <li className="inline-flex items-center gap-1.5 rounded-full bg-brand-gelap-sorot px-2.5 py-1">
                                    <QrCodeIcon aria-hidden="true" className="size-3.5" />
                                    Bayar QRIS
                                </li>
                            ) : null}
                            {Number(MinimalPesanan) > 0 ? (
                                <li className="inline-flex items-center gap-1.5 rounded-full bg-brand-gelap-sorot px-2.5 py-1">
                                    <ReceiptTextIcon aria-hidden="true" className="size-3.5" />
                                    Min. {FormatRupiah(MinimalPesanan)}
                                </li>
                            ) : null}
                        </ul>
                    ) : null}
                    {Outlet.length > 1 ? (
                        <div className="max-w-xs rounded-panel bg-permukaan p-2 text-teks-utama">
                            <BidangPilihan
                                label="Belanja dari outlet"
                                nilai={OutletDipilih}
                                opsi={Outlet.map((o) => ({ Nilai: o.Uuid, Label: o.Nama }))}
                                saatBerubah={(nilai) =>
                                    window.location.assign(`/${Slug}?outlet=${encodeURIComponent(nilai)}`)
                                }
                            />
                        </div>
                    ) : null}
                </div>
            </header>

            {Aktif && Menu.Produk.length > 0 ? (
                <nav aria-label="Kategori menu" className="sticky top-0 z-10 border-b border-garis bg-latar">
                    <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-3">
                        <label className="relative block">
                            <span className="sr-only">Cari menu</span>
                            <SearchIcon
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-teks-sekunder"
                            />
                            <input
                                type="search"
                                value={cari}
                                onChange={(e) => AturCari(e.target.value)}
                                placeholder="Cari menu"
                                className="h-11 w-full rounded-full border border-garis-input bg-permukaan pr-4 pl-10 text-isi text-teks-utama placeholder:text-teks-sekunder focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
                            />
                        </label>
                        {Menu.Kategori.length > 0 ? (
                            <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                                {[{ Uuid: '', Nama: 'Semua' }, ...Menu.Kategori].map((k) => (
                                    <button
                                        key={k.Uuid || 'semua'}
                                        type="button"
                                        aria-pressed={kategoriAktif === k.Uuid}
                                        onClick={() => AturKategoriAktif(k.Uuid)}
                                        className={`min-h-10 shrink-0 rounded-full border px-4 text-label font-semibold whitespace-nowrap ${
                                            kategoriAktif === k.Uuid
                                                ? 'border-brand bg-brand text-brand-teks'
                                                : 'border-garis bg-permukaan text-teks-utama'
                                        }`}
                                    >
                                        {k.Nama}
                                    </button>
                                ))}
                            </div>
                        ) : null}
                    </div>
                </nav>
            ) : null}

            <div className="mx-auto grid max-w-6xl gap-6 px-4 py-6 pb-28 lg:grid-cols-[1fr_400px] lg:pb-8">
                <section className="flex min-w-0 flex-col gap-4" aria-label="Daftar menu">
                    {!Aktif ? (
                        <Pemberitahuan jenis="peringatan">{PesanTutup ?? 'Toko online sedang tutup.'}</Pemberitahuan>
                    ) : Menu.Produk.length === 0 ? (
                        <Pemberitahuan jenis="info">Belum ada produk yang ditampilkan online.</Pemberitahuan>
                    ) : produkTampil.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 rounded-panel border border-garis bg-permukaan p-8 text-center">
                            <SearchIcon aria-hidden="true" className="size-8 text-teks-sekunder" />
                            <p className="text-isi text-teks-sekunder">
                                Tidak ada menu yang cocok. Coba kata lain atau pilih kategori Semua.
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-2">
                            {produkTampil.map((p) => {
                                const dipilih = pilihanProduk[p.Uuid] ?? { varian: '', pilihan: [] };
                                const diKeranjang = jumlahPerProduk.get(p.Uuid) ?? 0;
                                const perluPilih = (p.Varian?.length ?? 0) > 0 || p.KelompokPilihan.length > 0;

                                return (
                                    <article
                                        key={p.Uuid}
                                        className="flex flex-col overflow-hidden rounded-panel border border-garis bg-permukaan"
                                    >
                                        <div className="relative aspect-[4/3] w-full bg-brand-lembut">
                                            {p.UrlGambar ? (
                                                <img
                                                    src={p.UrlGambar}
                                                    alt=""
                                                    loading="lazy"
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <div className="grid size-full place-items-center text-brand">
                                                    <UtensilsCrossedIcon aria-hidden="true" className="size-10" />
                                                </div>
                                            )}
                                            {diKeranjang > 0 ? (
                                                <span className="absolute top-2 right-2 rounded-full bg-brand px-2.5 py-1 text-keterangan font-semibold text-brand-teks tabular-nums">
                                                    {diKeranjang} di keranjang
                                                </span>
                                            ) : null}
                                        </div>
                                        <div className="flex flex-1 flex-col gap-3 p-4">
                                            <div className="flex flex-col gap-1">
                                                <h2 className="text-subjudul font-semibold text-teks-utama">
                                                    {p.Nama}
                                                </h2>
                                                <p className="text-subjudul font-semibold text-brand tabular-nums">
                                                    {(p.Varian?.length ?? 0) > 0 ? 'Mulai ' : ''}
                                                    {FormatRupiah(p.Harga)}
                                                </p>
                                            </div>
                                            {(p.Varian?.length ?? 0) > 0 ? (
                                                <BidangPilihan
                                                    label="Varian"
                                                    nilai={dipilih.varian}
                                                    opsi={(p.Varian ?? [])
                                                        .filter((v) => v.Tersedia)
                                                        .map((v) => ({
                                                            Nilai: v.Uuid,
                                                            Label: `${v.Nama}${v.Harga ? ` | ${FormatRupiah(v.Harga)}` : ''}`,
                                                        }))}
                                                    kosong="Pilih varian"
                                                    saatBerubah={(nilai) =>
                                                        AturPilihanProduk((x) => ({
                                                            ...x,
                                                            [p.Uuid]: { ...dipilih, varian: nilai },
                                                        }))
                                                    }
                                                />
                                            ) : null}
                                            {p.KelompokPilihan.map((k) => (
                                                <fieldset key={k.Uuid} className="flex flex-col gap-2">
                                                    <legend className="text-label font-semibold text-teks-utama">
                                                        {k.Nama}
                                                        <span className="font-normal text-teks-sekunder">
                                                            {' '}
                                                            (pilih {k.MinimalPilih}–{k.MaksimalPilih})
                                                        </span>
                                                    </legend>
                                                    <div className="flex flex-wrap gap-2">
                                                        {k.Pilihan.map((x) => {
                                                            const aktif = dipilih.pilihan.includes(x.Uuid);

                                                            return (
                                                                <label
                                                                    key={x.Uuid}
                                                                    className={`flex min-h-10 cursor-pointer items-center gap-2 rounded-full border px-3 text-label has-focus-visible:ring-2 has-focus-visible:ring-brand ${
                                                                        aktif
                                                                            ? 'border-brand bg-brand-lembut font-semibold text-teks-utama'
                                                                            : 'border-garis text-teks-utama'
                                                                    }`}
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        className="sr-only"
                                                                        checked={aktif}
                                                                        onChange={(e) =>
                                                                            AturPilihanProduk((lama) => ({
                                                                                ...lama,
                                                                                [p.Uuid]: {
                                                                                    ...dipilih,
                                                                                    pilihan: e.target.checked
                                                                                        ? [...dipilih.pilihan, x.Uuid]
                                                                                        : dipilih.pilihan.filter(
                                                                                              (u) => u !== x.Uuid,
                                                                                          ),
                                                                                },
                                                                            }))
                                                                        }
                                                                    />
                                                                    {aktif ? (
                                                                        <CheckIcon
                                                                            aria-hidden="true"
                                                                            className="size-4 text-brand"
                                                                        />
                                                                    ) : null}
                                                                    {x.Nama}
                                                                    {Number(x.Harga) > 0
                                                                        ? ` (+${FormatRupiah(x.Harga)})`
                                                                        : ''}
                                                                </label>
                                                            );
                                                        })}
                                                    </div>
                                                </fieldset>
                                            ))}
                                            <div className="mt-auto">
                                                <Tombol
                                                    varian={perluPilih ? 'utama' : 'sekunder'}
                                                    onClick={() => Tambah(p)}
                                                >
                                                    <PlusIcon aria-hidden="true" className="size-4" />
                                                    Tambah
                                                </Tombol>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </section>

                <aside
                    id="keranjang"
                    aria-label="Keranjang"
                    className="h-fit scroll-mt-4 rounded-panel border border-garis bg-permukaan p-4 lg:sticky lg:top-24"
                >
                    <div className="mb-4 flex items-center justify-between gap-2">
                        <h2 className="text-judul font-semibold text-teks-utama">Keranjang</h2>
                        {jumlahItem > 0 ? (
                            <span className="rounded-full bg-brand-lembut px-2.5 py-1 text-keterangan font-semibold text-brand tabular-nums">
                                {jumlahItem} item
                            </span>
                        ) : null}
                    </div>
                    {baris.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 py-6 text-center">
                            <span className="grid size-14 place-items-center rounded-full bg-brand-lembut text-brand">
                                <ShoppingBagIcon aria-hidden="true" className="size-7" />
                            </span>
                            <p className="text-isi font-semibold text-teks-utama">Keranjang masih kosong</p>
                            <p className="text-keterangan text-teks-sekunder">
                                Pilih menu di sebelah kiri, lalu tekan Tambah.
                            </p>
                        </div>
                    ) : (
                        <form onSubmit={Pesan} className="flex flex-col gap-5">
                            <ul className="divide-y divide-garis rounded-panel border border-garis">
                                {baris.map((b) => {
                                    const barisProduk = produk.get(b.UuidProduk);
                                    const rincian = [
                                        barisProduk?.Varian?.find((v) => v.Uuid === b.UuidVarian)?.Nama,
                                        ...(barisProduk?.KelompokPilihan.flatMap((k) =>
                                            k.Pilihan.filter((x) => b.Pilihan.includes(x.Uuid)).map((x) => x.Nama),
                                        ) ?? []),
                                    ].filter((x): x is string => Boolean(x));

                                    return (
                                        <li key={b.Uuid} className="flex items-center gap-3 p-3">
                                            <span className="flex min-w-0 flex-1 flex-col">
                                                <span className="truncate text-isi font-semibold text-teks-utama">
                                                    {barisProduk?.Nama}
                                                </span>
                                                {rincian.length > 0 ? (
                                                    <span className="truncate text-keterangan text-teks-sekunder">
                                                        {rincian.join(', ')}
                                                    </span>
                                                ) : null}
                                            </span>
                                            <div className="flex shrink-0 items-center gap-1">
                                                <div className="flex items-center rounded-full border border-garis">
                                                    <button
                                                        type="button"
                                                        aria-label="Kurangi jumlah"
                                                        className="grid size-10 place-items-center rounded-full"
                                                        onClick={() => UbahJumlah(b.Uuid, -1)}
                                                    >
                                                        <MinusIcon aria-hidden="true" className="size-4" />
                                                    </button>
                                                    <span className="w-6 text-center text-isi font-semibold tabular-nums">
                                                        {b.Jumlah}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        aria-label="Tambah jumlah"
                                                        className="grid size-10 place-items-center rounded-full"
                                                        onClick={() => UbahJumlah(b.Uuid, 1)}
                                                    >
                                                        <PlusIcon aria-hidden="true" className="size-4" />
                                                    </button>
                                                </div>
                                                <button
                                                    type="button"
                                                    aria-label="Hapus"
                                                    className="grid size-10 place-items-center rounded-full text-bahaya"
                                                    onClick={() => AturBaris((x) => x.filter((z) => z.Uuid !== b.Uuid))}
                                                >
                                                    <Trash2Icon aria-hidden="true" className="size-4" />
                                                </button>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>

                            <fieldset className="flex flex-col gap-2">
                                <legend className="mb-1 text-label font-semibold text-teks-utama">Cara menerima</legend>
                                <div className="grid grid-cols-2 gap-2">
                                    {opsiPemenuhan.map((o) => (
                                        <label
                                            key={o.nilai}
                                            className={`flex min-h-14 cursor-pointer items-center gap-2 rounded-panel border p-3 text-isi font-semibold has-focus-visible:ring-2 has-focus-visible:ring-brand ${
                                                jenis === o.nilai
                                                    ? 'border-brand bg-brand-lembut text-teks-utama'
                                                    : 'border-garis text-teks-utama'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="cara-menerima"
                                                className="sr-only"
                                                checked={jenis === o.nilai}
                                                onChange={() => AturJenis(o.nilai)}
                                            />
                                            <o.ikon
                                                aria-hidden="true"
                                                className={`size-5 shrink-0 ${jenis === o.nilai ? 'text-brand' : 'text-teks-sekunder'}`}
                                            />
                                            {o.label}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                            {jenis === 'Kirim' ? (
                                <div className="flex flex-col gap-3">
                                    <BidangTeks
                                        label="Kode pos"
                                        nilai={kodePos}
                                        saatBerubah={AturKodePos}
                                        inputMode="numeric"
                                        maxLength={5}
                                        required
                                    />
                                    <BidangTeksPanjang
                                        label="Alamat lengkap"
                                        nilai={pelanggan.Alamat}
                                        saatBerubah={(v) => AturPelanggan((x) => ({ ...x, Alamat: v }))}
                                        required
                                    />
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <BidangTeks
                                            label="Kelurahan"
                                            nilai={pelanggan.Kelurahan}
                                            saatBerubah={(v) => AturPelanggan((x) => ({ ...x, Kelurahan: v }))}
                                            required
                                        />
                                        <BidangTeks
                                            label="Kecamatan"
                                            nilai={pelanggan.Kecamatan}
                                            saatBerubah={(v) => AturPelanggan((x) => ({ ...x, Kecamatan: v }))}
                                            required
                                        />
                                        <BidangTeks
                                            label="Kota/kabupaten"
                                            nilai={pelanggan.Kota}
                                            saatBerubah={(v) => AturPelanggan((x) => ({ ...x, Kota: v }))}
                                            required
                                        />
                                        <BidangTeks
                                            label="Provinsi"
                                            nilai={pelanggan.Provinsi}
                                            saatBerubah={(v) => AturPelanggan((x) => ({ ...x, Provinsi: v }))}
                                            required
                                        />
                                    </div>
                                </div>
                            ) : null}

                            <div className="flex flex-col gap-3">
                                <h3 className="text-label font-semibold text-teks-utama">Data penerima</h3>
                                {pembeli ? (
                                    <Pemberitahuan jenis="info">
                                        Pesanan tercatat di akun {pembeli.Nama} ({pembeli.NoHp})
                                        {pembeli.Tier ? ` | harga member ${pembeli.Tier} sudah dihitung` : ''}.
                                    </Pemberitahuan>
                                ) : Akun.Aktif ? (
                                    <p className="rounded-panel bg-brand-lembut p-3 text-keterangan text-teks-sekunder">
                                        Sudah pernah belanja di sini?{' '}
                                        <button
                                            type="button"
                                            className="font-semibold text-brand underline"
                                            onClick={() => AturDialogMasuk(true)}
                                        >
                                            Masuk dengan WhatsApp
                                        </button>{' '}
                                        untuk mengisi data otomatis dan mengumpulkan poin. Tanpa masuk juga bisa.
                                    </p>
                                ) : null}
                                <BidangTeks
                                    label={pembeli ? 'Nama penerima' : 'Nama'}
                                    nilai={pelanggan.NamaPelanggan}
                                    saatBerubah={(v) => AturPelanggan((x) => ({ ...x, NamaPelanggan: v }))}
                                    required
                                />
                                {pembeli ? null : (
                                    <BidangTeks
                                        label="Nomor WhatsApp"
                                        nilai={pelanggan.NoHp}
                                        saatBerubah={(v) => AturPelanggan((x) => ({ ...x, NoHp: v }))}
                                        inputMode="tel"
                                        required
                                    />
                                )}
                                <BidangTeks
                                    label="Email (opsional)"
                                    nilai={pelanggan.Email}
                                    saatBerubah={(v) => AturPelanggan((x) => ({ ...x, Email: v }))}
                                    jenis="email"
                                />
                                <BidangTeksPanjang
                                    label="Catatan (opsional)"
                                    nilai={pelanggan.Catatan}
                                    saatBerubah={(v) => AturPelanggan((x) => ({ ...x, Catatan: v }))}
                                    baris={3}
                                    maksimal={500}
                                />
                            </div>

                            <div className="flex flex-col gap-3">
                                <h3 className="text-label font-semibold text-teks-utama">Pembayaran</h3>
                                {opsiBayar.length > 1 ? (
                                    <BidangPilihan
                                        label="Cara bayar"
                                        nilai={bayarBerlaku}
                                        opsi={opsiBayar}
                                        saatBerubah={(v) => AturBayar(v as typeof bayar)}
                                    />
                                ) : null}
                                <p className="flex items-start gap-2 rounded-panel bg-permukaan-redup p-3 text-keterangan text-teks-sekunder">
                                    <QrCodeIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand" />
                                    {bayarBerlaku === 'QrisOnline'
                                        ? 'Setelah pesanan dikirim, Anda akan mendapat QRIS untuk dibayar sekarang. Pesanan diproses toko setelah pembayaran masuk.'
                                        : bayarBerlaku === 'Cod'
                                          ? 'Bayar tunai ke kurir saat barang tiba.'
                                          : 'Bayar di kasir saat mengambil pesanan.'}
                                </p>
                                <div className="flex flex-col gap-1">
                                    <div className="flex items-end gap-2">
                                        <div className="min-w-0 flex-1">
                                            <BidangTeks
                                                label="Kode voucher (opsional)"
                                                nilai={isianVoucher}
                                                saatBerubah={AturIsianVoucher}
                                                galat={galatVoucher ?? undefined}
                                                kode
                                            />
                                        </div>
                                        {hasilBerlaku?.Voucher ? (
                                            <Tombol
                                                varian="sekunder"
                                                onClick={() => {
                                                    AturVoucher('');
                                                    AturIsianVoucher('');
                                                }}
                                            >
                                                Lepas
                                            </Tombol>
                                        ) : (
                                            <Tombol
                                                varian="sekunder"
                                                disabled={isianVoucher.trim() === '' || baris.length === 0}
                                                onClick={PakaiVoucher}
                                            >
                                                Pakai
                                            </Tombol>
                                        )}
                                    </div>
                                    {hasilBerlaku?.Voucher ? (
                                        <p className="inline-flex items-center gap-1.5 text-keterangan text-sukses">
                                            <TicketPercentIcon aria-hidden="true" className="size-4" />
                                            Voucher {hasilBerlaku.Voucher.Kode} dipakai:{' '}
                                            {hasilBerlaku.Voucher.NamaPromo}
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            <KotakCentang
                                label="Saya setuju data ini dipakai untuk memproses pesanan dan pengiriman."
                                nilai={setuju}
                                saatBerubah={AturSetuju}
                            />
                            {galat ? <Pemberitahuan jenis="bahaya">{galat}</Pemberitahuan> : null}

                            {hasilBerlaku ? (
                                <dl className="flex flex-col gap-1.5 rounded-panel bg-permukaan-redup p-3 text-isi">
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-teks-sekunder">Subtotal</dt>
                                        <dd className="tabular-nums">{FormatRupiah(hasilBerlaku.Subtotal)}</dd>
                                    </div>
                                    {Number(hasilBerlaku.Diskon) > 0 ? (
                                        <div className="flex justify-between gap-3 text-sukses">
                                            <dt>Diskon promo</dt>
                                            <dd className="tabular-nums">−{FormatRupiah(hasilBerlaku.Diskon)}</dd>
                                        </div>
                                    ) : null}
                                    {Number(hasilBerlaku.Ongkir) > 0 ? (
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-teks-sekunder">Ongkir</dt>
                                            <dd className="tabular-nums">{FormatRupiah(hasilBerlaku.Ongkir)}</dd>
                                        </div>
                                    ) : null}
                                    {Number(hasilBerlaku.DiskonOngkir) > 0 ? (
                                        <div className="flex justify-between gap-3 text-sukses">
                                            <dt>
                                                {Number(hasilBerlaku.DiskonOngkir) === Number(hasilBerlaku.Ongkir)
                                                    ? 'Gratis ongkir'
                                                    : 'Diskon ongkir'}
                                            </dt>
                                            <dd className="tabular-nums">−{FormatRupiah(hasilBerlaku.DiskonOngkir)}</dd>
                                        </div>
                                    ) : null}
                                    <div className="flex justify-between gap-3 border-t border-garis pt-2 text-subjudul font-semibold">
                                        <dt>Total</dt>
                                        <dd className="tabular-nums">{FormatRupiah(hasilBerlaku.Total)}</dd>
                                    </div>
                                    {hasilBerlaku.Zona ? (
                                        <p className="text-keterangan text-teks-sekunder">
                                            {hasilBerlaku.Zona.Nama} | perkiraan {hasilBerlaku.Zona.EstimasiHariMin}–
                                            {hasilBerlaku.Zona.EstimasiHariMaks} hari
                                        </p>
                                    ) : null}
                                </dl>
                            ) : (
                                <p className="text-keterangan text-teks-sekunder">
                                    Minimal belanja {FormatRupiah(MinimalPesanan)}. Total dikunci oleh server sebelum
                                    pesanan dikirim.
                                </p>
                            )}
                            <Tombol
                                type="submit"
                                ukuran="besar"
                                memproses={memproses}
                                disabled={!hasilBerlaku || !setuju}
                            >
                                Kirim pesanan
                            </Tombol>
                        </form>
                    )}
                </aside>
            </div>

            {Aktif && jumlahItem > 0 ? (
                <a
                    href="#keranjang"
                    className="tepi-bawah-aman fixed inset-x-0 bottom-0 z-20 border-t border-garis bg-permukaan px-4 py-3 lg:hidden"
                >
                    <span className="mx-auto flex max-w-6xl items-center justify-between gap-3 rounded-full bg-brand px-5 py-3 text-brand-teks">
                        <span className="inline-flex items-center gap-2 text-isi font-semibold">
                            <ShoppingBagIcon aria-hidden="true" className="size-5" />
                            Lihat keranjang | {jumlahItem} item
                        </span>
                        <span className="text-isi font-semibold tabular-nums">
                            {hasilBerlaku ? FormatRupiah(hasilBerlaku.Total) : ''}
                        </span>
                    </span>
                </a>
            ) : null}

            {dialogMasuk && Toko ? (
                <DialogMasukPembeli
                    slug={Slug}
                    namaToko={Toko.Nama}
                    saatMasuk={SaatMasuk}
                    saatTutup={() => AturDialogMasuk(false)}
                />
            ) : null}
        </div>
    );
}

function IsiAlamat(a: AlamatPembeli | null) {
    return {
        Alamat: a?.Alamat ?? '',
        Kelurahan: a?.Kelurahan ?? '',
        Kecamatan: a?.Kecamatan ?? '',
        Kota: a?.Kota ?? '',
        Provinsi: a?.Provinsi ?? '',
    };
}
