import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import DialogKonfirmasi from '@/Komponen/Tindakan/DialogKonfirmasi';
import FormAkun from '@/Komponen/Pengelola/TemplateSektor/FormAkun';
import FormIsiBisnis from '@/Komponen/Pengelola/TemplateSektor/FormIsiBisnis';
import { Button } from '@/Komponen/Ui/button';
import { kelasDaftarTabPanel, kelasItemTabPanel } from '@/Komponen/Navigasi/TabTautan';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Komponen/Ui/tabs';
import { cn } from '@/Komponen/Ui/utils';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';
import type { GalatValidasi, IsiTemplate, PilihanEditorTemplate } from '@/Tipe/TemplateSektor';

type StatusVersi = 'Draf' | 'Terbit' | 'Usang';

type PropsEditor = {
    Template: { Kode: string; Nama: string; Keterangan: string | null };
    Versi: {
        Versi: number;
        Status: StatusVersi;
        Isi: IsiTemplate;
        HasilValidasi: { Lolos: boolean; Galat: GalatValidasi[] } | null;
        DivalidasiPada: string | null;
        DiterbitkanPada: string | null;
        VersiAsal: number | null;
    };
    DaftarVersi: { Versi: number; Status: StatusVersi; DiterbitkanPada: string | null }[];
    AdaDraf: boolean;
    Pilihan: PilihanEditorTemplate;
};

const jenisStatus = { Draf: 'peringatan', Terbit: 'sukses', Usang: 'netral' } as const;

const labelBagian: Record<string, string> = {
    ModeKasir: 'Mode kasir',
    KunciFitur: 'Fitur',
    Akun: 'Bagan akun',
    PemetaanAkun: 'Pemetaan akun',
    KodeSatuan: 'Satuan',
    KelompokPajak: 'Kelompok pajak',
    Pengaturan: 'Pengaturan',
    Kategori: 'Kategori',
    StasiunDapur: 'Stasiun dapur',
    AlasanVoid: 'Alasan void',
    AlasanPenyesuaian: 'Alasan penyesuaian',
    LaporanUnggulan: 'Laporan unggulan',
};

/** Editor terstruktur satu versi template sektor (P-03 langkah 2–5). */
export default function HalamanEditorTemplate({ Template, Versi, DaftarVersi, AdaDraf, Pilihan }: PropsEditor) {
    const { props } = usePage<PropsBersamaPengelola>();
    const pengguna = props.Pengguna;
    const draf = Versi.Status === 'Draf';
    const url = `/template-sektor/${encodeURIComponent(Template.Kode)}/versi/${Versi.Versi}`;
    const bolehKelolaDraf = PunyaIzin(pengguna, IzinPengelola.TemplateDrafKelola);
    const [memproses, AturMemproses] = useState(false);
    const [konfirmasi, AturKonfirmasi] = useState<'terbitkan' | 'hapus' | null>(null);
    const opsiKirim = {
        preserveScroll: true,
        onStart: () => AturMemproses(true),
        onFinish: () => AturMemproses(false),
    };
    // Dialog ditutup setelah permintaan selesai; galat umum tampil di halaman seperti sebelumnya.
    const opsiKonfirmasi = {
        ...opsiKirim,
        onFinish: () => {
            AturMemproses(false);
            AturKonfirmasi(null);
        },
    };

    const Terbitkan = () => router.post(`${url}/terbitkan`, {}, opsiKonfirmasi);
    const HapusDraf = () => router.delete(url, opsiKonfirmasi);

    return (
        <TataLetakPengelola
            judul={`${Template.Nama} | ${Template.Kode}`}
            aksi={
                <div className="flex flex-wrap gap-2">
                    {draf && bolehKelolaDraf ? (
                        <Tombol
                            varian="sekunder"
                            memproses={memproses}
                            onClick={() => router.post(`${url}/validasi`, {}, opsiKirim)}
                        >
                            Validasi ulang
                        </Tombol>
                    ) : null}
                    {draf && PunyaIzin(pengguna, IzinPengelola.TemplateTerbitkan) ? (
                        <Tombol memproses={memproses} onClick={() => AturKonfirmasi('terbitkan')}>
                            Terbitkan
                        </Tombol>
                    ) : null}
                    {!draf && !AdaDraf && bolehKelolaDraf ? (
                        <Tombol memproses={memproses} onClick={() => router.post(`${url}/duplikasi`, {}, opsiKirim)}>
                            Buat draf versi baru
                        </Tombol>
                    ) : null}
                    {draf && bolehKelolaDraf ? (
                        <Tombol varian="bahaya" memproses={memproses} onClick={() => AturKonfirmasi('hapus')}>
                            Hapus draf
                        </Tombol>
                    ) : null}
                </div>
            }
        >
            {konfirmasi === 'terbitkan' ? (
                <DialogKonfirmasi
                    judul={`Terbitkan ${Template.Kode} versi ${Versi.Versi}?`}
                    labelAksi="Terbitkan"
                    varian="utama"
                    memproses={memproses}
                    saatKonfirmasi={Terbitkan}
                    saatBatal={() => AturKonfirmasi(null)}
                >
                    Versi terbit tidak bisa diubah lagi.
                </DialogKonfirmasi>
            ) : null}
            {konfirmasi === 'hapus' ? (
                <DialogKonfirmasi
                    judul={`Hapus draf versi ${Versi.Versi}?`}
                    labelAksi="Hapus draf"
                    varian="bahaya"
                    memproses={memproses}
                    saatKonfirmasi={HapusDraf}
                    saatBatal={() => AturKonfirmasi(null)}
                >
                    Perubahan di draf ini hilang.
                </DialogKonfirmasi>
            ) : null}
            <nav aria-label="Versi template" className="flex flex-wrap items-center gap-2">
                <Button asChild variant="link" className="h-auto px-0 text-label font-semibold">
                    <Link href="/template-sektor">Semua template</Link>
                </Button>
                <span aria-hidden className="text-teks-sekunder">
                    |
                </span>
                {DaftarVersi.map((baris) => (
                    <Button
                        key={baris.Versi}
                        asChild
                        variant="outline"
                        size="sm"
                        className={cn(
                            'text-label font-semibold',
                            baris.Versi === Versi.Versi ? 'border-brand text-teks-utama' : 'text-teks-sekunder',
                        )}
                    >
                        <Link
                            href={`/template-sektor/${encodeURIComponent(Template.Kode)}/versi/${baris.Versi}`}
                            aria-current={baris.Versi === Versi.Versi ? 'page' : undefined}
                        >
                            Versi {baris.Versi} | {baris.Status}
                        </Link>
                    </Button>
                ))}
            </nav>
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
            <section className="flex flex-wrap items-center gap-3 text-keterangan text-teks-sekunder">
                <LabelStatus jenis={jenisStatus[Versi.Status]} teks={Versi.Status} />
                {Versi.VersiAsal ? <span>Disalin dari versi {Versi.VersiAsal}</span> : null}
                {Versi.DiterbitkanPada ? <span>Terbit {FormatTanggalWaktu(Versi.DiterbitkanPada)}</span> : null}
            </section>
            {draf ? (
                <HasilValidasi hasil={Versi.HasilValidasi} divalidasiPada={Versi.DivalidasiPada} />
            ) : (
                <Pemberitahuan jenis="info">
                    Versi {Versi.Status === 'Terbit' ? 'terbit' : 'usang'} tidak bisa diubah. Buat draf versi baru untuk
                    memperbaikinya.
                </Pemberitahuan>
            )}
            {/* Kedua panel tetap terpasang (forceMount) agar isian yang belum disimpan tidak hilang saat pindah tab. */}
            <Tabs defaultValue="isi-bisnis">
                <TabsList aria-label="Bagian template" className={kelasDaftarTabPanel}>
                    <TabsTrigger value="isi-bisnis" className={kelasItemTabPanel}>
                        Isi bisnis
                    </TabsTrigger>
                    <TabsTrigger value="akun" className={kelasItemTabPanel}>
                        Akun & pajak
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="isi-bisnis" forceMount className="data-[state=inactive]:hidden">
                    <FormIsiBisnis
                        key={`bisnis-${Versi.Versi}`}
                        url={url}
                        isi={Versi.Isi}
                        pilihan={Pilihan}
                        bolehUbah={draf && PunyaIzin(pengguna, IzinPengelola.TemplateIsiUbah)}
                    />
                </TabsContent>
                <TabsContent value="akun" forceMount className="data-[state=inactive]:hidden">
                    <FormAkun
                        key={`akun-${Versi.Versi}`}
                        url={url}
                        isi={{
                            Akun: Versi.Isi.Akun,
                            PemetaanAkun: Array.isArray(Versi.Isi.PemetaanAkun) ? {} : Versi.Isi.PemetaanAkun,
                            KelompokPajak: Versi.Isi.KelompokPajak,
                        }}
                        pilihan={Pilihan}
                        bolehUbah={draf && PunyaIzin(pengguna, IzinPengelola.TemplateAkunUbah)}
                    />
                </TabsContent>
            </Tabs>
        </TataLetakPengelola>
    );
}

type PropsHasilValidasi = {
    hasil: { Lolos: boolean; Galat: GalatValidasi[] } | null;
    divalidasiPada: string | null;
};

function HasilValidasi({ hasil, divalidasiPada }: PropsHasilValidasi) {
    if (hasil === null) {
        return (
            <Pemberitahuan jenis="peringatan" judul="Belum divalidasi">
                Simpan salah satu bagian atau jalankan validasi ulang untuk memeriksa template.
            </Pemberitahuan>
        );
    }

    const waktu = divalidasiPada ? ` (diperiksa ${FormatTanggalWaktu(divalidasiPada)})` : '';

    if (hasil.Lolos) {
        return (
            <Pemberitahuan jenis="sukses" judul={`Lolos validasi${waktu}`}>
                COA, pemetaan akun, pajak, fitur, dan satuan sudah konsisten. Template siap diterbitkan.
            </Pemberitahuan>
        );
    }

    return (
        <Pemberitahuan jenis="bahaya" judul={`${hasil.Galat.length} masalah validasi${waktu}`}>
            <p>Template tidak bisa terbit sampai semua masalah diperbaiki.</p>
            <ul className="mt-2 list-disc pl-5">
                {hasil.Galat.map((galat, indeks) => (
                    <li key={indeks}>
                        <span className="font-semibold">{labelBagian[galat.Bagian] ?? galat.Bagian}:</span>{' '}
                        {galat.Pesan}
                    </li>
                ))}
            </ul>
        </Pemberitahuan>
    );
}
