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
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import Panel from '@/Komponen/Kelola/Panel';
import { AlamatPiutang } from '@/Komponen/Piutang/BagianPiutang';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import { Button } from '@/Komponen/Ui/button';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { BandingkanDesimal, CekDesimalValid, JumlahkanDesimal } from '@/Pustaka/HitungDesimal';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PiutangTerbuka, PropsFormPelunasan } from '@/Tipe/Piutang';

const alamat = `${AlamatPiutang}/pelunasan`;

/** Galat lokal alokasi: kosong = tidak dilunasi; tidak boleh negatif atau melebihi sisa piutang. */
export function PeriksaAlokasiPiutang(jumlah: string, piutang: Pick<PiutangTerbuka, 'Sisa'>): string | null {
    if (jumlah === '') {
        return null;
    }

    if (!CekDesimalValid(jumlah) || BandingkanDesimal(jumlah, '0') < 0) {
        return 'Isi jumlah yang valid.';
    }

    return BandingkanDesimal(jumlah, piutang.Sisa) > 0
        ? `Maksimal ${FormatRupiah(piutang.Sisa)} (sisa piutang).`
        : null;
}

/** F-12: pelunasan piutang satu pelanggan untuk satu atau banyak penjualan tempo, penuh atau sebagian. */
export default function HalamanFormPelunasan({
    OpsiPelanggan,
    OpsiAkun,
    UuidPelanggan,
    NamaPelanggan,
    UuidPiutangAwal,
    Piutang,
    HariIni,
}: PropsFormPelunasan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [akun, AturAkun] = useState(OpsiAkun[0]?.Uuid ?? '');
    const [cara, AturCara] = useState<CaraBayar>('KasBank');
    const [giro, AturGiro] = useState<IsianGiro>(GiroKosong);
    const [tanggal, AturTanggal] = useState(HariIni);
    const [catatan, AturCatatan] = useState('');
    const [alokasi, AturAlokasi] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            Piutang.map((p) => [
                p.Uuid,
                UuidPiutangAwal === null || UuidPiutangAwal === p.Uuid ? p.Sisa.replace(/\.00$/, '') : '',
            ]),
        ),
    );
    const [periksa, AturPeriksa] = useState(false);
    const [memproses, AturMemproses] = useState(false);
    const dilunasi = Piutang.filter((p) => {
        const j = alokasi[p.Uuid] ?? '';

        return j !== '' && CekDesimalValid(j) && BandingkanDesimal(j, '0') > 0;
    });
    const total = JumlahkanDesimal(dilunasi.map((p) => alokasi[p.Uuid] ?? '0'));
    const adaGalatAlokasi = Piutang.some((p) => PeriksaAlokasiPiutang(alokasi[p.Uuid] ?? '', p) !== null);
    const opsiPelanggan =
        UuidPelanggan !== null && !OpsiPelanggan.some((p) => p.Uuid === UuidPelanggan)
            ? [...OpsiPelanggan, { Uuid: UuidPelanggan, Nama: NamaPelanggan ?? '' }]
            : OpsiPelanggan;

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturPeriksa(true);

        const galatGiro = cara === 'Giro' ? PeriksaGiro(giro, tanggal) : {};

        if (
            UuidPelanggan === null ||
            (cara === 'KasBank' && akun === '') ||
            Object.keys(galatGiro).length > 0 ||
            dilunasi.length === 0 ||
            adaGalatAlokasi
        ) {
            return;
        }

        router.post(
            alamat,
            {
                UuidPelanggan,
                CaraBayar: cara,
                ...(cara === 'KasBank' ? { UuidAkun: akun } : { Giro: giro }),
                Tanggal: tanggal,
                Catatan: catatan === '' ? null : catatan,
                Alokasi: dilunasi.map((p) => ({ UuidPiutang: p.Uuid, Jumlah: alokasi[p.Uuid] ?? '0' })),
            },
            {
                preserveScroll: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
            },
        );
    };

    return (
        <TataLetakAplikasi judul="Terima pelunasan piutang">
            <DaftarGalatServer
                galat={galat}
                kecuali={[
                    'UuidPelanggan',
                    'UuidAkun',
                    'Giro.NomorGiro',
                    'Giro.NamaBank',
                    'Giro.TanggalJatuhTempo',
                    'Tanggal',
                    'Catatan',
                    ...Piutang.map((p) => `Alokasi.${p.Uuid}`),
                ]}
            />
            <form onSubmit={Simpan} noValidate aria-label="Terima pelunasan piutang" className="flex flex-col gap-4">
                <Panel judul="Pelunasan" idJudul="judul-pelunasan">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <BidangPilihan
                            label="Pelanggan"
                            nilai={UuidPelanggan ?? ''}
                            kosong="Pilih pelanggan"
                            opsi={opsiPelanggan.map((p) => ({ Nilai: p.Uuid, Label: p.Nama }))}
                            saatBerubah={(uuid) =>
                                router.get(`${alamat}/buat`, uuid === '' ? {} : { pelanggan: uuid }, {
                                    preserveState: false,
                                })
                            }
                            galat={
                                galat.UuidPelanggan ??
                                (periksa && UuidPelanggan === null ? 'Pilih pelanggan.' : undefined)
                            }
                            required
                        />
                        <BidangCaraBayar label="Diterima dalam bentuk" cara={cara} saatCara={AturCara} />
                        {cara === 'KasBank' ? (
                            <BidangPilihan
                                label="Diterima di akun"
                                nilai={akun}
                                kosong="Pilih akun kas/bank"
                                opsi={OpsiAkun.map((a) => ({ Nilai: a.Uuid, Label: a.Nama, Keterangan: a.Kode }))}
                                saatBerubah={AturAkun}
                                galat={galat.UuidAkun ?? (periksa && akun === '' ? 'Pilih akun kas/bank.' : undefined)}
                                required
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
                            id="tanggal-pelunasan"
                            label="Tanggal terima"
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
                </Panel>

                <Panel
                    judul="Penjualan yang dilunasi"
                    idJudul="judul-alokasi-piutang"
                    keterangan="Isi jumlah per penjualan. Kosongkan penjualan yang belum dibayar sekarang."
                >
                    {UuidPelanggan === null ? (
                        <p className="text-isi text-teks-sekunder">Pilih pelanggan untuk melihat piutang terbukanya.</p>
                    ) : Piutang.length === 0 ? (
                        <Pemberitahuan jenis="info" judul="Tidak ada piutang terbuka">
                            Semua penjualan tempo pelanggan ini sudah lunas.
                        </Pemberitahuan>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {Piutang.map((p) => (
                                <li
                                    key={p.Uuid}
                                    className="grid items-end gap-2 rounded-panel border border-garis p-3 sm:grid-cols-[1fr_14rem]"
                                >
                                    <span className="flex flex-col">
                                        <span className="font-mono font-semibold break-all">{p.Nomor}</span>
                                        <span className="text-keterangan text-teks-sekunder">
                                            {FormatTanggal(p.TanggalBisnis)} | jatuh tempo {FormatTanggal(p.JatuhTempo)}{' '}
                                            | sisa {FormatRupiah(p.Sisa)} dari {FormatRupiah(p.Jumlah)}
                                        </span>
                                    </span>
                                    <BidangUang
                                        label={`Lunasi ${p.Nomor}`}
                                        nilai={alokasi[p.Uuid] ?? ''}
                                        saatBerubah={(nilai) => AturAlokasi((lama) => ({ ...lama, [p.Uuid]: nilai }))}
                                        galat={
                                            galat[`Alokasi.${p.Uuid}`] ??
                                            (periksa
                                                ? (PeriksaAlokasiPiutang(alokasi[p.Uuid] ?? '', p) ?? undefined)
                                                : undefined)
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                    {periksa && UuidPelanggan !== null && dilunasi.length === 0 ? (
                        <p className="text-keterangan font-semibold text-bahaya">
                            Isi jumlah pelunasan minimal satu penjualan.
                        </p>
                    ) : null}
                    {galat.Alokasi ? (
                        <p className="text-keterangan font-semibold text-bahaya">{galat.Alokasi}</p>
                    ) : null}
                    <dl className="ml-auto flex w-full max-w-md justify-between gap-3 border-t border-garis pt-3">
                        <dt className="font-semibold">Total diterima</dt>
                        <dd className="font-semibold tabular-nums">{FormatRupiah(total)}</dd>
                    </dl>
                </Panel>

                <BilahAksiForm>
                    <Tombol type="submit" memproses={memproses}>
                        Simpan pelunasan
                    </Tombol>
                    <Button asChild variant="outline">
                        <Link href={AlamatPiutang}>Batal</Link>
                    </Button>
                </BilahAksiForm>
            </form>
        </TataLetakAplikasi>
    );
}
