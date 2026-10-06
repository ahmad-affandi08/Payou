import { Link, router } from '@inertiajs/react';
import {
    Eye,
    EyeOff,
    ExternalLink,
    LayoutPanelLeft,
    MoreHorizontal,
    Monitor,
    Plus,
    Redo2,
    Settings2,
    Trash2,
    Undo2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import { DaftarSaranTautan } from '@/Komponen/Pengelola/Situs/BidangTautan';
import { BuatNilaiKosong } from '@/Komponen/Pengelola/Situs/EditorBlok';
import DaftarBlok from '@/Komponen/Pengelola/Situs/EditorVisual/DaftarBlok';
import DialogTambahBlok from '@/Komponen/Pengelola/Situs/EditorVisual/DialogTambahBlok';
import PanelPengaturanHalaman from '@/Komponen/Pengelola/Situs/EditorVisual/PanelPengaturanHalaman';
import PanelPratinjau, { OPSI_PERANGKAT } from '@/Komponen/Pengelola/Situs/EditorVisual/PanelPratinjau';
import {
    BerinyaIdBlok,
    BuatIdBlok,
    KunciDraf,
    type BlokDraf,
    type Draf,
    type Perangkat,
} from '@/Komponen/Pengelola/Situs/EditorVisual/Tipe';
import { useRiwayat } from '@/Komponen/Pengelola/Situs/EditorVisual/useRiwayat';
import { useSimpanOtomatis } from '@/Komponen/Pengelola/Situs/EditorVisual/useSimpanOtomatis';
import { usePratinjauLangsung } from '@/Komponen/Pengelola/Situs/EditorVisual/usePratinjauLangsung';
import TabSitus from '@/Komponen/Pengelola/Situs/TabSitus';
import type { NilaiBlok, SkemaBlok } from '@/Komponen/Pengelola/Situs/Tipe';
import DialogKonfirmasi from '@/Komponen/Tindakan/DialogKonfirmasi';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Komponen/Ui/dropdown-menu';
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
    NamaSitus: string;
    AlamatSitus: string;
    UrlPratinjauEditor: string;
    Izin: { Kelola: boolean };
};

const LABEL_SIMPAN = {
    diam: '',
    menunggu: 'Perubahan belum disimpan…',
    menyimpan: 'Menyimpan draf…',
    tersimpan: 'Draf tersimpan',
    galat: 'Draf belum tersimpan',
} as const;

function Jam(waktu: Date | null): string {
    return waktu === null ? '' : waktu.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

/** Editor halaman situs: pratinjau langsung di samping daftar blok yang bisa diseret (D-21, D-63). */
export default function HalamanUbahHalamanSitus({
    Halaman: halaman,
    LabelBlok,
    Skema,
    Ikon,
    NamaSitus,
    AlamatSitus,
    UrlPratinjauEditor,
    Izin,
}: PropsUbah) {
    const url = `/situs/halaman/${halaman.Uuid}`;
    const awal = useMemo<Draf>(
        () => ({
            Slug: halaman.Slug,
            Judul: halaman.Judul,
            JudulSeo: halaman.JudulSeo ?? '',
            DeskripsiSeo: halaman.DeskripsiSeo ?? '',
            UuidGambarOg: halaman.UuidGambarOg,
            TampilDiSitemap: halaman.TampilDiSitemap,
            Bagian: BerinyaIdBlok(halaman.Bagian),
        }),
        // Muatan awal hanya dihitung sekali; sesudahnya editor memegang isi yang sedang disunting.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );
    const riwayat = useRiwayat<Draf>(awal);
    const draf = riwayat.nilai;
    const [kunciTersimpan, AturKunciTersimpan] = useState(() => KunciDraf(awal));
    const [adaPerubahanServer, AturAdaPerubahanServer] = useState(halaman.AdaPerubahan);
    const SaatTersimpan = useCallback((kunci: string, adaPerubahan: boolean) => {
        AturKunciTersimpan(kunci);
        AturAdaPerubahanServer(adaPerubahan);
    }, []);
    const simpan = useSimpanOtomatis({ draf, kunciTersimpan, url, aktif: Izin.Kelola, saatTersimpan: SaatTersimpan });
    const pratinjau = usePratinjauLangsung(url, draf);
    const galat = { ...pratinjau.galat, ...simpan.galat };
    const [terbukaId, AturTerbukaId] = useState<string | null>(null);
    const [perangkat, AturPerangkat] = useState<Perangkat>('komputer');
    const [tab, AturTab] = useState<'susun' | 'pratinjau'>('susun');
    const [dialogBlok, AturDialogBlok] = useState<{ sisipSetelah: number | null } | null>(null);
    const [panelHalaman, AturPanelHalaman] = useState(false);
    const [konfirmasi, AturKonfirmasi] = useState<'terbit' | 'hapus' | null>(null);
    const [memproses, AturMemproses] = useState(false);
    const [terhapus, AturTerhapus] = useState<string | null>(null);
    const terpilihIndeks = draf.Bagian.findIndex((b) => b._id === terbukaId);
    const status = AmbilStatusHalaman({ ...halaman, AdaPerubahan: adaPerubahanServer || simpan.kotor });
    const jenisTersedia = Object.keys(LabelBlok);

    const Ubah = useCallback(
        (pembaruan: (lama: Draf) => Draf, gabung = false) => riwayat.ubah(pembaruan, gabung),
        // `riwayat.ubah` stabil; objek `riwayat` berganti tiap render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [riwayat.ubah],
    );

    // Peringatan bila halaman ditutup saat masih ada perubahan yang belum tersimpan.
    useEffect(() => {
        if (!simpan.kotor) {
            return;
        }

        const Cegah = (peristiwa: BeforeUnloadEvent) => {
            peristiwa.preventDefault();
        };
        window.addEventListener('beforeunload', Cegah);

        return () => window.removeEventListener('beforeunload', Cegah);
    }, [simpan.kotor]);

    // Pintasan papan ketik: Ctrl+S simpan, Ctrl+Z urungkan, Ctrl+Shift+Z / Ctrl+Y ulangi (di luar kolom ketik).
    useEffect(() => {
        const SaatTombol = (peristiwa: KeyboardEvent) => {
            if (!(peristiwa.ctrlKey || peristiwa.metaKey)) {
                return;
            }

            const tombol = peristiwa.key.toLowerCase();
            const dalamKolom = (peristiwa.target as HTMLElement | null)?.closest(
                'input, textarea, select, [contenteditable]',
            );

            if (tombol === 's') {
                peristiwa.preventDefault();
                void simpan.simpanSekarang();
            } else if (!dalamKolom && tombol === 'z') {
                peristiwa.preventDefault();

                if (peristiwa.shiftKey) {
                    riwayat.ulangi();
                } else {
                    riwayat.urungkan();
                }
            } else if (!dalamKolom && tombol === 'y') {
                peristiwa.preventDefault();
                riwayat.ulangi();
            }
        };
        window.addEventListener('keydown', SaatTombol);

        return () => window.removeEventListener('keydown', SaatTombol);
    }, [riwayat, simpan]);

    const PilihBlokDariPratinjau = useCallback(
        (indeks: number) => {
            const blok = draf.Bagian[indeks];

            if (blok) {
                AturTerbukaId(blok._id);
                AturTab('susun');
            }
        },
        [draf.Bagian],
    );

    const TambahBlok = (jenis: string) => {
        const skema = Skema[jenis];
        const sisip = dialogBlok?.sisipSetelah ?? null;

        if (!skema) {
            return;
        }

        const baru: BlokDraf = { _id: BuatIdBlok(), Jenis: jenis, ...BuatNilaiKosong(skema) };
        Ubah((d) => {
            const posisi = sisip === null ? d.Bagian.length : sisip + 1;

            return { ...d, Bagian: [...d.Bagian.slice(0, posisi), baru, ...d.Bagian.slice(posisi)] };
        });
        AturTerbukaId(baru._id);
        AturDialogBlok(null);
        AturTab('susun');
    };

    const HapusBlok = (indeks: number) => {
        const blok = draf.Bagian[indeks];

        if (!blok) {
            return;
        }

        Ubah((d) => ({ ...d, Bagian: d.Bagian.filter((_, j) => j !== indeks) }));
        AturTerhapus(LabelBlok[String(blok.Jenis)] ?? 'Blok');

        if (terbukaId === blok._id) {
            AturTerbukaId(null);
        }
    };

    const GandakanBlok = (indeks: number) => {
        const blok = draf.Bagian[indeks];

        if (!blok) {
            return;
        }

        const salinan: BlokDraf = { ...(structuredClone(blok) as BlokDraf), _id: BuatIdBlok() };
        Ubah((d) => ({ ...d, Bagian: [...d.Bagian.slice(0, indeks + 1), salinan, ...d.Bagian.slice(indeks + 1)] }));
        AturTerbukaId(salinan._id);
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
            router.post(`${url}/${aksi}`, data, {
                ...opsi,
                onSuccess: () => {
                    if (aksi === 'terbitkan') {
                        AturAdaPerubahanServer(false);
                    }
                },
            });
        }
    };

    const MintaTerbit = async () => {
        if (await simpan.simpanSekarang()) {
            AturKonfirmasi('terbit');
        }
    };

    const BukaTabBaru = async () => {
        const jendela = window.open('', '_blank');
        await simpan.simpanSekarang();

        if (jendela) {
            jendela.location.href = `${url}/pratinjau`;
        }
    };

    const labelSimpan =
        simpan.status === 'tersimpan' && simpan.disimpanPada
            ? `${LABEL_SIMPAN.tersimpan} otomatis ${Jam(simpan.disimpanPada)}`
            : LABEL_SIMPAN[simpan.status];

    return (
        <TataLetakPengelola
            judul={draf.Judul === '' ? halaman.Judul : draf.Judul}
            aksi={
                <div className="flex flex-wrap items-center gap-2">
                    <Tombol varian="sekunder" onClick={() => void BukaTabBaru()}>
                        Buka di tab baru <ExternalLink className="size-4" aria-hidden />
                    </Tombol>
                    {Izin.Kelola ? (
                        <Tombol
                            memproses={memproses && konfirmasi === 'terbit'}
                            disabled={
                                simpan.status === 'galat' || (!simpan.kotor && !adaPerubahanServer && halaman.Terbit)
                            }
                            title={
                                simpan.status === 'galat'
                                    ? 'Perbaiki isian yang ditandai merah dulu'
                                    : !simpan.kotor && !adaPerubahanServer && halaman.Terbit
                                      ? 'Tidak ada perubahan untuk diterbitkan'
                                      : undefined
                            }
                            onClick={() => void MintaTerbit()}
                        >
                            Terbitkan
                        </Tombol>
                    ) : null}
                    {Izin.Kelola ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    aria-label="Aksi lain halaman"
                                    className="inline-flex size-8 items-center justify-center rounded-kontrol border border-garis-input text-teks-utama outline-none hover:bg-permukaan-sorot focus-visible:ring-2 focus-visible:ring-brand pointer-coarse:size-11"
                                >
                                    <MoreHorizontal className="size-4" aria-hidden />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {halaman.Terbit && halaman.Slug !== 'beranda' ? (
                                    <DropdownMenuItem onSelect={() => Kirim('aktif', { Aktif: !halaman.Aktif })}>
                                        {halaman.Aktif ? <EyeOff aria-hidden /> : <Eye aria-hidden />}
                                        {halaman.Aktif ? 'Sembunyikan dari situs' : 'Tampilkan lagi'}
                                    </DropdownMenuItem>
                                ) : null}
                                {!halaman.Bawaan ? (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            onSelect={() => AturKonfirmasi('hapus')}
                                        >
                                            <Trash2 aria-hidden /> Hapus halaman
                                        </DropdownMenuItem>
                                    </>
                                ) : null}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}
                </div>
            }
        >
            <TabSitus />
            <DaftarSaranTautan />
            {konfirmasi === 'terbit' ? (
                <DialogKonfirmasi
                    judul={`Terbitkan ${draf.Judul}?`}
                    labelAksi="Terbitkan"
                    varian="utama"
                    memproses={memproses}
                    saatKonfirmasi={() => Kirim('terbitkan')}
                    saatBatal={() => AturKonfirmasi(null)}
                >
                    <p>Isi yang sedang Anda lihat di pratinjau langsung tampil ke pengunjung situs.</p>
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
            <DialogTambahBlok
                terbuka={dialogBlok !== null}
                saatTutup={() => AturDialogBlok(null)}
                jenis={jenisTersedia}
                label={LabelBlok}
                saatPilih={TambahBlok}
                keterangan={
                    dialogBlok?.sisipSetelah == null
                        ? 'Blok baru ditambahkan di bagian paling bawah halaman.'
                        : `Blok baru ditambahkan di bawah blok ${String(dialogBlok.sisipSetelah + 1)}.`
                }
            />
            <PanelPengaturanHalaman
                terbuka={panelHalaman}
                saatTutup={() => AturPanelHalaman(false)}
                draf={draf}
                saatUbah={(bagian, gabung) => Ubah((d) => ({ ...d, ...bagian }), gabung ?? false)}
                galat={galat}
                bawaan={halaman.Bawaan}
                jalurAsli={AlamatSitus}
                namaSitus={NamaSitus}
                bolehUbah={Izin.Kelola}
            />

            <div className="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-panel border border-garis bg-permukaan px-3 py-2">
                <Link href="/situs/halaman" className="text-label font-semibold text-brand underline">
                    Semua halaman
                </Link>
                <LabelStatus jenis={status.jenis} teks={status.teks} />
                <span className="font-mono text-keterangan text-teks-sekunder">
                    {AlamatSitus.replace(/^https?:\/\//, '').replace(/\/$/, '')}
                    {draf.Slug === 'beranda' ? '' : `/${draf.Slug}`}
                </span>
                {halaman.DiterbitkanPada ? (
                    <span className="text-keterangan text-teks-sekunder">
                        Terbit {FormatTanggalWaktu(halaman.DiterbitkanPada)}
                    </span>
                ) : null}
                <span
                    role="status"
                    aria-live="polite"
                    className={`ml-auto text-keterangan font-medium ${
                        simpan.status === 'galat' ? 'text-bahaya' : 'text-teks-sekunder'
                    }`}
                >
                    {labelSimpan}
                </span>
                {Izin.Kelola ? (
                    <div className="flex items-center gap-1">
                        <button
                            type="button"
                            aria-label="Urungkan (Ctrl+Z)"
                            title="Urungkan (Ctrl+Z)"
                            disabled={!riwayat.bisaUrungkan}
                            onClick={riwayat.urungkan}
                            className="inline-flex size-8 items-center justify-center rounded-kontrol text-teks-utama outline-none hover:bg-permukaan-sorot focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-40 pointer-coarse:size-11"
                        >
                            <Undo2 className="size-4" aria-hidden />
                        </button>
                        <button
                            type="button"
                            aria-label="Ulangi (Ctrl+Y)"
                            title="Ulangi (Ctrl+Y)"
                            disabled={!riwayat.bisaUlangi}
                            onClick={riwayat.ulangi}
                            className="inline-flex size-8 items-center justify-center rounded-kontrol text-teks-utama outline-none hover:bg-permukaan-sorot focus-visible:ring-2 focus-visible:ring-brand disabled:opacity-40 pointer-coarse:size-11"
                        >
                            <Redo2 className="size-4" aria-hidden />
                        </button>
                    </div>
                ) : null}
                <Tombol varian="sekunder" onClick={() => AturPanelHalaman(true)}>
                    <Settings2 aria-hidden /> Pengaturan halaman
                </Tombol>
            </div>
            {simpan.status === 'galat' && simpan.pesan ? (
                <Pemberitahuan jenis="bahaya">{simpan.pesan}</Pemberitahuan>
            ) : null}
            {terhapus ? (
                <Pemberitahuan jenis="info">
                    Blok {terhapus} dihapus.{' '}
                    <button
                        type="button"
                        className="font-semibold text-brand underline"
                        onClick={() => {
                            riwayat.urungkan();
                            AturTerhapus(null);
                        }}
                    >
                        Urungkan
                    </button>{' '}
                    <button type="button" className="text-teks-sekunder underline" onClick={() => AturTerhapus(null)}>
                        Tutup
                    </button>
                </Pemberitahuan>
            ) : null}

            <div
                className="flex gap-1 rounded-kontrol bg-permukaan-redup p-1 lg:hidden"
                role="tablist"
                aria-label="Tampilan editor"
            >
                {(
                    [
                        ['susun', 'Susun blok'],
                        ['pratinjau', 'Pratinjau'],
                    ] as const
                ).map(([kunci, label]) => (
                    <button
                        key={kunci}
                        type="button"
                        role="tab"
                        aria-selected={tab === kunci}
                        onClick={() => AturTab(kunci)}
                        className={`h-9 flex-1 rounded-kontrol text-label font-semibold ${
                            tab === kunci ? 'bg-permukaan text-teks-utama shadow-sm' : 'text-teks-sekunder'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <div className="grid items-start gap-4 lg:grid-cols-[minmax(340px,430px)_minmax(0,1fr)]">
                <section
                    aria-label="Blok halaman"
                    className={`${tab === 'susun' ? 'flex' : 'hidden'} flex-col gap-3 lg:flex`}
                >
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-subjudul font-semibold text-teks-utama">
                            Blok halaman ({draf.Bagian.length})
                        </h2>
                        {Izin.Kelola ? (
                            <Tombol onClick={() => AturDialogBlok({ sisipSetelah: null })}>
                                <Plus aria-hidden /> Tambah blok
                            </Tombol>
                        ) : null}
                    </div>
                    {galat.Bagian ? <Pemberitahuan jenis="bahaya">{galat.Bagian}</Pemberitahuan> : null}
                    {draf.Bagian.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-panel border border-dashed border-garis-input bg-permukaan px-4 py-10 text-center">
                            <LayoutPanelLeft className="size-8 text-teks-sekunder" aria-hidden />
                            <p className="text-isi text-teks-sekunder">
                                Halaman ini masih kosong. Mulai dengan blok pembuka, lalu tambahkan isinya.
                            </p>
                            {Izin.Kelola ? (
                                <Tombol onClick={() => AturDialogBlok({ sisipSetelah: null })}>
                                    <Plus aria-hidden /> Tambah blok pertama
                                </Tombol>
                            ) : null}
                        </div>
                    ) : (
                        <DaftarBlok
                            bagian={draf.Bagian}
                            skema={Skema}
                            labelBlok={LabelBlok}
                            ikon={Ikon}
                            galat={galat}
                            bolehUbah={Izin.Kelola}
                            terbukaId={terbukaId}
                            terpilihId={terbukaId}
                            saatToggle={(id) => AturTerbukaId((lama) => (lama === id ? null : id))}
                            saatUbahBlok={(i, blok) =>
                                Ubah((d) => ({ ...d, Bagian: d.Bagian.map((x, j) => (j === i ? blok : x)) }), true)
                            }
                            saatUrutUlang={(baru) => Ubah((d) => ({ ...d, Bagian: baru }))}
                            saatGandakan={GandakanBlok}
                            saatHapus={HapusBlok}
                            saatSisipkan={(i) => AturDialogBlok({ sisipSetelah: i })}
                        />
                    )}
                </section>

                <section
                    aria-label="Pratinjau halaman"
                    className={`${tab === 'pratinjau' ? 'flex' : 'hidden'} flex-col gap-2 lg:sticky lg:top-3 lg:flex lg:h-[calc(100dvh-2rem)]`}
                >
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-subjudul font-semibold text-teks-utama">Pratinjau</h2>
                        <div
                            className="flex gap-1 rounded-kontrol bg-permukaan-redup p-1"
                            role="group"
                            aria-label="Ukuran layar pratinjau"
                        >
                            {OPSI_PERANGKAT.map(({ kunci, label, ikon: Ikon }) => (
                                <button
                                    key={kunci}
                                    type="button"
                                    aria-pressed={perangkat === kunci}
                                    onClick={() => AturPerangkat(kunci)}
                                    className={`inline-flex h-8 items-center gap-1.5 rounded-kontrol px-2.5 text-label font-semibold ${
                                        perangkat === kunci
                                            ? 'bg-permukaan text-teks-utama shadow-sm'
                                            : 'text-teks-sekunder'
                                    }`}
                                >
                                    <Ikon className="size-4" aria-hidden />
                                    <span className="hidden sm:inline">{label}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="min-h-0 flex-1">
                        <PanelPratinjau
                            urlBingkai={UrlPratinjauEditor}
                            halaman={pratinjau.halaman}
                            memuat={pratinjau.memuat}
                            terpilih={terpilihIndeks >= 0 ? terpilihIndeks : null}
                            perangkat={perangkat}
                            saatKlikBlok={PilihBlokDariPratinjau}
                        />
                    </div>
                    <p className="flex items-center gap-1.5 text-keterangan text-teks-sekunder">
                        <Monitor className="size-3.5" aria-hidden /> Klik bagian mana pun di pratinjau untuk
                        menyuntingnya. Perubahan tersimpan otomatis sebagai draf; pengunjung baru melihatnya setelah
                        Terbitkan.
                    </p>
                </section>
            </div>
        </TataLetakPengelola>
    );
}
