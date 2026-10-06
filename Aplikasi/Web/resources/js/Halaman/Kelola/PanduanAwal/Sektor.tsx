import { Link, useForm } from '@inertiajs/react';
import { useId, useRef, type FormEvent } from 'react';

import AjakanTambahBatas from '@/Komponen/Kelola/AjakanTambahBatas';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TataLetakPanduan from '@/Komponen/PanduanAwal/TataLetakPanduan';
import { Card } from '@/Komponen/Ui/card';
import { Empty, EmptyDescription, EmptyHeader } from '@/Komponen/Ui/empty';
import { FieldDescription, FieldError, FieldLegend, FieldSet } from '@/Komponen/Ui/field';
import { RadioGroup, RadioGroupItem } from '@/Komponen/Ui/radio-group';
import { cn } from '@/Komponen/Ui/utils';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { AlamatPanduan, type PropsSektor, type TemplatePilihan } from '@/Tipe/PanduanAwal';

type IsianSektor = { KodeTemplate: string; SektorLain: string[] };

const batasKategoriTampil = 6;

const pesanGantiTemplate =
    'Kategori dan akun dari template sebelumnya tetap ada. Template baru hanya menambah yang belum ada.';

/** Ringkasan daftar kategori: 6 pertama lalu "dan N lainnya". */
function RingkasKategori(kategori: string[]): string {
    if (kategori.length === 0) {
        return 'Belum ada';
    }

    const tampil = kategori.slice(0, batasKategoriTampil).join(', ');
    const sisa = kategori.length - batasKategoriTampil;

    return sisa > 0 ? `${tampil}, dan ${String(sisa)} lainnya` : tampil;
}

/** Langkah 2 F-01: pilih template sektor yang sudah terbit, lalu terapkan (aditif & idempoten, BR-01.1–01.3). */
export default function HalamanSektor({ Progres, Template, TemplateTerpilih, SektorLain, NamaPaket }: PropsSektor) {
    const elemenFormulir = useRef<HTMLFormElement>(null);
    const idGalatTemplate = useId();
    const idLegenda = useId();
    const formulir = useForm<IsianSektor>({
        KodeTemplate: TemplateTerpilih?.Kode ?? '',
        SektorLain: SektorLain.filter((kode) => kode !== TemplateTerpilih?.Kode),
    });
    const gantiTemplate =
        TemplateTerpilih !== null &&
        formulir.data.KodeTemplate !== '' &&
        formulir.data.KodeTemplate !== TemplateTerpilih.Kode;
    const galat = formulir.errors as Record<string, string | undefined>;
    const galatSektorLain =
        galat.SektorLain ?? Object.entries(galat).find(([kunci]) => kunci.startsWith('SektorLain.'))?.[1];

    const PilihTemplate = (kode: string) =>
        formulir.setData({
            KodeTemplate: kode,
            SektorLain: formulir.data.SektorLain.filter((item) => item !== kode),
        });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(AlamatPanduan.Sektor, {
            preserveScroll: true,
            onError: () => FokusGalatPertama(elemenFormulir.current),
        });
    };

    /** D-23 A: terapkan template sekaligus pajak usulan, produk contoh, dan metode bayar. */
    const SiapkanOtomatis = () => {
        formulir.post(`${AlamatPanduan.Sektor}/siapkan-otomatis`, {
            preserveScroll: true,
            onError: () => FokusGalatPertama(elemenFormulir.current),
        });
    };

    return (
        <TataLetakPanduan
            progres={Progres}
            langkah="Sektor"
            lanjut={Template.length === 0 ? 'tandai-selesai' : 'formulir'}
        >
            <p className="text-isi text-teks-sekunder">
                Template menyiapkan bagan akun, kategori, satuan, kelompok pajak, dan fitur kasir yang cocok untuk jenis
                usaha Anda. Semuanya bisa diubah nanti.
                {NamaPaket ? (
                    <>
                        {' '}
                        Paket Anda saat ini: <span className="font-semibold text-teks-utama">{NamaPaket}</span>.
                    </>
                ) : null}
            </p>

            {TemplateTerpilih ? (
                <Pemberitahuan jenis="info" judul={`Template saat ini: ${TemplateTerpilih.Nama}`}>
                    Versi {TemplateTerpilih.Versi}, diterapkan {FormatTanggalWaktu(TemplateTerpilih.DiterapkanPada)}.
                    Menerapkan ulang template yang sama aman: hanya data yang belum ada yang ditambahkan.
                </Pemberitahuan>
            ) : null}

            {Template.length === 0 ? (
                <Empty className="items-start border border-solid border-garis bg-permukaan p-6 text-left md:p-6">
                    <EmptyHeader className="max-w-none items-start text-left">
                        <EmptyDescription className="text-isi text-teks-sekunder">
                            Belum ada template yang bisa dipilih. Tekan Lanjutkan untuk menyiapkan toko tanpa template;
                            template bisa diterapkan nanti dari Pengaturan.{' '}
                            <AjakanTambahBatas teksLisensi="Hubungi penjual lisensi Payoung untuk paket data template sektor.">
                                Hubungi tim kami lewat menu{' '}
                                <Link href="/kelola/bantuan" className="font-semibold text-brand underline">
                                    Bantuan
                                </Link>
                                .
                            </AjakanTambahBatas>
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <form ref={elemenFormulir} onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                    <RingkasanGalatFormulir galat={formulir.errors} />
                    <FieldSet
                        className="gap-2"
                        aria-describedby={formulir.errors.KodeTemplate ? idGalatTemplate : undefined}
                    >
                        <FieldLegend id={idLegenda} className="mb-2 text-subjudul font-semibold text-teks-utama">
                            Pilih jenis usaha Anda
                        </FieldLegend>
                        <RadioGroup
                            name="KodeTemplate"
                            value={formulir.data.KodeTemplate}
                            onValueChange={PilihTemplate}
                            aria-labelledby={idLegenda}
                            className="grid-cols-1 lg:grid-cols-2"
                        >
                            {Template.map((template, indeks) => (
                                <PilihanTemplate
                                    key={template.Kode}
                                    template={template}
                                    terpilih={formulir.data.KodeTemplate === template.Kode}
                                    diterapkan={TemplateTerpilih?.Kode === template.Kode}
                                    tandaiTidakValid={indeks === 0 && Boolean(formulir.errors.KodeTemplate)}
                                />
                            ))}
                        </RadioGroup>
                        {formulir.errors.KodeTemplate ? (
                            <FieldError id={idGalatTemplate} className="text-keterangan font-semibold">
                                {formulir.errors.KodeTemplate}
                            </FieldError>
                        ) : null}
                    </FieldSet>

                    <div aria-live="polite">
                        {gantiTemplate ? (
                            <Pemberitahuan jenis="peringatan" judul="Template lama tidak dihapus">
                                {pesanGantiTemplate}
                            </Pemberitahuan>
                        ) : null}
                    </div>

                    {Template.length > 1 ? (
                        <Card className="gap-2 p-4 rounded-panel shadow-none">
                            <GrupCentang
                                legenda="Usaha Anda juga bergerak di bidang lain? (opsional)"
                                opsi={Template.filter((item) => item.Kode !== formulir.data.KodeTemplate).map(
                                    (item) => ({ nilai: item.Kode, label: item.Nama }),
                                )}
                                terpilih={formulir.data.SektorLain}
                                saatBerubah={(terpilih) => formulir.setData('SektorLain', terpilih.slice(0, 10))}
                                galat={galatSektorLain}
                            />
                            <FieldDescription className="text-keterangan">
                                Hanya dicatat untuk menyesuaikan saran fitur. Isi template lain tidak ditambahkan.
                                Maksimal 10.
                            </FieldDescription>
                        </Card>
                    ) : null}

                    <Card className="gap-2 p-4 shadow-none rounded-panel">
                        <h2 className="text-subjudul font-semibold text-teks-utama">Mulai jualan lebih cepat</h2>
                        <p className="text-isi text-teks-sekunder">
                            Siapkan semuanya otomatis: template diterapkan, pajak diatur sesuai usulan untuk kota Anda,
                            produk contoh ditambahkan dengan harga saran, dan pembayaran tunai langsung siap. Semua bisa
                            diubah nanti.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Tombol type="button" onClick={SiapkanOtomatis} memproses={formulir.processing}>
                                Siapkan semuanya otomatis
                            </Tombol>
                            <Tombol type="submit" varian="sekunder" memproses={formulir.processing}>
                                Terapkan template saja
                            </Tombol>
                        </div>
                    </Card>
                </form>
            )}
        </TataLetakPanduan>
    );
}

type PropsPilihanTemplate = {
    template: TemplatePilihan;
    terpilih: boolean;
    diterapkan: boolean;
    tandaiTidakValid: boolean;
};

function PilihanTemplate({ template, terpilih, diterapkan, tandaiTidakValid }: PropsPilihanTemplate) {
    const idPilihan = useId();
    const idKeterangan = useId();
    const fiturTerkunci = template.Fitur.filter((fitur) => !fitur.TersediaDiPaket);

    return (
        <Card
            className={cn('gap-2 p-4 shadow-none rounded-panel', terpilih ? 'border-2 border-primary' : 'border-garis')}
        >
            <div className="flex items-start gap-3">
                <RadioGroupItem
                    id={idPilihan}
                    value={template.Kode}
                    aria-describedby={idKeterangan}
                    aria-invalid={tandaiTidakValid || undefined}
                    className="mt-1"
                />
                <label htmlFor={idPilihan} className="flex min-w-0 cursor-pointer flex-col gap-1">
                    <span className="flex flex-wrap items-center gap-2">
                        <span className="text-subjudul font-semibold break-words text-teks-utama">{template.Nama}</span>
                        <span className="text-keterangan text-teks-sekunder">versi {template.Versi}</span>
                        {diterapkan ? <LabelStatus jenis="sukses" teks="Sedang dipakai" /> : null}
                    </span>
                    {template.Keterangan ? (
                        <span className="text-isi text-teks-sekunder">{template.Keterangan}</span>
                    ) : null}
                </label>
            </div>
            <dl id={idKeterangan} className="grid grid-cols-1 gap-x-4 gap-y-1 pl-7 text-label sm:grid-cols-[auto_1fr]">
                <dt className="text-teks-sekunder">Mode kasir</dt>
                <dd className="text-teks-utama">
                    {template.ModeKasir.length > 0 ? template.ModeKasir.map((mode) => mode.Label).join(', ') : '—'}
                </dd>
                <dt className="text-teks-sekunder">Kategori awal</dt>
                <dd className="text-teks-utama">{RingkasKategori(template.Kategori)}</dd>
                <dt className="text-teks-sekunder">Bagan akun</dt>
                <dd className="text-teks-utama">{template.JumlahAkun} akun</dd>
                <dt className="text-teks-sekunder">Produk contoh</dt>
                <dd className="text-teks-utama">
                    {template.JumlahProdukContoh > 0 ? `${String(template.JumlahProdukContoh)} produk` : 'Belum ada'}
                </dd>
            </dl>
            {template.Fitur.length > 0 ? (
                <details className="pl-7">
                    <summary className="cursor-pointer rounded-sm text-label font-semibold text-brand outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50">
                        {template.Fitur.length} fitur kasir
                        {fiturTerkunci.length > 0 ? `, ${String(fiturTerkunci.length)} butuh paket lebih tinggi` : ''}
                    </summary>
                    <ul className="mt-2 flex flex-col gap-1">
                        {template.Fitur.map((fitur) => (
                            <li
                                key={fitur.Kunci}
                                className="flex flex-wrap items-center gap-2 text-label text-teks-utama"
                            >
                                <span className="break-words">{fitur.Nama}</span>
                                {fitur.TersediaDiPaket ? (
                                    <span className="text-keterangan text-teks-sekunder">Tersedia di paket Anda</span>
                                ) : (
                                    <LabelStatus jenis="peringatan" teks="Butuh paket lebih tinggi" />
                                )}
                            </li>
                        ))}
                    </ul>
                </details>
            ) : null}
        </Card>
    );
}
