import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import {
    BidangCaraBayar,
    BidangIsianGiro,
    GiroKosong,
    PeriksaGiro,
    type CaraBayar,
    type IsianGiro,
} from '@/Komponen/Akuntansi/BidangGiro';
import BilahAksiForm from '@/Komponen/Formulir/BilahAksiForm';
import BidangBerkas from '@/Komponen/Formulir/BidangBerkas';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import Panel from '@/Komponen/Kelola/Panel';
import { AlamatPembelian } from '@/Komponen/Pembelian/BagianDokumenPembelian';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import { Button } from '@/Komponen/Ui/button';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { BandingkanDesimal, CekDesimalValid, JumlahkanDesimal } from '@/Pustaka/HitungDesimal';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { FakturTerbuka, PropsFormPembayaran } from '@/Tipe/Pembelian';

const alamat = `${AlamatPembelian}/pembayaran`;

/** Galat lokal alokasi: kosong/0 = tidak dibayar; tidak boleh melebihi sisa faktur. */
export function PeriksaAlokasi(jumlah: string, faktur: Pick<FakturTerbuka, 'Sisa'>): string | null {
    if (jumlah === '') {
        return null;
    }

    if (!CekDesimalValid(jumlah) || BandingkanDesimal(jumlah, '0') < 0) {
        return 'Isi jumlah yang valid.';
    }

    return BandingkanDesimal(jumlah, faktur.Sisa) > 0 ? `Maksimal ${FormatRupiah(faktur.Sisa)} (sisa hutang).` : null;
}

/** F-04 fase 1: pembayaran hutang ke satu pemasok untuk satu atau banyak faktur, penuh atau sebagian (J-04.4). */
export default function HalamanFormPembayaran({
    OpsiPemasok,
    OpsiAkun,
    UuidPemasok,
    UuidFakturAwal,
    Faktur,
    HariIni,
    Lampiran,
}: PropsFormPembayaran) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [akun, AturAkun] = useState(OpsiAkun[0]?.Uuid ?? '');
    const [cara, AturCara] = useState<CaraBayar>('KasBank');
    const [giro, AturGiro] = useState<IsianGiro>(GiroKosong);
    const [tanggal, AturTanggal] = useState(HariIni);
    const [catatan, AturCatatan] = useState('');
    const [berkas, AturBerkas] = useState<File[]>([]);
    const [alokasi, AturAlokasi] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            Faktur.map((f) => [
                f.Uuid,
                UuidFakturAwal === null || UuidFakturAwal === f.Uuid ? f.Sisa.replace(/\.00$/, '') : '',
            ]),
        ),
    );
    const [periksa, AturPeriksa] = useState(false);
    const [memproses, AturMemproses] = useState(false);
    const dibayar = Faktur.filter((f) => {
        const j = alokasi[f.Uuid] ?? '';

        return j !== '' && CekDesimalValid(j) && BandingkanDesimal(j, '0') > 0;
    });
    const total = JumlahkanDesimal(dibayar.map((f) => alokasi[f.Uuid] ?? '0'));
    const adaGalatAlokasi = Faktur.some((f) => PeriksaAlokasi(alokasi[f.Uuid] ?? '', f) !== null);

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturPeriksa(true);

        const galatGiro = cara === 'Giro' ? PeriksaGiro(giro, tanggal) : {};

        if (
            UuidPemasok === null ||
            (cara === 'KasBank' && akun === '') ||
            Object.keys(galatGiro).length > 0 ||
            dibayar.length === 0 ||
            adaGalatAlokasi
        ) {
            return;
        }

        router.post(
            alamat,
            {
                UuidPemasok,
                CaraBayar: cara,
                ...(cara === 'KasBank' ? { UuidAkun: akun } : { Giro: giro }),
                Tanggal: tanggal,
                Catatan: catatan === '' ? null : catatan,
                Lampiran: berkas[0] ?? null,
                Alokasi: dibayar.map((f) => ({ UuidFaktur: f.Uuid, Jumlah: alokasi[f.Uuid] ?? '0' })),
            },
            {
                preserveScroll: true,
                forceFormData: berkas.length > 0,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
            },
        );
    };

    return (
        <TataLetakAplikasi judul="Bayar hutang">
            <DaftarGalatServer
                galat={galat}
                kecuali={[
                    'UuidPemasok',
                    'UuidAkun',
                    'Giro.NomorGiro',
                    'Giro.NamaBank',
                    'Giro.TanggalJatuhTempo',
                    'Tanggal',
                    'Catatan',
                    'Lampiran',
                ]}
            />
            <form onSubmit={Simpan} noValidate aria-label="Bayar hutang" className="flex flex-col gap-4">
                <Panel judul="Pembayaran" idJudul="judul-pembayaran">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <BidangPilihan
                            label="Pemasok"
                            nilai={UuidPemasok ?? ''}
                            kosong="Pilih pemasok"
                            opsi={OpsiPemasok.map((p) => ({ Nilai: p.Uuid, Label: p.Nama, Keterangan: p.Kode }))}
                            saatBerubah={(uuid) =>
                                router.get(`${alamat}/buat`, uuid === '' ? {} : { pemasok: uuid }, {
                                    preserveState: false,
                                })
                            }
                            required
                            galat={
                                galat.UuidPemasok ?? (periksa && UuidPemasok === null ? 'Pilih pemasok.' : undefined)
                            }
                        />
                        <BidangCaraBayar label="Dibayar dengan" cara={cara} saatCara={AturCara} />
                        {cara === 'KasBank' ? (
                            <BidangPilihan
                                label="Dibayar dari akun"
                                nilai={akun}
                                kosong="Pilih akun kas/bank"
                                opsi={OpsiAkun.map((a) => ({ Nilai: a.Uuid, Label: a.Nama, Keterangan: a.Kode }))}
                                saatBerubah={AturAkun}
                                required
                                galat={galat.UuidAkun ?? (periksa && akun === '' ? 'Pilih akun kas/bank.' : undefined)}
                            />
                        ) : (
                            <BidangIsianGiro
                                giro={giro}
                                saatBerubah={AturGiro}
                                tanggalMin={tanggal}
                                galat={{
                                    NomorGiro:
                                        galat['Giro.NomorGiro'] ??
                                        (periksa ? PeriksaGiro(giro, tanggal).NomorGiro : undefined),
                                    NamaBank:
                                        galat['Giro.NamaBank'] ??
                                        (periksa ? PeriksaGiro(giro, tanggal).NamaBank : undefined),
                                    TanggalJatuhTempo:
                                        galat['Giro.TanggalJatuhTempo'] ??
                                        (periksa ? PeriksaGiro(giro, tanggal).TanggalJatuhTempo : undefined),
                                }}
                            />
                        )}
                        <PemilihTanggal
                            id="tanggal-pembayaran"
                            label="Tanggal bayar"
                            nilai={tanggal}
                            max={HariIni}
                            required
                            saatBerubah={AturTanggal}
                            galat={galat.Tanggal}
                        />
                    </div>
                    <BidangTeksPanjang
                        label="Catatan (opsional)"
                        nilai={catatan}
                        saatBerubah={AturCatatan}
                        galat={galat.Catatan}
                        baris={2}
                        maksimal={500}
                    />
                    <BidangBerkas
                        label="Bukti transfer (opsional)"
                        berkas={berkas}
                        saatBerubah={AturBerkas}
                        ekstensi={Lampiran.Ekstensi}
                        maksimal={1}
                        ukuranMaksimalKb={Lampiran.UkuranMaksimalKb}
                        galat={galat.Lampiran}
                    />
                </Panel>

                <Panel
                    judul="Faktur yang dibayar"
                    idJudul="judul-alokasi"
                    keterangan="Isi jumlah per faktur. Kosongkan faktur yang tidak dibayar sekarang."
                >
                    {UuidPemasok === null ? (
                        <p className="text-isi text-teks-sekunder">Pilih pemasok untuk melihat faktur terbukanya.</p>
                    ) : Faktur.length === 0 ? (
                        <Pemberitahuan jenis="info" judul="Tidak ada hutang terbuka">
                            Semua faktur pemasok ini sudah lunas.
                        </Pemberitahuan>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {Faktur.map((f, i) => (
                                <li
                                    key={f.Uuid}
                                    className="grid items-end gap-2 rounded-panel border border-garis p-3 sm:grid-cols-[1fr_14rem]"
                                >
                                    <span className="flex flex-col">
                                        <span className="font-mono font-semibold">{f.Nomor}</span>
                                        <span className="text-keterangan text-teks-sekunder">
                                            No. pemasok {f.NomorFakturPemasok} | jatuh tempo{' '}
                                            {FormatTanggal(f.JatuhTempo)} | sisa {FormatRupiah(f.Sisa)} dari{' '}
                                            {FormatRupiah(f.Total)}
                                        </span>
                                    </span>
                                    <BidangUang
                                        label={`Bayar ${f.Nomor}`}
                                        nilai={alokasi[f.Uuid] ?? ''}
                                        saatBerubah={(nilai) => AturAlokasi((lama) => ({ ...lama, [f.Uuid]: nilai }))}
                                        galat={
                                            galat[`Alokasi.${String(i)}.Jumlah`] ??
                                            (periksa
                                                ? (PeriksaAlokasi(alokasi[f.Uuid] ?? '', f) ?? undefined)
                                                : undefined)
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                    {periksa && UuidPemasok !== null && dibayar.length === 0 ? (
                        <p className="text-keterangan font-semibold text-bahaya">
                            Isi jumlah bayar minimal satu faktur.
                        </p>
                    ) : null}
                    {galat.Alokasi ? (
                        <p className="text-keterangan font-semibold text-bahaya">{galat.Alokasi}</p>
                    ) : null}
                    <dl className="ml-auto flex w-full max-w-md justify-between gap-3 border-t border-garis pt-3">
                        <dt className="font-semibold">Total dibayar</dt>
                        <dd className="font-semibold tabular-nums">{FormatRupiah(total)}</dd>
                    </dl>
                </Panel>

                <BilahAksiForm>
                    <Tombol type="submit" memproses={memproses}>
                        Simpan pembayaran
                    </Tombol>
                    <Button asChild variant="outline">
                        <Link href={`${AlamatPembelian}/hutang`}>Batal</Link>
                    </Button>
                </BilahAksiForm>
            </form>
        </TataLetakAplikasi>
    );
}
