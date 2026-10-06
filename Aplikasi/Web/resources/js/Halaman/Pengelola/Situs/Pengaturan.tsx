import { useForm } from '@inertiajs/react';
import { ExternalLink, Plus, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import BidangTautan, { DaftarSaranTautan } from '@/Komponen/Pengelola/Situs/BidangTautan';
import PemilihGambarSitus from '@/Komponen/Pengelola/Situs/PemilihGambarSitus';
import TabSitus from '@/Komponen/Pengelola/Situs/TabSitus';
import type { TautanMenu } from '@/Komponen/Pengelola/Situs/Tipe';
import { Card } from '@/Komponen/Ui/card';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';

type KunciMediaSosial = 'Instagram' | 'Facebook' | 'Tiktok' | 'Youtube' | 'Linkedin' | 'X';
type KunciUnduh = 'Android' | 'Ios' | 'Windows';

export type PengaturanSitus = {
    NamaSitus: string;
    Slogan: string | null;
    JudulSeo: string;
    DeskripsiSeo: string;
    KataKunci: string | null;
    UuidGambarOg: string | null;
    UuidLogo: string | null;
    VerifikasiGoogle: string | null;
    Kontak: {
        WhatsApp: string | null;
        PesanWhatsApp: string | null;
        Email: string | null;
        Telepon: string | null;
        Alamat: string | null;
        JamLayanan: string | null;
    };
    MediaSosial: Record<KunciMediaSosial, string | null>;
    Pengumuman: { Aktif: boolean; Teks: string | null; Tautan: string | null };
    Menu: TautanMenu[];
    MenuKaki: { Judul: string; Tautan: TautanMenu[] }[];
    TeksKaki: string | null;
    TautanUnduh: Record<KunciUnduh, string | null>;
    TeksTombolDaftar: string;
    TeksTombolMasuk: string;
    TombolWhatsAppMelayang: boolean;
    Analitik: { IdGoogleAnalytics: string | null; IdMetaPixel: string | null };
    Prospek: { EmailNotifikasi: string | null };
};

const MEDIA_SOSIAL: { Kunci: KunciMediaSosial; Label: string }[] = [
    { Kunci: 'Instagram', Label: 'Instagram' },
    { Kunci: 'Facebook', Label: 'Facebook' },
    { Kunci: 'Tiktok', Label: 'TikTok' },
    { Kunci: 'Youtube', Label: 'YouTube' },
    { Kunci: 'Linkedin', Label: 'LinkedIn' },
    { Kunci: 'X', Label: 'X (Twitter)' },
];

const UNDUH: { Kunci: KunciUnduh; Label: string }[] = [
    { Kunci: 'Android', Label: 'Google Play (Android)' },
    { Kunci: 'Ios', Label: 'App Store (iPhone/iPad)' },
    { Kunci: 'Windows', Label: 'Unduhan Windows' },
];

function Kartu({ judul, keterangan, children }: { judul: string; keterangan?: string; children: ReactNode }) {
    return (
        <Card className="grid gap-4 px-6 py-6 sm:grid-cols-2 rounded-panel shadow-none">
            <div className="flex flex-col gap-1 sm:col-span-2">
                <h2 className="text-subjudul font-semibold text-teks-utama">{judul}</h2>
                {keterangan ? <p className="text-isi text-teks-sekunder">{keterangan}</p> : null}
            </div>
            {children}
        </Card>
    );
}

type PropsEditorTautan = {
    label: string;
    tautan: TautanMenu[];
    maks: number;
    saatBerubah: (tautan: TautanMenu[]) => void;
    galat: Record<string, string>;
    jalur: string;
};

/** Daftar tautan menu (label + tautan) yang bisa ditambah, dihapus, dan diurutkan. */
function EditorTautan({ label, tautan, maks, saatBerubah, galat, jalur }: PropsEditorTautan) {
    const Ubah = (i: number, kunci: keyof TautanMenu, nilai: string) =>
        saatBerubah(tautan.map((t, j) => (j === i ? { ...t, [kunci]: nilai } : t)));

    return (
        <fieldset className="flex flex-col gap-2 sm:col-span-2">
            <legend className="mb-1 text-label font-semibold text-teks-utama">
                {label} ({tautan.length}/{maks})
            </legend>
            {tautan.map((t, i) => (
                <div
                    key={i}
                    className="grid items-start gap-2 rounded-kontrol border border-garis p-2 sm:grid-cols-[1fr_1.5fr_auto]"
                >
                    <BidangTeks
                        label={`Label ${i + 1}`}
                        nilai={t.Label}
                        saatBerubah={(v) => Ubah(i, 'Label', v)}
                        galat={galat[`${jalur}.${i}.Label`]}
                        maxLength={40}
                    />
                    <BidangTautan
                        label={`Tautan ${i + 1}`}
                        keterangan=""
                        nilai={t.Tautan}
                        saatBerubah={(v) => Ubah(i, 'Tautan', v)}
                        galat={galat[`${jalur}.${i}.Tautan`]}
                    />
                    <button
                        type="button"
                        aria-label={`Hapus ${label.toLowerCase()} ${i + 1}`}
                        onClick={() => saatBerubah(tautan.filter((_, j) => j !== i))}
                        className="inline-flex size-8 items-center justify-center self-end rounded-kontrol text-teks-sekunder hover:bg-permukaan-sorot pointer-coarse:size-11"
                    >
                        <Trash2 className="size-4" aria-hidden />
                    </button>
                </div>
            ))}
            {tautan.length < maks ? (
                <div>
                    <Tombol varian="sekunder" onClick={() => saatBerubah([...tautan, { Label: '', Tautan: '' }])}>
                        <Plus aria-hidden /> Tambah tautan
                    </Tombol>
                </div>
            ) : null}
        </fieldset>
    );
}

/** Pengaturan umum situs pemasaran (D-21): identitas, SEO, kontak, menu, kaki, pengumuman, unduhan. */
export default function HalamanPengaturanSitus({
    Pengaturan,
    UrlSitus,
    Izin,
}: {
    Pengaturan: PengaturanSitus;
    UrlSitus: string;
    Izin: { Kelola: boolean };
}) {
    const formulir = useForm(Pengaturan);
    const d = formulir.data;
    const galat = formulir.errors as Record<string, string>;
    const Teks = (nilai: string | null) => nilai ?? '';

    const Simpan = (p: FormEvent) => {
        p.preventDefault();
        formulir.put('/situs/pengaturan', { preserveScroll: true, onSuccess: () => formulir.setDefaults() });
    };

    return (
        <TataLetakPengelola
            judul="Situs pemasaran"
            aksi={
                <a
                    href={UrlSitus}
                    target="_blank"
                    rel="noopener"
                    className="inline-flex h-8 items-center gap-2 rounded-kontrol border border-garis-input px-3 text-isi font-medium text-teks-utama hover:bg-permukaan-sorot pointer-coarse:h-11"
                >
                    Buka situs <ExternalLink className="size-4" aria-hidden />
                </a>
            }
        >
            <TabSitus />
            <DaftarSaranTautan />
            {galat.Umum ? <Pemberitahuan jenis="bahaya">{galat.Umum}</Pemberitahuan> : null}
            <form onSubmit={Simpan} className="flex flex-col gap-4" noValidate>
                <Kartu judul="Identitas" keterangan="Tampil di kepala, kaki, dan judul tab browser.">
                    <BidangTeks
                        label="Nama situs"
                        nilai={d.NamaSitus}
                        saatBerubah={(v) => formulir.setData('NamaSitus', v)}
                        galat={galat.NamaSitus}
                        maxLength={60}
                        required
                    />
                    <BidangTeks
                        label="Slogan"
                        nilai={Teks(d.Slogan)}
                        saatBerubah={(v) => formulir.setData('Slogan', v)}
                        galat={galat.Slogan}
                        maxLength={120}
                    />
                    <PemilihGambarSitus
                        label="Logo (opsional)"
                        keterangan="Kosong = logo Payoung bawaan. Pakai PNG/WEBP latar transparan, tinggi ±96px."
                        nilai={d.UuidLogo}
                        saatBerubah={(v) => formulir.setData('UuidLogo', v)}
                        galat={galat.UuidLogo}
                        bolehUbah={Izin.Kelola}
                    />
                    <BidangTeks
                        label="Teks tombol daftar"
                        nilai={d.TeksTombolDaftar}
                        saatBerubah={(v) => formulir.setData('TeksTombolDaftar', v)}
                        galat={galat.TeksTombolDaftar}
                        maxLength={30}
                        required
                    />
                    <BidangTeks
                        label="Teks tombol masuk"
                        nilai={d.TeksTombolMasuk}
                        saatBerubah={(v) => formulir.setData('TeksTombolMasuk', v)}
                        galat={galat.TeksTombolMasuk}
                        maxLength={30}
                        required
                    />
                </Kartu>

                <Kartu
                    judul="Mesin pencari (SEO)"
                    keterangan="Dipakai beranda dan halaman yang tidak mengisi SEO sendiri."
                >
                    <BidangTeks
                        label="Judul di Google"
                        keterangan={`${d.JudulSeo.length}/70`}
                        nilai={d.JudulSeo}
                        saatBerubah={(v) => formulir.setData('JudulSeo', v)}
                        galat={galat.JudulSeo}
                        maxLength={70}
                        required
                    />
                    <BidangTeks
                        label="Kata kunci (dipisah koma)"
                        nilai={Teks(d.KataKunci)}
                        saatBerubah={(v) => formulir.setData('KataKunci', v)}
                        galat={galat.KataKunci}
                        maxLength={255}
                    />
                    <div className="sm:col-span-2">
                        <BidangTeksPanjang
                            label="Deskripsi di Google"
                            keterangan={`${d.DeskripsiSeo.length}/170`}
                            nilai={d.DeskripsiSeo}
                            saatBerubah={(v) => formulir.setData('DeskripsiSeo', v)}
                            galat={galat.DeskripsiSeo}
                            maksimal={170}
                            baris={2}
                            required
                        />
                    </div>
                    <PemilihGambarSitus
                        label="Gambar saat dibagikan"
                        keterangan="Tampil di WhatsApp/Facebook. Ukuran ideal 1200×630."
                        nilai={d.UuidGambarOg}
                        saatBerubah={(v) => formulir.setData('UuidGambarOg', v)}
                        galat={galat.UuidGambarOg}
                        bolehUbah={Izin.Kelola}
                    />
                    <BidangTeks
                        label="Kode verifikasi Google Search Console"
                        keterangan='Isi nilai "content" dari tag meta google-site-verification.'
                        kode
                        nilai={Teks(d.VerifikasiGoogle)}
                        saatBerubah={(v) => formulir.setData('VerifikasiGoogle', v)}
                        galat={galat.VerifikasiGoogle}
                        maxLength={100}
                    />
                </Kartu>

                <Kartu judul="Kontak" keterangan="Tampil di kaki situs, blok Kontak, dan tombol WhatsApp.">
                    <BidangTeks
                        label="Nomor WhatsApp"
                        keterangan="Contoh 0812xxxx atau +62812xxxx."
                        inputMode="tel"
                        nilai={Teks(d.Kontak.WhatsApp)}
                        saatBerubah={(v) => formulir.setData('Kontak', { ...d.Kontak, WhatsApp: v })}
                        galat={galat['Kontak.WhatsApp']}
                        maxLength={20}
                    />
                    <BidangTeks
                        label="Pesan awal WhatsApp"
                        nilai={Teks(d.Kontak.PesanWhatsApp)}
                        saatBerubah={(v) => formulir.setData('Kontak', { ...d.Kontak, PesanWhatsApp: v })}
                        galat={galat['Kontak.PesanWhatsApp']}
                        maxLength={300}
                    />
                    <BidangTeks
                        label="Email"
                        jenis="email"
                        nilai={Teks(d.Kontak.Email)}
                        saatBerubah={(v) => formulir.setData('Kontak', { ...d.Kontak, Email: v })}
                        galat={galat['Kontak.Email']}
                        maxLength={150}
                    />
                    <BidangTeks
                        label="Telepon"
                        inputMode="tel"
                        nilai={Teks(d.Kontak.Telepon)}
                        saatBerubah={(v) => formulir.setData('Kontak', { ...d.Kontak, Telepon: v })}
                        galat={galat['Kontak.Telepon']}
                        maxLength={30}
                    />
                    <BidangTeksPanjang
                        label="Alamat"
                        nilai={Teks(d.Kontak.Alamat)}
                        saatBerubah={(v) => formulir.setData('Kontak', { ...d.Kontak, Alamat: v })}
                        galat={galat['Kontak.Alamat']}
                        maksimal={300}
                        baris={3}
                    />
                    <BidangTeks
                        label="Jam layanan"
                        nilai={Teks(d.Kontak.JamLayanan)}
                        saatBerubah={(v) => formulir.setData('Kontak', { ...d.Kontak, JamLayanan: v })}
                        galat={galat['Kontak.JamLayanan']}
                        maxLength={100}
                    />
                    <div className="sm:col-span-2">
                        <KotakCentang
                            label="Tampilkan tombol WhatsApp melayang di pojok kanan bawah"
                            nilai={d.TombolWhatsAppMelayang}
                            saatBerubah={(v) => formulir.setData('TombolWhatsAppMelayang', v)}
                        />
                    </div>
                </Kartu>

                <Kartu
                    judul="Prospek & analitik"
                    keterangan="Formulir kontak/minta demo dan pelacakan pengunjung. Analitik baru dimuat setelah pengunjung menyetujui cookie."
                >
                    <BidangTeks
                        label="Email notifikasi prospek"
                        keterangan="Kosong = email kontak di atas."
                        jenis="email"
                        nilai={Teks(d.Prospek.EmailNotifikasi)}
                        saatBerubah={(v) => formulir.setData('Prospek', { ...d.Prospek, EmailNotifikasi: v })}
                        galat={galat['Prospek.EmailNotifikasi']}
                        maxLength={150}
                    />
                    <BidangTeks
                        label="ID Google Analytics 4"
                        keterangan="Contoh G-ABC123XYZ. Kosong = tidak dipasang."
                        kode
                        nilai={Teks(d.Analitik.IdGoogleAnalytics)}
                        saatBerubah={(v) => formulir.setData('Analitik', { ...d.Analitik, IdGoogleAnalytics: v })}
                        galat={galat['Analitik.IdGoogleAnalytics']}
                        maxLength={20}
                    />
                    <BidangTeks
                        label="ID Meta Pixel"
                        keterangan="Angka dari Meta Events Manager. Kosong = tidak dipasang."
                        kode
                        inputMode="numeric"
                        nilai={Teks(d.Analitik.IdMetaPixel)}
                        saatBerubah={(v) => formulir.setData('Analitik', { ...d.Analitik, IdMetaPixel: v })}
                        galat={galat['Analitik.IdMetaPixel']}
                        maxLength={20}
                    />
                </Kartu>

                <Kartu
                    judul="Pengumuman"
                    keterangan="Bilah tipis di paling atas semua halaman (misal promo atau info libur)."
                >
                    <div className="sm:col-span-2">
                        <KotakCentang
                            label="Tampilkan pengumuman"
                            nilai={d.Pengumuman.Aktif}
                            saatBerubah={(v) => formulir.setData('Pengumuman', { ...d.Pengumuman, Aktif: v })}
                        />
                    </div>
                    <BidangTeks
                        label="Teks pengumuman"
                        nilai={Teks(d.Pengumuman.Teks)}
                        saatBerubah={(v) => formulir.setData('Pengumuman', { ...d.Pengumuman, Teks: v })}
                        galat={galat['Pengumuman.Teks']}
                        maxLength={200}
                    />
                    <BidangTautan
                        label="Tautan pengumuman (opsional)"
                        nilai={Teks(d.Pengumuman.Tautan)}
                        saatBerubah={(v) => formulir.setData('Pengumuman', { ...d.Pengumuman, Tautan: v })}
                        galat={galat['Pengumuman.Tautan']}
                    />
                </Kartu>

                <Kartu judul="Menu atas" keterangan="Tautan di kepala situs. Di HP tampil sebagai menu lipat.">
                    <EditorTautan
                        label="Menu"
                        tautan={d.Menu}
                        maks={10}
                        saatBerubah={(v) => formulir.setData('Menu', v)}
                        galat={galat}
                        jalur="Menu"
                    />
                </Kartu>

                <Kartu judul="Kaki situs" keterangan="Kolom tautan di bagian bawah semua halaman.">
                    <div className="sm:col-span-2">
                        <BidangTeksPanjang
                            label="Teks singkat di kaki"
                            nilai={Teks(d.TeksKaki)}
                            saatBerubah={(v) => formulir.setData('TeksKaki', v)}
                            galat={galat.TeksKaki}
                            maksimal={400}
                            baris={2}
                        />
                    </div>
                    {d.MenuKaki.map((kolom, i) => (
                        <fieldset
                            key={i}
                            className="flex flex-col gap-3 rounded-panel border border-garis p-4 sm:col-span-2"
                        >
                            <legend className="px-1 text-label font-semibold text-teks-utama">Kolom {i + 1}</legend>
                            <div className="flex items-end gap-2">
                                <div className="flex-1">
                                    <BidangTeks
                                        label="Judul kolom"
                                        nilai={kolom.Judul}
                                        saatBerubah={(v) =>
                                            formulir.setData(
                                                'MenuKaki',
                                                d.MenuKaki.map((k, j) => (j === i ? { ...k, Judul: v } : k)),
                                            )
                                        }
                                        galat={galat[`MenuKaki.${i}.Judul`]}
                                        maxLength={40}
                                    />
                                </div>
                                <Tombol
                                    varian="sekunder"
                                    onClick={() =>
                                        formulir.setData(
                                            'MenuKaki',
                                            d.MenuKaki.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    Hapus kolom
                                </Tombol>
                            </div>
                            <EditorTautan
                                label="Tautan"
                                tautan={kolom.Tautan}
                                maks={10}
                                saatBerubah={(v) =>
                                    formulir.setData(
                                        'MenuKaki',
                                        d.MenuKaki.map((k, j) => (j === i ? { ...k, Tautan: v } : k)),
                                    )
                                }
                                galat={galat}
                                jalur={`MenuKaki.${i}.Tautan`}
                            />
                        </fieldset>
                    ))}
                    {d.MenuKaki.length < 5 ? (
                        <div className="sm:col-span-2">
                            <Tombol
                                varian="sekunder"
                                onClick={() => formulir.setData('MenuKaki', [...d.MenuKaki, { Judul: '', Tautan: [] }])}
                            >
                                <Plus aria-hidden /> Tambah kolom
                            </Tombol>
                        </div>
                    ) : null}
                </Kartu>

                <Kartu judul="Media sosial" keterangan="Alamat lengkap https://…; kosong = tidak ditampilkan.">
                    {MEDIA_SOSIAL.map((m) => (
                        <BidangTeks
                            key={m.Kunci}
                            label={m.Label}
                            inputMode="url"
                            nilai={Teks(d.MediaSosial[m.Kunci])}
                            saatBerubah={(v) => formulir.setData('MediaSosial', { ...d.MediaSosial, [m.Kunci]: v })}
                            galat={galat[`MediaSosial.${m.Kunci}`]}
                            maxLength={255}
                        />
                    ))}
                </Kartu>

                <Kartu
                    judul="Tautan unduh aplikasi"
                    keterangan="Dipakai blok Unduh aplikasi dan pintasan @unduh-android, @unduh-ios, @unduh-windows."
                >
                    {UNDUH.map((u) => (
                        <BidangTeks
                            key={u.Kunci}
                            label={u.Label}
                            inputMode="url"
                            nilai={Teks(d.TautanUnduh[u.Kunci])}
                            saatBerubah={(v) => formulir.setData('TautanUnduh', { ...d.TautanUnduh, [u.Kunci]: v })}
                            galat={galat[`TautanUnduh.${u.Kunci}`]}
                            maxLength={255}
                        />
                    ))}
                </Kartu>

                {Izin.Kelola ? (
                    <div className="sticky bottom-0 z-10 flex gap-2 border-t border-garis bg-latar py-3 tepi-bawah-aman">
                        <Tombol type="submit" memproses={formulir.processing} disabled={!formulir.isDirty}>
                            Simpan pengaturan
                        </Tombol>
                        {formulir.isDirty ? (
                            <Tombol varian="sekunder" onClick={() => formulir.reset()}>
                                Batalkan perubahan
                            </Tombol>
                        ) : null}
                    </div>
                ) : null}
            </form>
        </TataLetakPengelola>
    );
}
