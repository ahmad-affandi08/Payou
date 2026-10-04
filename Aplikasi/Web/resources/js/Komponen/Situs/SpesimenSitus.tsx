import { cn } from '@/Komponen/Ui/utils';

/**
 * Spesimen keluaran PAYOU untuk situs pemasaran (D-25, D-39): tangkapan layar asli aplikasi Kasir & Pemilik
 * (berkas statis di `public/situs/produk`, jadi bisa dipakai isi bawaan), serta struk dan jurnal yang benar-benar
 * dihasilkan produk. Dipakai sebagai visual hero dan blok gambar-teks bila konsol belum mengunggah gambar.
 *
 * Kenapa bukan ilustrasi:
 * - §17.6.4 melarang ilustrasi dekoratif, dan ini bukan hiasan melainkan **contoh keluaran produk**;
 * - gambar unggahan konsol tidak bisa dipakai isi bawaan (`GambarSitus` dirujuk lewat Uuid), maka tangkapan layar
 *   aplikasi disimpan sebagai berkas statis dan dipilih lewat nama spesimen;
 * - struk & jurnal murni tipografi; tangkapan layar dibingkai tepi gelap tanpa bayangan maupun gradien (§17.6.11).
 *
 * Angkanya contoh tetap (bukan klaim tentang usaha siapa pun) dan memakai font Mono + angka tabular seperti
 * struk sungguhan. Pembaca layar menerima satu kalimat lewat `role="img"`, bukan deretan angka.
 */

type PropsSpesimen = { className?: string | undefined };

const BARIS_STRUK = [
    { Nama: 'Kopi susu gula aren', Qty: '2', Harga: '36.000' },
    { Nama: 'Croissant butter', Qty: '1', Harga: '28.000' },
    { Nama: 'Air mineral 600ml', Qty: '1', Harga: '6.000' },
] as const;

/**
 * Struk termal: kop outlet, baris penjualan, PPN, total, dan penanda bahwa transaksi ini dibuat saat offline
 * lalu menunggu terkirim — pembeda utama PAYOU, ditandai dengan `Aksen` berteks `TeksUtama` (8,98:1).
 */
export function SpesimenStruk({ className }: PropsSpesimen) {
    return (
        <div
            role="img"
            aria-label="Contoh struk PAYOU: tiga baris penjualan, PPN, total Rp 77.000, dan penanda transaksi dibuat saat offline yang menunggu terkirim."
            className={cn(
                'text-isi w-full max-w-sm rounded-panel border border-garis bg-permukaan p-6 font-mono text-teks-utama tabular-nums',
                className,
            )}
        >
            <div className="flex flex-col items-center gap-0.5 border-b border-dashed border-garis pb-4 text-center">
                <p className="font-semibold">KOPI SENJA</p>
                <p className="text-keterangan text-teks-sekunder">Jl. Cendana 12, Yogyakarta</p>
                <p className="text-keterangan text-teks-sekunder">PJL/20260927/KSR1/0043</p>
            </div>
            <ul className="flex flex-col gap-2 border-b border-dashed border-garis py-4">
                {BARIS_STRUK.map((b) => (
                    <li key={b.Nama} className="flex items-start justify-between gap-3">
                        <span className="flex-1">
                            {b.Qty}× {b.Nama}
                        </span>
                        <span className="text-right">{b.Harga}</span>
                    </li>
                ))}
            </ul>
            <dl className="flex flex-col gap-1 border-b border-dashed border-garis py-4">
                <div className="flex justify-between gap-3">
                    <dt className="text-teks-sekunder">Subtotal</dt>
                    <dd>70.000</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="text-teks-sekunder">PPN 11%</dt>
                    <dd>7.000</dd>
                </div>
                <div className="flex justify-between gap-3 font-semibold">
                    <dt>TOTAL</dt>
                    <dd>Rp 77.000</dd>
                </div>
            </dl>
            <div className="flex flex-col gap-3 pt-4">
                <p className="flex justify-between gap-3 text-keterangan text-teks-sekunder">
                    <span>Tunai</span>
                    <span>100.000</span>
                </p>
                <p className="flex justify-between gap-3 text-keterangan text-teks-sekunder">
                    <span>Kembali</span>
                    <span>23.000</span>
                </p>
                {/* Penanda offline: warna tidak menanggung makna sendiri, teksnya yang menjelaskan. */}
                <p className="text-keterangan rounded-kontrol bg-aksen px-3 py-2 text-center font-semibold text-teks-utama">
                    Dibuat offline · menunggu terkirim
                </p>
            </div>
        </div>
    );
}

const BARIS_JURNAL = [
    { Akun: 'Kas', Kode: '1-1100', Debit: '77.000', Kredit: null },
    { Akun: 'Pendapatan Penjualan', Kode: '4-1000', Debit: null, Kredit: '70.000' },
    { Akun: 'PPN Keluaran', Kode: '2-1300', Debit: null, Kredit: '7.000' },
] as const;

/**
 * Jurnal otomatis dari struk di atas: memperlihatkan invariant Σ debit = Σ kredit, yang memang diuji di
 * test invariant keuangan. Ini pembeda yang sulit ditunjukkan lewat tangkapan layar.
 */
export function SpesimenJurnal({ className }: PropsSpesimen) {
    return (
        <div
            role="img"
            aria-label="Contoh jurnal otomatis PAYOU dari satu penjualan: Kas debit 77.000, Pendapatan Penjualan kredit 70.000, PPN Keluaran kredit 7.000, dengan total debit sama dengan total kredit."
            className={cn(
                'text-isi w-full rounded-panel border border-garis bg-permukaan p-6 text-teks-utama',
                className,
            )}
        >
            <p className="text-label mb-4 font-semibold text-teks-sekunder">Jurnal otomatis · PJL/20260927/KSR1/0043</p>
            <ul className="flex flex-col">
                {BARIS_JURNAL.map((b) => (
                    <li key={b.Kode} className="flex items-baseline gap-3 border-b border-garis py-3">
                        <span className="flex-1">
                            <span className="font-mono text-keterangan text-teks-sekunder">{b.Kode}</span> {b.Akun}
                        </span>
                        <span className="w-24 text-right font-mono tabular-nums">{b.Debit ?? '—'}</span>
                        <span className="w-24 text-right font-mono tabular-nums">{b.Kredit ?? '—'}</span>
                    </li>
                ))}
                <li className="flex items-baseline gap-3 py-3 font-semibold">
                    <span className="flex-1">Seimbang</span>
                    <span className="w-24 text-right font-mono tabular-nums">77.000</span>
                    <span className="w-24 text-right font-mono tabular-nums">77.000</span>
                </li>
            </ul>
        </div>
    );
}

/** D-39: tangkapan layar asli aplikasi, dihasilkan `Aplikasi/{Kasir,Pemilik}/AlatSitus/FotoSitus_test.dart`. */
const FOTO_KASIR = { src: '/situs/produk/kasir-jual.webp', lebar: 1280, tinggi: 800 } as const;
const FOTO_PEMILIK = { src: '/situs/produk/pemilik-beranda.webp', lebar: 390, tinggi: 844 } as const;

/** Bingkai HP: tepi gelap tipis membulat, tanpa bayangan dekoratif (§17.6.11). */
function BingkaiHp({ className, prioritas = false }: PropsSpesimen & { prioritas?: boolean }) {
    return (
        <div className={cn('rounded-[1.75rem] border border-teks-utama bg-teks-utama p-1.5', className)}>
            <img
                src={FOTO_PEMILIK.src}
                width={FOTO_PEMILIK.lebar}
                height={FOTO_PEMILIK.tinggi}
                loading={prioritas ? 'eager' : 'lazy'}
                decoding="async"
                alt="Aplikasi Pemilik PAYOU: omzet hari ini Rp 8.475.000, naik 14% dari kemarin, perlu tindakan stok susu hampir habis, dan omzet per outlet."
                className="block h-auto w-full rounded-[1.375rem] bg-permukaan"
            />
        </div>
    );
}

/**
 * Layar Jual aplikasi Kasir di bingkai tablet, dengan aplikasi Pemilik di HP menumpuk di sudut kiri bawah: satu
 * gambar yang langsung menjelaskan "kasir di toko, pemilik dari mana saja". Gambar asli, bukan mockup karangan.
 */
export function SpesimenKasir({ className }: PropsSpesimen) {
    return (
        <div className={cn('relative w-full max-w-2xl pb-10 pl-6 sm:pb-14 sm:pl-10', className)}>
            <div className="rounded-[1.25rem] border border-teks-utama bg-teks-utama p-2 sm:p-2.5">
                <img
                    src={FOTO_KASIR.src}
                    width={FOTO_KASIR.lebar}
                    height={FOTO_KASIR.tinggi}
                    loading="eager"
                    fetchPriority="high"
                    decoding="async"
                    alt="Aplikasi Kasir PAYOU di tablet: katalog menu kafe, keranjang berisi Es Kopi Susu Aren, Croissant Cokelat, dan Matcha Latte, total Rp 95.700 dengan PBJT 10%."
                    className="block h-auto w-full rounded-[0.75rem] bg-permukaan"
                />
            </div>
            <BingkaiHp prioritas className="absolute bottom-0 left-0 w-[26%] min-w-24" />
        </div>
    );
}

/** Aplikasi Pemilik saja, untuk blok gambar-teks tentang memantau usaha dari HP. */
export function SpesimenPemilik({ className }: PropsSpesimen) {
    return <BingkaiHp className={cn('mx-auto w-full max-w-64', className)} />;
}

export const SPESIMEN = {
    Kasir: SpesimenKasir,
    Pemilik: SpesimenPemilik,
    Struk: SpesimenStruk,
    Jurnal: SpesimenJurnal,
} as const;

export type NamaSpesimen = keyof typeof SPESIMEN;
