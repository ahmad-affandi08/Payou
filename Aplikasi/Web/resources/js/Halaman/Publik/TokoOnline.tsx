import { Head } from '@inertiajs/react';
import { MinusIcon, PlusIcon, ShoppingBagIcon, Trash2Icon, UserRoundIcon } from 'lucide-react';
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

    return (
        <main className="min-h-screen bg-latar text-teks-utama">
            <Head title={Toko?.Nama ?? 'Toko online'} />
            <header className="border-b border-garis bg-permukaan">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-5">
                    <div className="flex min-w-0 flex-col gap-2">
                        <p className="text-label text-brand">Toko online</p>
                        <JudulHalaman>{Toko?.Nama ?? 'Toko tidak ditemukan'}</JudulHalaman>
                        {Toko ? (
                            <p className="text-keterangan text-teks-sekunder">
                                {Toko.NamaOutlet}
                                {Toko.Alamat ? ` | ${Toko.Alamat}` : ''}
                            </p>
                        ) : null}
                        {Outlet.length > 1 ? (
                            <div className="max-w-xs">
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
                    <div className="flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-center">
                        {pembeli ? (
                            <a
                                href={`/${Slug}/akun`}
                                className="flex min-h-10 items-center gap-2 rounded-full border border-garis px-3 text-label font-medium"
                            >
                                <UserRoundIcon className="size-4" />
                                <span className="max-w-32 truncate">{pembeli.Nama}</span>
                            </a>
                        ) : Akun.Aktif ? (
                            <button
                                type="button"
                                onClick={() => AturDialogMasuk(true)}
                                className="flex min-h-10 items-center gap-2 rounded-full border border-garis px-3 text-label font-medium"
                            >
                                <UserRoundIcon className="size-4" />
                                Masuk
                            </button>
                        ) : null}
                        <div className="flex items-center gap-2 rounded-full bg-brand/10 px-3 py-2 text-label font-semibold text-brand">
                            <ShoppingBagIcon className="size-4" />
                            {baris.reduce((n, b) => n + b.Jumlah, 0)}
                        </div>
                    </div>
                </div>
            </header>
            <div className="mx-auto grid max-w-6xl gap-6 px-4 py-6 lg:grid-cols-[1fr_380px]">
                <section className="flex flex-col gap-4">
                    {!Aktif ? (
                        <Pemberitahuan jenis="peringatan">{PesanTutup ?? 'Toko online sedang tutup.'}</Pemberitahuan>
                    ) : Menu.Produk.length === 0 ? (
                        <Pemberitahuan jenis="info">Belum ada produk yang ditampilkan online.</Pemberitahuan>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {Menu.Produk.map((p) => {
                                const dipilih = pilihanProduk[p.Uuid] ?? { varian: '', pilihan: [] };
                                return (
                                    <article
                                        key={p.Uuid}
                                        className="overflow-hidden rounded-xl border border-garis bg-permukaan shadow-sm"
                                    >
                                        {p.UrlGambar ? (
                                            <img src={p.UrlGambar} alt="" className="h-40 w-full object-cover" />
                                        ) : (
                                            <div className="h-24 bg-latar" />
                                        )}
                                        <div className="flex flex-col gap-3 p-4">
                                            <div>
                                                <h2 className="text-subjudul font-semibold">{p.Nama}</h2>
                                                <p className="font-semibold text-brand">
                                                    Mulai {FormatRupiah(p.Harga)}
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
                                                <fieldset key={k.Uuid} className="flex flex-col gap-1">
                                                    <legend className="text-label font-medium">
                                                        {k.Nama} ({k.MinimalPilih}–{k.MaksimalPilih})
                                                    </legend>
                                                    {k.Pilihan.map((x) => (
                                                        <label
                                                            key={x.Uuid}
                                                            className="flex min-h-9 items-center gap-2 text-isi"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={dipilih.pilihan.includes(x.Uuid)}
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
                                                            {x.Nama}
                                                            {Number(x.Harga) > 0 ? ` (+${FormatRupiah(x.Harga)})` : ''}
                                                        </label>
                                                    ))}
                                                </fieldset>
                                            ))}
                                            <Tombol onClick={() => Tambah(p)}>Tambah</Tombol>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </section>
                <aside className="h-fit rounded-xl border border-garis bg-permukaan p-4 lg:sticky lg:top-4">
                    <h2 className="mb-3 text-subjudul font-semibold">Keranjang</h2>
                    {baris.length === 0 ? (
                        <p className="text-teks-sekunder">Keranjang masih kosong.</p>
                    ) : (
                        <form onSubmit={Pesan} className="flex flex-col gap-4">
                            <ul className="divide-y divide-garis">
                                {baris.map((b) => (
                                    <li key={b.Uuid} className="flex items-center justify-between gap-2 py-3">
                                        <span className="min-w-0 flex-1 truncate">
                                            {produk.get(b.UuidProduk)?.Nama}
                                        </span>
                                        <div className="flex items-center gap-1">
                                            <button
                                                type="button"
                                                aria-label="Kurangi"
                                                className="p-2"
                                                onClick={() => UbahJumlah(b.Uuid, -1)}
                                            >
                                                <MinusIcon className="size-4" />
                                            </button>
                                            <span className="w-6 text-center tabular-nums">{b.Jumlah}</span>
                                            <button
                                                type="button"
                                                aria-label="Tambah"
                                                className="p-2"
                                                onClick={() => UbahJumlah(b.Uuid, 1)}
                                            >
                                                <PlusIcon className="size-4" />
                                            </button>
                                            <button
                                                type="button"
                                                aria-label="Hapus"
                                                className="p-2 text-bahaya"
                                                onClick={() => AturBaris((x) => x.filter((z) => z.Uuid !== b.Uuid))}
                                            >
                                                <Trash2Icon className="size-4" />
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <fieldset className="flex flex-wrap gap-3">
                                <legend className="mb-1 text-label font-medium">Cara menerima</legend>
                                {Pemenuhan.AmbilSendiri ? (
                                    <label>
                                        <input
                                            type="radio"
                                            checked={jenis === 'AmbilSendiri'}
                                            onChange={() => AturJenis('AmbilSendiri')}
                                        />{' '}
                                        Ambil sendiri
                                    </label>
                                ) : null}
                                {Pemenuhan.Kirim ? (
                                    <label>
                                        <input
                                            type="radio"
                                            checked={jenis === 'Kirim'}
                                            onChange={() => AturJenis('Kirim')}
                                        />{' '}
                                        Dikirim
                                    </label>
                                ) : null}
                            </fieldset>
                            {jenis === 'Kirim' ? (
                                <>
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
                                </>
                            ) : null}
                            {pembeli ? (
                                <Pemberitahuan jenis="info">
                                    Pesanan tercatat di akun {pembeli.Nama} ({pembeli.NoHp})
                                    {pembeli.Tier ? ` | harga member ${pembeli.Tier} sudah dihitung` : ''}.
                                </Pemberitahuan>
                            ) : Akun.Aktif ? (
                                <p className="text-keterangan text-teks-sekunder">
                                    Sudah pernah belanja di sini?{' '}
                                    <button type="button" className="underline" onClick={() => AturDialogMasuk(true)}>
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
                            {opsiBayar.length > 1 ? (
                                <BidangPilihan
                                    label="Cara bayar"
                                    nilai={bayarBerlaku}
                                    opsi={opsiBayar}
                                    saatBerubah={(v) => AturBayar(v as typeof bayar)}
                                />
                            ) : null}
                            <p className="text-keterangan text-teks-sekunder">
                                {bayarBerlaku === 'QrisOnline'
                                    ? 'Setelah pesanan dikirim, Anda akan mendapat QRIS untuk dibayar sekarang. Pesanan diproses toko setelah pembayaran masuk.'
                                    : bayarBerlaku === 'Cod'
                                      ? 'Bayar tunai ke kurir saat barang tiba.'
                                      : 'Bayar di kasir saat mengambil pesanan.'}
                            </p>
                            <KotakCentang
                                label="Saya setuju data ini dipakai untuk memproses pesanan dan pengiriman."
                                nilai={setuju}
                                saatBerubah={AturSetuju}
                            />
                            {galat ? <Pemberitahuan jenis="bahaya">{galat}</Pemberitahuan> : null}
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
                                    <p className="text-keterangan text-sukses">
                                        Voucher {hasilBerlaku.Voucher.Kode} dipakai: {hasilBerlaku.Voucher.NamaPromo}
                                    </p>
                                ) : null}
                            </div>
                            {hasilBerlaku ? (
                                <dl className="flex flex-col gap-1 border-t border-garis pt-3 text-isi">
                                    <div className="flex justify-between">
                                        <dt>Subtotal</dt>
                                        <dd>{FormatRupiah(hasilBerlaku.Subtotal)}</dd>
                                    </div>
                                    {Number(hasilBerlaku.Diskon) > 0 ? (
                                        <div className="flex justify-between text-sukses">
                                            <dt>Diskon promo</dt>
                                            <dd>−{FormatRupiah(hasilBerlaku.Diskon)}</dd>
                                        </div>
                                    ) : null}
                                    {Number(hasilBerlaku.Ongkir) > 0 ? (
                                        <div className="flex justify-between">
                                            <dt>Ongkir</dt>
                                            <dd>{FormatRupiah(hasilBerlaku.Ongkir)}</dd>
                                        </div>
                                    ) : null}
                                    {Number(hasilBerlaku.DiskonOngkir) > 0 ? (
                                        <div className="flex justify-between text-sukses">
                                            <dt>
                                                {Number(hasilBerlaku.DiskonOngkir) === Number(hasilBerlaku.Ongkir)
                                                    ? 'Gratis ongkir'
                                                    : 'Diskon ongkir'}
                                            </dt>
                                            <dd>−{FormatRupiah(hasilBerlaku.DiskonOngkir)}</dd>
                                        </div>
                                    ) : null}
                                    <div className="flex justify-between text-subjudul font-semibold">
                                        <dt>Total</dt>
                                        <dd>{FormatRupiah(hasilBerlaku.Total)}</dd>
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
                            <Tombol type="submit" memproses={memproses} disabled={!hasilBerlaku || !setuju}>
                                Kirim pesanan
                            </Tombol>
                        </form>
                    )}
                </aside>
            </div>
            {dialogMasuk && Toko ? (
                <DialogMasukPembeli
                    slug={Slug}
                    namaToko={Toko.Nama}
                    saatMasuk={SaatMasuk}
                    saatTutup={() => AturDialogMasuk(false)}
                />
            ) : null}
        </main>
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
