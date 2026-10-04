import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import TabelForm from '@/Komponen/TabelData/TabelForm';
import BidangDaftarTeks from '@/Komponen/Formulir/BidangDaftarTeks';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalat from '@/Komponen/Pengelola/TemplateSektor/RingkasanGalat';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { Input } from '@/Komponen/Ui/input';
import { TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { FormatRupiah } from '@/Pustaka/Format';
import type { Pilihan } from '@/Tipe/Pengelola';
import type {
    IsiBisnisTemplate,
    JenisProdukContoh,
    PilihanEditorTemplate,
    ProdukContohTemplate,
} from '@/Tipe/TemplateSektor';
import PilihanCari from '@/Komponen/Formulir/PilihanCari';

type PropsFormIsiBisnis = {
    url: string;
    isi: IsiBisnisTemplate;
    pilihan: PilihanEditorTemplate;
    bolehUbah: boolean;
};

const bagianDaftar = ['Kategori', 'StasiunDapur', 'AlasanVoid', 'AlasanPenyesuaian'] as const;

const jumlahProdukContohMaksimal = 100;
const polaHarga = /^\d{1,16}(\.\d{1,2})?$/;
const kelasSel = 'px-2 py-2 align-top whitespace-normal [&_[data-slot=native-select-wrapper]]:w-full';
const kelasKepala = 'px-2 text-label text-teks-sekunder';
const kelasInput = 'h-8 pointer-coarse:h-11 px-2 text-isi';

function KeOpsi(daftar: Pilihan[]) {
    return daftar.map((item) => ({ nilai: item.Nilai, label: item.Label }));
}

/** Usaha retail/grosir menjual barang berstok; F&B umumnya menu tanpa stok (DesainF01 H14). */
function TentukanJenisAwal(modeKasir: string[]): JenisProdukContoh {
    return modeKasir.includes('Retail') || modeKasir.includes('Grosir') ? 'Stok' : 'NonStok';
}

/**
 * Isi bisnis template: mode kasir, fitur, kategori, satuan, pengaturan default, dan produk contoh
 * (BR-P03.5, Konten & Legal).
 */
export default function FormIsiBisnis({ url, isi, pilihan, bolehUbah }: PropsFormIsiBisnis) {
    const formulir = useForm<IsiBisnisTemplate>(isi);
    const galat = formulir.errors as Record<string, string | undefined>;
    const data = formulir.data;
    const AturPengaturan = <K extends keyof IsiBisnisTemplate['Pengaturan']>(
        kunci: K,
        nilai: IsiBisnisTemplate['Pengaturan'][K],
    ) => formulir.setData('Pengaturan', { ...data.Pengaturan, [kunci]: nilai });

    const kategoriTemplate = data.Kategori.map((nama) => nama.trim()).filter((nama) => nama !== '');
    const satuanTemplate = pilihan.Satuan.filter((satuan) => data.KodeSatuan.includes(satuan.Nilai));
    const UbahProdukContoh = (indeks: number, perubahan: Partial<ProdukContohTemplate>) =>
        formulir.setData(
            'ProdukContoh',
            data.ProdukContoh.map((produk, posisi) => (posisi === indeks ? { ...produk, ...perubahan } : produk)),
        );
    const TambahProdukContoh = () =>
        formulir.setData('ProdukContoh', [
            ...data.ProdukContoh,
            {
                Nama: '',
                Kategori: null,
                Harga: '',
                KodeSatuan: data.KodeSatuan[0] ?? '',
                Jenis: TentukanJenisAwal(data.ModeKasir),
            },
        ]);
    const HapusProdukContoh = (indeks: number) =>
        formulir.setData(
            'ProdukContoh',
            data.ProdukContoh.filter((_, posisi) => posisi !== indeks),
        );

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.transform((isian) => {
            const bersih = { ...isian };

            for (const bagian of bagianDaftar) {
                bersih[bagian] = isian[bagian].map((nama) => nama.trim()).filter((nama) => nama !== '');
            }

            bersih.ProdukContoh = isian.ProdukContoh.map((produk) => ({
                ...produk,
                Nama: produk.Nama.trim(),
                Kategori: produk.Kategori === null || produk.Kategori.trim() === '' ? null : produk.Kategori,
                Harga: produk.Harga.trim(),
            }));

            return bersih;
        });
        formulir.put(`${url}/isi-bisnis`, { preserveScroll: true });
    };

    return (
        <Card className="gap-4 rounded-panel shadow-none">
            <CardHeader>
                <CardTitle>
                    <h2 className="text-subjudul font-semibold text-teks-utama">Isi bisnis</h2>
                </CardTitle>
                <CardDescription className="text-keterangan text-teks-sekunder">
                    Diubah oleh Konten & Legal.
                </CardDescription>
            </CardHeader>
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <CardContent className="flex flex-col gap-4">
                    <RingkasanGalat galat={galat} />
                    <fieldset disabled={!bolehUbah} className="grid gap-6 disabled:opacity-90 sm:grid-cols-2">
                        <GrupCentang
                            legenda="Mode kasir"
                            opsi={KeOpsi(pilihan.ModeKasir)}
                            terpilih={data.ModeKasir}
                            saatBerubah={(terpilih) => formulir.setData('ModeKasir', terpilih)}
                            galat={galat.ModeKasir}
                        />
                        <BidangPilihan
                            label="Mode kasir default"
                            nilai={data.ModeKasirDefault ?? ''}
                            kosong="Pilih mode"
                            opsi={pilihan.ModeKasir.filter((mode) => data.ModeKasir.includes(mode.Nilai))}
                            saatBerubah={(nilai) => formulir.setData('ModeKasirDefault', nilai === '' ? null : nilai)}
                            galat={galat.ModeKasirDefault}
                        />
                        <div className="sm:col-span-2">
                            <GrupCentang
                                legenda="Fitur aktif"
                                opsi={pilihan.Fitur.map((fitur) => ({
                                    nilai: fitur.Nilai,
                                    label: `${fitur.Label} | ${fitur.Kelompok}`,
                                }))}
                                terpilih={data.KunciFitur}
                                saatBerubah={(terpilih) => formulir.setData('KunciFitur', terpilih)}
                                galat={galat.KunciFitur}
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <GrupCentang
                                legenda="Satuan default"
                                opsi={KeOpsi(pilihan.Satuan)}
                                terpilih={data.KodeSatuan}
                                saatBerubah={(terpilih) => formulir.setData('KodeSatuan', terpilih)}
                                galat={galat.KodeSatuan}
                            />
                        </div>
                        <BidangDaftarTeks
                            label="Kategori contoh"
                            nilai={data.Kategori}
                            saatBerubah={(nilai) => formulir.setData('Kategori', nilai)}
                            galat={galat.Kategori}
                        />
                        <BidangDaftarTeks
                            label="Stasiun dapur"
                            keterangan="Satu per baris. Kosongkan untuk usaha non-F&B."
                            nilai={data.StasiunDapur}
                            saatBerubah={(nilai) => formulir.setData('StasiunDapur', nilai)}
                            galat={galat.StasiunDapur}
                        />
                        <BidangDaftarTeks
                            label="Alasan void"
                            nilai={data.AlasanVoid}
                            saatBerubah={(nilai) => formulir.setData('AlasanVoid', nilai)}
                            galat={galat.AlasanVoid}
                        />
                        <BidangDaftarTeks
                            label="Alasan penyesuaian stok"
                            nilai={data.AlasanPenyesuaian}
                            saatBerubah={(nilai) => formulir.setData('AlasanPenyesuaian', nilai)}
                            galat={galat.AlasanPenyesuaian}
                        />
                        <div className="sm:col-span-2">
                            <GrupCentang
                                legenda="Laporan unggulan di dasbor"
                                opsi={KeOpsi(pilihan.LaporanUnggulan)}
                                terpilih={data.LaporanUnggulan}
                                saatBerubah={(terpilih) => formulir.setData('LaporanUnggulan', terpilih)}
                                galat={galat.LaporanUnggulan}
                            />
                        </div>
                        <fieldset className="grid gap-4 sm:col-span-2 sm:grid-cols-3">
                            <legend className="mb-2 text-label font-semibold text-teks-utama">
                                Pengaturan default
                            </legend>
                            <BidangTeks
                                label="Kelipatan pembulatan tunai (Rp)"
                                inputMode="numeric"
                                nilai={String(data.Pengaturan.PembulatanTunai.Kelipatan)}
                                saatBerubah={(nilai) =>
                                    AturPengaturan('PembulatanTunai', {
                                        ...data.Pengaturan.PembulatanTunai,
                                        Kelipatan: Number(nilai.replace(/\D/g, '')),
                                    })
                                }
                                galat={galat['Pengaturan.PembulatanTunai.Kelipatan']}
                                required
                            />
                            <BidangPilihan
                                label="Arah pembulatan tunai"
                                nilai={data.Pengaturan.PembulatanTunai.Arah}
                                opsi={pilihan.ArahPembulatan}
                                saatBerubah={(nilai) =>
                                    AturPengaturan('PembulatanTunai', {
                                        ...data.Pengaturan.PembulatanTunai,
                                        Arah: nilai,
                                    })
                                }
                                galat={galat['Pengaturan.PembulatanTunai.Arah']}
                                required
                            />
                            <BidangPilihan
                                label="Metode HPP"
                                nilai={data.Pengaturan.MetodeHpp}
                                opsi={pilihan.MetodeHpp}
                                saatBerubah={(nilai) => AturPengaturan('MetodeHpp', nilai)}
                                galat={galat['Pengaturan.MetodeHpp']}
                                required
                            />
                            <BidangTeks
                                label="Biaya layanan (%)"
                                inputMode="decimal"
                                keterangan="0 sampai 10."
                                nilai={data.Pengaturan.PersenBiayaLayanan}
                                saatBerubah={(nilai) => AturPengaturan('PersenBiayaLayanan', nilai)}
                                galat={galat['Pengaturan.PersenBiayaLayanan']}
                                required
                            />
                            <div className="flex flex-col gap-2 sm:col-span-2">
                                <KotakCentang
                                    label="Biaya layanan masuk DPP pajak"
                                    nilai={data.Pengaturan.BiayaLayananMasukDpp}
                                    saatBerubah={(nilai) => AturPengaturan('BiayaLayananMasukDpp', nilai)}
                                />
                                <KotakCentang
                                    label="Stok boleh minus"
                                    nilai={data.Pengaturan.StokBolehMinus}
                                    saatBerubah={(nilai) => AturPengaturan('StokBolehMinus', nilai)}
                                />
                                <KotakCentang
                                    label="Harga jual sudah termasuk pajak"
                                    nilai={data.Pengaturan.HargaTermasukPajak}
                                    saatBerubah={(nilai) => AturPengaturan('HargaTermasukPajak', nilai)}
                                />
                            </div>
                        </fieldset>
                        <section className="flex flex-col gap-2 sm:col-span-2" aria-labelledby="judul-produk-contoh">
                            <div>
                                <h3 id="judul-produk-contoh" className="text-label font-semibold text-teks-utama">
                                    Produk contoh
                                </h3>
                                <p className="text-keterangan text-teks-sekunder">
                                    Ditawarkan ke tenant di langkah produk awal panduan. Kategori dan satuan diambil
                                    dari isian di atas. Harga dalam Rupiah tanpa titik ribuan, misal 22000.
                                </p>
                            </div>
                            {data.ProdukContoh.length === 0 ? (
                                <p className="text-keterangan text-teks-sekunder">
                                    Belum ada produk contoh. Tenant yang memakai template ini menambah produknya
                                    sendiri.
                                </p>
                            ) : (
                                <TabelForm label="Isi bisnis template sektor" lebar="lebar">
                                    <TableCaption className="sr-only">Produk contoh template</TableCaption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead scope="col" className={kelasKepala}>
                                                Nama produk
                                            </TableHead>
                                            <TableHead scope="col" className={`${kelasKepala} w-44`}>
                                                Kategori
                                            </TableHead>
                                            <TableHead scope="col" className={`${kelasKepala} w-40 text-right`}>
                                                Harga (Rp)
                                            </TableHead>
                                            <TableHead scope="col" className={`${kelasKepala} w-36`}>
                                                Satuan
                                            </TableHead>
                                            <TableHead scope="col" className={`${kelasKepala} w-44`}>
                                                Jenis
                                            </TableHead>
                                            <TableHead scope="col" className={kelasKepala}>
                                                <span className="sr-only">Aksi</span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.ProdukContoh.map((produk, indeks) => {
                                            const awalan = `ProdukContoh.${indeks}`;
                                            const hargaValid = polaHarga.test(produk.Harga.trim());
                                            const kategoriAsing =
                                                produk.Kategori !== null && !kategoriTemplate.includes(produk.Kategori);
                                            const satuanAsing =
                                                produk.KodeSatuan !== '' &&
                                                !satuanTemplate.some((satuan) => satuan.Nilai === produk.KodeSatuan);

                                            return (
                                                <TableRow key={indeks}>
                                                    <TableCell className={kelasSel}>
                                                        <Input
                                                            aria-label={`Nama produk contoh baris ${indeks + 1}`}
                                                            aria-invalid={galat[`${awalan}.Nama`] ? true : undefined}
                                                            className={kelasInput}
                                                            maxLength={150}
                                                            value={produk.Nama}
                                                            onChange={(peristiwa) =>
                                                                UbahProdukContoh(indeks, {
                                                                    Nama: peristiwa.target.value,
                                                                })
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell className={kelasSel}>
                                                        <PilihanCari
                                                            label="Kategori"
                                                            aria-label={`Kategori produk contoh baris ${indeks + 1}`}
                                                            galat={galat[`${awalan}.Kategori`]}
                                                            className={kelasInput}
                                                            nilai={produk.Kategori ?? ''}
                                                            kosong="Tanpa kategori"
                                                            opsi={[
                                                                ...(kategoriAsing
                                                                    ? [
                                                                          {
                                                                              Nilai: produk.Kategori ?? '',
                                                                              Label: `${produk.Kategori ?? ''} (tidak ada di daftar)`,
                                                                          },
                                                                      ]
                                                                    : []),
                                                                ...kategoriTemplate.map((nama) => ({
                                                                    Nilai: nama,
                                                                    Label: nama,
                                                                })),
                                                            ]}
                                                            saatBerubah={(nilai) =>
                                                                UbahProdukContoh(indeks, {
                                                                    Kategori: nilai === '' ? null : nilai,
                                                                })
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell className={kelasSel}>
                                                        <Input
                                                            aria-label={`Harga produk contoh baris ${indeks + 1}`}
                                                            aria-invalid={galat[`${awalan}.Harga`] ? true : undefined}
                                                            className={`${kelasInput} text-right tabular-nums`}
                                                            inputMode="decimal"
                                                            value={produk.Harga}
                                                            onChange={(peristiwa) =>
                                                                UbahProdukContoh(indeks, {
                                                                    Harga: peristiwa.target.value,
                                                                })
                                                            }
                                                        />
                                                        {hargaValid ? (
                                                            <span className="mt-1 block text-right text-keterangan text-teks-sekunder tabular-nums">
                                                                {FormatRupiah(produk.Harga)}
                                                            </span>
                                                        ) : null}
                                                    </TableCell>
                                                    <TableCell className={kelasSel}>
                                                        <PilihanCari
                                                            label="Satuan"
                                                            aria-label={`Satuan produk contoh baris ${indeks + 1}`}
                                                            galat={galat[`${awalan}.KodeSatuan`]}
                                                            className={kelasInput}
                                                            nilai={produk.KodeSatuan}
                                                            kosong="Pilih satuan"
                                                            opsi={[
                                                                ...(satuanAsing
                                                                    ? [
                                                                          {
                                                                              Nilai: produk.KodeSatuan,
                                                                              Label: `${produk.KodeSatuan} (tidak ada di daftar)`,
                                                                          },
                                                                      ]
                                                                    : []),
                                                                ...satuanTemplate,
                                                            ]}
                                                            saatBerubah={(nilai) =>
                                                                UbahProdukContoh(indeks, { KodeSatuan: nilai })
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell className={kelasSel}>
                                                        <PilihanCari
                                                            label="Jenis produk"
                                                            aria-label={`Jenis produk contoh baris ${indeks + 1}`}
                                                            galat={galat[`${awalan}.Jenis`]}
                                                            className={kelasInput}
                                                            nilai={produk.Jenis}
                                                            opsi={pilihan.JenisProdukContoh}
                                                            saatBerubah={(nilai) =>
                                                                UbahProdukContoh(indeks, {
                                                                    Jenis: nilai as JenisProdukContoh,
                                                                })
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell className={`${kelasSel} text-right`}>
                                                        {bolehUbah ? (
                                                            <Tombol
                                                                varian="bahaya"
                                                                onClick={() => HapusProdukContoh(indeks)}
                                                            >
                                                                Hapus
                                                            </Tombol>
                                                        ) : null}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </TabelForm>
                            )}
                            {bolehUbah ? (
                                <div className="flex flex-wrap items-center gap-3">
                                    <Tombol
                                        varian="sekunder"
                                        onClick={TambahProdukContoh}
                                        disabled={data.ProdukContoh.length >= jumlahProdukContohMaksimal}
                                    >
                                        Tambah produk contoh
                                    </Tombol>
                                    <span className="text-keterangan text-teks-sekunder tabular-nums">
                                        {data.ProdukContoh.length} dari {jumlahProdukContohMaksimal} produk
                                    </span>
                                </div>
                            ) : null}
                        </section>
                    </fieldset>
                </CardContent>
                {bolehUbah ? (
                    <CardFooter>
                        <Tombol type="submit" memproses={formulir.processing}>
                            Simpan isi bisnis
                        </Tombol>
                    </CardFooter>
                ) : null}
            </form>
        </Card>
    );
}
