import { Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import BilahAksiForm from '@/Komponen/Formulir/BilahAksiForm';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import { DaftarSaranTautan } from '@/Komponen/Pengelola/Situs/BidangTautan';
import EditorBlok, { BuatNilaiKosong } from '@/Komponen/Pengelola/Situs/EditorBlok';
import PemilihGambarSitus from '@/Komponen/Pengelola/Situs/PemilihGambarSitus';
import TabSitus from '@/Komponen/Pengelola/Situs/TabSitus';
import type { NilaiBlok, SkemaBlok } from '@/Komponen/Pengelola/Situs/Tipe';
import DialogKonfirmasi from '@/Komponen/Tindakan/DialogKonfirmasi';
import { Card } from '@/Komponen/Ui/card';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';

import { AmbilStatusHalaman, type RingkasHalamanSitus } from './Daftar';

type HalamanUbah = RingkasHalamanSitus & {
    JudulSeo: string | null;
    DeskripsiSeo: string | null;
    UuidGambarOg: string | null;
    TampilDiSitemap: boolean;
    Bagian: NilaiBlok[];
    Bawaan: boolean;
};

type PropsUbah = {
    Halaman: HalamanUbah;
    LabelBlok: Record<string, string>;
    Skema: Record<string, SkemaBlok>;
    Ikon: string[];
    Izin: { Kelola: boolean };
};

/** Editor halaman situs berblok (D-21): susun blok, simpan draf, pratinjau, terbitkan. */
export default function HalamanUbahHalamanSitus({ Halaman: halaman, LabelBlok, Skema, Ikon, Izin }: PropsUbah) {
    const url = `/situs/halaman/${halaman.Uuid}`;
    const formulir = useForm({
        Slug: halaman.Slug,
        Judul: halaman.Judul,
        JudulSeo: halaman.JudulSeo ?? '',
        DeskripsiSeo: halaman.DeskripsiSeo ?? '',
        UuidGambarOg: halaman.UuidGambarOg,
        TampilDiSitemap: halaman.TampilDiSitemap,
    });
    // Blok disimpan di state terpisah: tipe JSON rekursif terlalu dalam untuk `useForm`.
    const [bagian, AturBagianState] = useState<NilaiBlok[]>(halaman.Bagian);
    const [bagianBerubah, AturBagianBerubah] = useState(false);
    const berubah = formulir.isDirty || bagianBerubah;
    const galat = formulir.errors as Record<string, string>;
    const [jenisBaru, AturJenisBaru] = useState('Keunggulan');
    const [konfirmasi, AturKonfirmasi] = useState<'terbit' | 'hapus' | null>(null);
    const [memproses, AturMemproses] = useState(false);
    const status = AmbilStatusHalaman(halaman);
    const AturBagian = (baru: NilaiBlok[]) => {
        AturBagianState(baru);
        AturBagianBerubah(true);
    };

    const Simpan = (p?: FormEvent) => {
        p?.preventDefault();
        formulir.transform((data) => ({ ...data, Bagian: bagian }));
        formulir.put(url, {
            preserveScroll: true,
            onSuccess: () => {
                formulir.setDefaults();
                AturBagianBerubah(false);
            },
        });
    };
    const Batalkan = () => {
        formulir.reset();
        AturBagianState(halaman.Bagian);
        AturBagianBerubah(false);
    };
    const Kirim = (aksi: string, data: Record<string, boolean> = {}, metode: 'post' | 'delete' = 'post') => {
        const opsi = {
            preserveScroll: true,
            onStart: () => AturMemproses(true),
            onFinish: () => {
                AturMemproses(false);
                AturKonfirmasi(null);
            },
        };

        if (metode === 'delete') {
            router.delete(url, opsi);
        } else {
            router.post(`${url}/${aksi}`, data, opsi);
        }
    };
    const TambahBlok = () => {
        const skema = Skema[jenisBaru];

        if (skema) {
            AturBagian([...bagian, { Jenis: jenisBaru, ...BuatNilaiKosong(skema) }]);
        }
    };
    const Pindah = (i: number, arah: -1 | 1) => {
        const baru = [...bagian];
        const [blok] = baru.splice(i, 1);

        if (blok) {
            baru.splice(i + arah, 0, blok);
            AturBagian(baru);
        }
    };

    return (
        <TataLetakPengelola
            judul={halaman.Judul}
            aksi={
                <div className="flex flex-wrap gap-2">
                    <a
                        href={`${url}/pratinjau`}
                        target="_blank"
                        rel="noopener"
                        className="inline-flex h-8 items-center gap-2 rounded-kontrol border border-garis-input px-3 text-isi font-medium text-teks-utama hover:bg-permukaan-sorot pointer-coarse:h-11"
                    >
                        Pratinjau draf <ExternalLink className="size-4" aria-hidden />
                    </a>
                    {Izin.Kelola ? (
                        <Tombol
                            varian="sekunder"
                            disabled={berubah}
                            title={berubah ? 'Simpan draf dulu' : undefined}
                            onClick={() => AturKonfirmasi('terbit')}
                        >
                            Terbitkan
                        </Tombol>
                    ) : null}
                </div>
            }
        >
            <TabSitus />
            <DaftarSaranTautan />
            <Link href="/situs/halaman" className="text-label font-semibold text-brand underline">
                Semua halaman
            </Link>
            {galat.Umum ? <Pemberitahuan jenis="bahaya">{galat.Umum}</Pemberitahuan> : null}
            <div className="flex flex-wrap items-center gap-2 text-keterangan text-teks-sekunder">
                <LabelStatus jenis={status.jenis} teks={status.teks} />
                <span className="font-mono">payou.id{halaman.Jalur}</span>
                {halaman.DiterbitkanPada ? <span>Terbit {FormatTanggalWaktu(halaman.DiterbitkanPada)}</span> : null}
            </div>
            {berubah ? (
                <Pemberitahuan jenis="peringatan">
                    Ada perubahan yang belum disimpan. Simpan draf sebelum pratinjau atau terbitkan.
                </Pemberitahuan>
            ) : null}
            {konfirmasi === 'terbit' ? (
                <DialogKonfirmasi
                    judul={`Terbitkan ${halaman.Judul}?`}
                    labelAksi="Terbitkan"
                    varian="utama"
                    memproses={memproses}
                    saatKonfirmasi={() => Kirim('terbitkan')}
                    saatBatal={() => AturKonfirmasi(null)}
                >
                    <p>Draf tersimpan langsung tampil ke pengunjung situs.</p>
                </DialogKonfirmasi>
            ) : null}
            {konfirmasi === 'hapus' ? (
                <DialogKonfirmasi
                    judul={`Hapus halaman ${halaman.Judul}?`}
                    labelAksi="Hapus halaman"
                    memproses={memproses}
                    saatKonfirmasi={() => Kirim('', {}, 'delete')}
                    saatBatal={() => AturKonfirmasi(null)}
                >
                    <p>
                        Alamat {halaman.Jalur} akan menampilkan "halaman tidak ditemukan". Tautan menu ke halaman ini
                        perlu diubah.
                    </p>
                </DialogKonfirmasi>
            ) : null}
            <form onSubmit={Simpan} className="flex flex-col gap-4" noValidate>
                <Card className="grid gap-4 px-6 py-6 sm:grid-cols-2 rounded-panel shadow-none">
                    <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">Halaman & SEO</h2>
                    <BidangTeks
                        label="Judul halaman"
                        nilai={formulir.data.Judul}
                        saatBerubah={(v) => formulir.setData('Judul', v)}
                        galat={galat.Judul}
                        maxLength={150}
                        required
                    />
                    <BidangTeks
                        label="Alamat halaman"
                        kode
                        keterangan={
                            halaman.Bawaan ? 'Halaman bawaan; alamat tetap.' : 'Huruf kecil, angka, tanda hubung.'
                        }
                        nilai={formulir.data.Slug}
                        saatBerubah={(v) => formulir.setData('Slug', v.toLowerCase())}
                        galat={galat.Slug}
                        maxLength={100}
                        disabled={halaman.Bawaan}
                        required
                    />
                    <BidangTeks
                        label="Judul di Google (opsional)"
                        keterangan={`${formulir.data.JudulSeo.length}/70. Kosong = judul halaman | nama situs.`}
                        nilai={formulir.data.JudulSeo}
                        saatBerubah={(v) => formulir.setData('JudulSeo', v)}
                        galat={galat.JudulSeo}
                        maxLength={70}
                    />
                    <PemilihGambarSitus
                        label="Gambar saat dibagikan (opsional)"
                        keterangan="Tampil di WhatsApp/Facebook saat tautan dibagikan. Ukuran ideal 1200×630."
                        nilai={formulir.data.UuidGambarOg}
                        saatBerubah={(v) => formulir.setData('UuidGambarOg', v)}
                        galat={galat.UuidGambarOg}
                        bolehUbah={Izin.Kelola}
                    />
                    <div className="sm:col-span-2">
                        <BidangTeksPanjang
                            label="Deskripsi di Google (opsional)"
                            keterangan={`${formulir.data.DeskripsiSeo.length}/170. Kosong = deskripsi umum situs.`}
                            nilai={formulir.data.DeskripsiSeo}
                            saatBerubah={(v) => formulir.setData('DeskripsiSeo', v)}
                            galat={galat.DeskripsiSeo}
                            maksimal={170}
                            baris={2}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <KotakCentang
                            label="Tampilkan di peta situs (/peta-situs) untuk mesin pencari"
                            nilai={formulir.data.TampilDiSitemap}
                            saatBerubah={(v) => formulir.setData('TampilDiSitemap', v)}
                        />
                    </div>
                </Card>
                <section aria-labelledby="judul-blok" className="flex flex-col gap-3">
                    <h2 id="judul-blok" className="text-subjudul font-semibold text-teks-utama">
                        Blok halaman ({bagian.length})
                    </h2>
                    {galat.Bagian ? <Pemberitahuan jenis="bahaya">{galat.Bagian}</Pemberitahuan> : null}
                    {bagian.length === 0 ? (
                        <p className="text-isi text-teks-sekunder">Belum ada blok. Tambahkan blok pertama di bawah.</p>
                    ) : null}
                    {bagian.map((blok, i) => {
                        const jenis = String(blok.Jenis);
                        const skema = Skema[jenis];

                        return skema ? (
                            <EditorBlok
                                key={`${jenis}-${i}`}
                                indeks={i}
                                jumlah={bagian.length}
                                blok={blok}
                                skema={skema}
                                labelJenis={LabelBlok[jenis] ?? jenis}
                                galat={galat}
                                ikon={Ikon}
                                bolehUbah={Izin.Kelola}
                                saatBerubah={(b) => AturBagian(bagian.map((x, j) => (j === i ? b : x)))}
                                saatPindah={(arah) => Pindah(i, arah)}
                                saatGandakan={() =>
                                    AturBagian([
                                        ...bagian.slice(0, i + 1),
                                        structuredClone(blok),
                                        ...bagian.slice(i + 1),
                                    ])
                                }
                                saatHapus={() => AturBagian(bagian.filter((_, j) => j !== i))}
                            />
                        ) : null;
                    })}
                    {Izin.Kelola ? (
                        <Card className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-end rounded-panel shadow-none">
                            <div className="sm:w-80">
                                <BidangPilihan
                                    label="Jenis blok baru"
                                    nilai={jenisBaru}
                                    opsi={Object.entries(LabelBlok).map(([nilai, label]) => ({
                                        Nilai: nilai,
                                        Label: label,
                                    }))}
                                    saatBerubah={AturJenisBaru}
                                />
                            </div>
                            <Tombol varian="sekunder" onClick={TambahBlok}>
                                <Plus aria-hidden /> Tambah blok
                            </Tombol>
                        </Card>
                    ) : null}
                </section>
                {Izin.Kelola ? (
                    <div className="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-2 border-t border-garis bg-latar py-3 tepi-bawah-aman">
                        <BilahAksiForm>
                            <Tombol type="submit" memproses={formulir.processing} disabled={!berubah}>
                                Simpan draf
                            </Tombol>
                            {berubah ? (
                                <Tombol varian="sekunder" onClick={Batalkan}>
                                    Batalkan perubahan
                                </Tombol>
                            ) : null}
                        </BilahAksiForm>
                        <div className="flex gap-2">
                            {halaman.Terbit && halaman.Slug !== 'beranda' ? (
                                <Tombol
                                    varian="sekunder"
                                    memproses={memproses}
                                    onClick={() => Kirim('aktif', { Aktif: !halaman.Aktif })}
                                >
                                    {halaman.Aktif ? 'Sembunyikan' : 'Tampilkan lagi'}
                                </Tombol>
                            ) : null}
                            {!halaman.Bawaan ? (
                                <Tombol varian="bahaya" onClick={() => AturKonfirmasi('hapus')}>
                                    Hapus halaman
                                </Tombol>
                            ) : null}
                        </div>
                    </div>
                ) : null}
            </form>
        </TataLetakPengelola>
    );
}
