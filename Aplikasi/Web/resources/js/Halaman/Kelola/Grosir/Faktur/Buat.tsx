import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BilahAksiForm from '@/Komponen/Formulir/BilahAksiForm';
import Tombol from '@/Komponen/Formulir/Tombol';
import { AlamatGrosir, HalamanGrosir } from '@/Komponen/Grosir/BagianDokumenGrosir';
import Panel from '@/Komponen/Kelola/Panel';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import { Button } from '@/Komponen/Ui/button';
import { Checkbox } from '@/Komponen/Ui/checkbox';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { JumlahkanDesimal } from '@/Pustaka/HitungDesimal';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsBuatFaktur } from '@/Tipe/Grosir';

const alamat = `${AlamatGrosir}/faktur`;

/**
 * Menerbitkan faktur penjualan grosir dari surat jalan yang belum difakturkan (BR-12.4).
 *
 * Batasnya ditegakkan server, bukan di sini: satu pelanggan, satu outlet, satu bulan kalender (UU PPN Pasal 13 ayat
 * 2a), dan tarif PPN seragam. Halaman ini membantu memilih dengan menampilkan pelanggan & bulan penyerahan tiap baris,
 * lalu server menolak kombinasi yang tidak sah dengan alasan yang menyebut sebabnya.
 */
export default function HalamanBuatFakturGrosir({ SuratJalan, HariIni, Izin }: PropsBuatFaktur) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [dipilih, AturDipilih] = useState<string[]>([]);
    const [tanggal, AturTanggal] = useState(HariIni);
    const [nomorPajak, AturNomorPajak] = useState('');
    const [memproses, AturMemproses] = useState(false);

    const baris = SuratJalan.Data;
    const terpilih = baris.filter((sj) => dipilih.includes(sj.Uuid));
    const total = terpilih.length === 0 ? '0' : JumlahkanDesimal(terpilih.map((sj) => sj.Total));

    const Alihkan = (uuid: string) =>
        AturDipilih((lama) => (lama.includes(uuid) ? lama.filter((u) => u !== uuid) : [...lama, uuid]));

    const Kirim = () =>
        router.post(
            alamat,
            { UuidSuratJalan: dipilih, Tanggal: tanggal, NomorFakturPajak: nomorPajak === '' ? null : nomorPajak },
            { onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );

    return (
        <HalamanGrosir
            judul="Buat faktur penjualan"
            keterangan="Pilih surat jalan yang akan ditagihkan. Satu faktur hanya untuk satu pelanggan, satu outlet, dan satu bulan kalender penyerahan."
            izin={Izin}
            objek="faktur penjualan"
        >
            <Pemberitahuan jenis="info" judul="Faktur tidak menambah pendapatan">
                Pendapatan, HPP, dan PPN sudah diakui saat barangnya diserahkan. Faktur hanya memindahkan nilainya dari
                Piutang Belum Difakturkan ke Piutang Usaha, lalu membuat piutang yang jatuh temponya dihitung dari
                termin pelanggan.
            </Pemberitahuan>

            <Panel judul="Surat jalan belum difakturkan">
                {baris.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">
                        Tidak ada surat jalan yang menunggu difakturkan. Semua penyerahan sudah ditagihkan.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {baris.map((sj) => (
                            <li key={sj.Uuid} className="flex items-start gap-3 rounded-panel border border-garis p-3">
                                <Checkbox
                                    id={`sj-${sj.Uuid}`}
                                    checked={dipilih.includes(sj.Uuid)}
                                    onCheckedChange={() => Alihkan(sj.Uuid)}
                                    aria-label={`Pilih ${sj.Nomor}`}
                                />
                                <label htmlFor={`sj-${sj.Uuid}`} className="flex min-w-0 flex-col gap-0.5">
                                    <span className="font-mono font-semibold break-all text-teks-utama">
                                        {sj.Nomor}
                                    </span>
                                    <span className="text-keterangan text-teks-sekunder">
                                        {sj.NamaPelanggan} | diserahkan {FormatTanggal(sj.Tanggal)} |{' '}
                                        {FormatRupiah(sj.Total)}
                                    </span>
                                </label>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>

            <Panel judul="Faktur">
                <div className="grid gap-4 sm:grid-cols-2">
                    <PemilihTanggal
                        label="Tanggal faktur"
                        nilai={tanggal}
                        saatBerubah={AturTanggal}
                        galat={props.errors.Tanggal}
                        required
                    />
                    <BidangTeks
                        label="Nomor Faktur Pajak"
                        nilai={nomorPajak}
                        saatBerubah={AturNomorPajak}
                        keterangan="Boleh dikosongkan dan diisi nanti setelah nomornya didapat dari e-Faktur."
                        galat={props.errors.NomorFakturPajak}
                        maxLength={30}
                        kode
                    />
                </div>
                <p className="mt-3 text-isi text-teks-utama">
                    {terpilih.length} surat jalan dipilih | total tagihan{' '}
                    <span className="font-semibold tabular-nums">{FormatRupiah(total)}</span>
                </p>
            </Panel>

            <BilahAksiForm>
                <Button asChild variant="outline" type="button">
                    <Link href={alamat}>Batal</Link>
                </Button>
                <Tombol type="button" onClick={Kirim} memproses={memproses} disabled={terpilih.length === 0}>
                    Terbitkan faktur
                </Tombol>
            </BilahAksiForm>
        </HalamanGrosir>
    );
}
