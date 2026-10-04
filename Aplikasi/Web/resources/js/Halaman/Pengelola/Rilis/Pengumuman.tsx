import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import AksiHalaman from '@/Komponen/Kelola/AksiHalaman';
import TabRilis from '@/Komponen/Pengelola/TabRilis';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import PemilihTanggalWaktu from '@/Komponen/Tanggal/PemilihTanggalWaktu';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { UbahIsoKeWaktuLokal, UbahWaktuLokalKeIsoUtc } from '@/Pustaka/Tanggal';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import {
    IzinPengelola,
    PunyaIzin,
    type BarisPengumumanPlatform,
    type JenisPengumumanPlatform,
    type Pilihan,
    type PropsBersamaPengelola,
    type PropsPengumumanPlatform,
} from '@/Tipe/Pengelola';

const alamat = '/pengumuman';

const JenisStatus: Record<BarisPengumumanPlatform['Status'], 'netral' | 'sukses' | 'bahaya'> = {
    Draf: 'netral',
    Terbit: 'sukses',
    Dicabut: 'bahaya',
};

/** Ringkasan sasaran dalam satu kalimat: kosong = semua. */
export function RingkasSasaran(
    p: BarisPengumumanPlatform,
    opsi: Omit<PropsPengumumanPlatform, 'Pengumuman' | 'OpsiJenis'>,
): string {
    const AmbilLabel = (nilai: string[], daftar: Pilihan[]) =>
        nilai.map((n) => daftar.find((o) => o.Nilai === n)?.Label ?? n).join(', ');
    const s = p.Sasaran;
    const bagian = [
        s.Platform.length > 0 ? AmbilLabel(s.Platform, opsi.OpsiPlatform) : 'semua platform',
        s.KodePaket.length > 0 ? `paket ${AmbilLabel(s.KodePaket, opsi.OpsiPaket)}` : 'semua paket',
        s.Sektor.length > 0 ? `sektor ${AmbilLabel(s.Sektor, opsi.OpsiSektor)}` : 'semua sektor',
    ];

    if (s.VersiMinimal !== null || s.VersiMaksimal !== null) {
        bagian.push(`versi ${s.VersiMinimal ?? '…'}–${s.VersiMaksimal ?? '…'}`);
    }

    return bagian.join(' | ');
}

type Dialog =
    { jenis: 'simpan'; awal: BarisPengumumanPlatform | null } | { jenis: 'cabut'; p: BarisPengumumanPlatform };

/**
 * P-10 PGL-19 pengumuman platform: banner info, "Yang baru", pemeliharaan terjadwal, dan penting untuk back-office &
 * aplikasi kasir, bersasaran paket, sektor, platform, dan versi. Draf bisa diubah; yang terbit hanya bisa dicabut.
 */
export default function HalamanPengumumanPlatform(props: PropsPengumumanPlatform) {
    const { props: bersama } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(bersama.Pengguna, IzinPengelola.RilisKelola);
    const [dialog, AturDialog] = useState<Dialog | null>(null);
    const [memproses, AturMemproses] = useState(false);
    const Tutup = () => AturDialog(null);

    const kolom: KolomTabel<BarisPengumumanPlatform>[] = [
        {
            id: 'Judul',
            accessorKey: 'Judul',
            header: 'Pengumuman',
            meta: { label: 'Pengumuman', prioritas: 'utama', wajib: true },
            cell: ({ row: { original: p } }) => (
                <span className="flex flex-col">
                    <span className="font-semibold break-words text-teks-utama">{p.Judul}</span>
                    <span className="text-label text-teks-sekunder">{p.LabelJenis}</span>
                </span>
            ),
        },
        {
            id: 'Status',
            accessorKey: 'Status',
            header: 'Status',
            meta: { label: 'Status', prioritas: 'utama' },
            cell: ({ row: { original: p } }) => <LabelStatus jenis={JenisStatus[p.Status]} teks={p.LabelStatus} />,
        },
        {
            id: 'TampilMulai',
            accessorKey: 'TampilMulai',
            header: 'Masa tampil',
            meta: { label: 'Masa tampil', prioritas: 'penting', kelasSel: 'text-teks-sekunder' },
            cell: ({ row: { original: p } }) =>
                `${FormatTanggalWaktu(p.TampilMulai)} – ${FormatTanggalWaktu(p.TampilSampai)}`,
        },
        {
            id: 'Sasaran',
            header: 'Sasaran',
            enableSorting: false,
            meta: { label: 'Sasaran', prioritas: 'rendah' },
            cell: ({ row }) => <span className="break-words">{RingkasSasaran(row.original, props)}</span>,
        },
    ];

    const Terbitkan = (p: BarisPengumumanPlatform) =>
        router.post(
            `${alamat}/${p.Uuid}/terbitkan`,
            {},
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );

    return (
        <TataLetakPengelola judul="Rilis aplikasi">
            <TabRilis />
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Pengumuman tampil sebagai banner di back-office dan aplikasi kasir yang cocok dengan sasarannya. Penting
                dan Pemeliharaan tidak bisa ditutup pengguna; Info dan Yang baru bisa. Umumkan pemeliharaan paling
                lambat sehari sebelumnya.
            </p>
            <AksiHalaman>
                {bolehKelola ? (
                    <Tombol memproses={memproses} onClick={() => AturDialog({ jenis: 'simpan', awal: null })}>
                        Buat pengumuman
                    </Tombol>
                ) : null}
            </AksiHalaman>
            <TabelData
                id="pengelola-pengumuman"
                label="Pengumuman platform"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: props.Pengumuman }}
                ambilIdBaris={(p) => p.Uuid}
                labelBaris={(p) => p.Judul}
                cari="Cari judul"
                saring={[
                    {
                        id: 'Status',
                        label: 'Status',
                        jenis: 'pilihanBanyak',
                        opsi: [
                            { nilai: 'Draf', label: 'Draf' },
                            { nilai: 'Terbit', label: 'Terbit' },
                            { nilai: 'Dicabut', label: 'Dicabut' },
                        ],
                    },
                ]}
                {...(bolehKelola
                    ? {
                          aksiBaris: (p: BarisPengumumanPlatform) =>
                              p.Status === 'Dicabut' ? null : (
                                  <>
                                      {p.Status === 'Draf' ? (
                                          <>
                                              <DropdownMenuItem
                                                  onSelect={() => AturDialog({ jenis: 'simpan', awal: p })}
                                              >
                                                  Ubah draf
                                              </DropdownMenuItem>
                                              <DropdownMenuItem onSelect={() => Terbitkan(p)}>
                                                  Terbitkan
                                              </DropdownMenuItem>
                                          </>
                                      ) : null}
                                      <DropdownMenuItem onSelect={() => AturDialog({ jenis: 'cabut', p })}>
                                          {p.Status === 'Draf' ? 'Buang draf' : 'Cabut pengumuman'}
                                      </DropdownMenuItem>
                                  </>
                              ),
                      }
                    : {})}
                kosong={{ ilustrasi: true, judul: 'Belum ada pengumuman platform.' }}
            />
            {dialog?.jenis === 'simpan' ? <FormPengumuman awal={dialog.awal} opsi={props} saatSelesai={Tutup} /> : null}
            {dialog?.jenis === 'cabut' ? <FormCabut p={dialog.p} saatSelesai={Tutup} /> : null}
        </TataLetakPengelola>
    );
}

function FormPengumuman({
    awal,
    opsi,
    saatSelesai,
}: {
    awal: BarisPengumumanPlatform | null;
    opsi: PropsPengumumanPlatform;
    saatSelesai: () => void;
}) {
    const { props } = usePage<PropsBersamaPengelola>();
    const galat = props.errors;
    const [judul, AturJudul] = useState(awal?.Judul ?? '');
    const [isi, AturIsi] = useState(awal?.Isi ?? '');
    const [jenis, AturJenis] = useState<JenisPengumumanPlatform>(awal?.Jenis ?? 'Info');
    const [tautan, AturTautan] = useState(awal?.Tautan ?? '');
    const [tampilMulai, AturTampilMulai] = useState(UbahIsoKeWaktuLokal(awal?.TampilMulai ?? null));
    const [tampilSampai, AturTampilSampai] = useState(UbahIsoKeWaktuLokal(awal?.TampilSampai ?? null));
    const [pMulai, AturPMulai] = useState(UbahIsoKeWaktuLokal(awal?.PemeliharaanMulai ?? null));
    const [pSelesai, AturPSelesai] = useState(UbahIsoKeWaktuLokal(awal?.PemeliharaanSelesai ?? null));
    const [paket, AturPaket] = useState<string[]>(awal?.Sasaran.KodePaket ?? []);
    const [sektor, AturSektor] = useState<string[]>(awal?.Sasaran.Sektor ?? []);
    const [platform, AturPlatform] = useState<string[]>(awal?.Sasaran.Platform ?? []);
    const [versiMin, AturVersiMin] = useState(awal?.Sasaran.VersiMinimal ?? '');
    const [versiMaks, AturVersiMaks] = useState(awal?.Sasaran.VersiMaksimal ?? '');
    const [memproses, AturMemproses] = useState(false);

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const data = {
            Judul: judul,
            Isi: isi,
            Jenis: jenis,
            Tautan: tautan.trim() === '' ? null : tautan.trim(),
            TampilMulai: UbahWaktuLokalKeIsoUtc(tampilMulai),
            TampilSampai: UbahWaktuLokalKeIsoUtc(tampilSampai),
            PemeliharaanMulai: jenis === 'Pemeliharaan' ? UbahWaktuLokalKeIsoUtc(pMulai) : null,
            PemeliharaanSelesai: jenis === 'Pemeliharaan' ? UbahWaktuLokalKeIsoUtc(pSelesai) : null,
            Sasaran: {
                KodePaket: paket,
                Sektor: sektor,
                Platform: platform,
                VersiMinimal: versiMin.trim() === '' ? null : versiMin.trim(),
                VersiMaksimal: versiMaks.trim() === '' ? null : versiMaks.trim(),
            },
        };
        const pilihan = {
            preserveScroll: true,
            onStart: () => AturMemproses(true),
            onFinish: () => AturMemproses(false),
            onSuccess: saatSelesai,
        };

        if (awal) {
            router.put(`${alamat}/${awal.Uuid}`, data, pilihan);
        } else {
            router.post(alamat, data, pilihan);
        }
    };

    return (
        <DialogFormulir
            judul={awal ? `Ubah draf ${awal.Judul}` : 'Buat pengumuman'}
            keterangan="Disimpan sebagai draf. Terbitkan dari daftar setelah diperiksa."
            jenis="panel"
            lebar="lebar"
            galatUmum={galat.Umum}
            saatTutup={saatSelesai}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <div className="sm:col-span-2">
                    <BidangTeks label="Judul" nilai={judul} saatBerubah={AturJudul} galat={galat.Judul} required />
                </div>
                <BidangPilihan
                    label="Jenis"
                    nilai={jenis}
                    opsi={opsi.OpsiJenis}
                    saatBerubah={(n) => AturJenis(n as JenisPengumumanPlatform)}
                    galat={galat.Jenis}
                    required
                />
                <BidangTeks
                    label="Tautan (opsional)"
                    nilai={tautan}
                    saatBerubah={AturTautan}
                    galat={galat.Tautan}
                    keterangan="Halaman bantuan atau catatan rilis, diawali https://."
                />
                <div className="sm:col-span-2">
                    <BidangTeksPanjang
                        label="Isi"
                        nilai={isi}
                        saatBerubah={AturIsi}
                        galat={galat.Isi}
                        baris={4}
                        maksimal={1000}
                        required
                    />
                </div>
                <PemilihTanggalWaktu
                    label="Mulai tampil"
                    nilai={tampilMulai}
                    saatBerubah={AturTampilMulai}
                    galat={galat.TampilMulai}
                    jamBawaan="08:00"
                />
                <PemilihTanggalWaktu
                    label="Selesai tampil"
                    nilai={tampilSampai}
                    saatBerubah={AturTampilSampai}
                    galat={galat.TampilSampai}
                    jamBawaan="23:59"
                />
                {jenis === 'Pemeliharaan' ? (
                    <>
                        <PemilihTanggalWaktu
                            label="Pemeliharaan mulai"
                            nilai={pMulai}
                            saatBerubah={AturPMulai}
                            galat={galat.PemeliharaanMulai}
                            jamBawaan="23:00"
                        />
                        <PemilihTanggalWaktu
                            label="Pemeliharaan selesai"
                            nilai={pSelesai}
                            saatBerubah={AturPSelesai}
                            galat={galat.PemeliharaanSelesai}
                            jamBawaan="01:00"
                        />
                    </>
                ) : null}
                <div className="sm:col-span-2">
                    <GrupCentang
                        legenda="Platform (kosong = semua)"
                        opsi={opsi.OpsiPlatform.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                        terpilih={platform}
                        saatBerubah={AturPlatform}
                    />
                </div>
                <div className="sm:col-span-2">
                    <GrupCentang
                        legenda="Paket (kosong = semua)"
                        opsi={opsi.OpsiPaket.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                        terpilih={paket}
                        saatBerubah={AturPaket}
                    />
                </div>
                <div className="sm:col-span-2">
                    <GrupCentang
                        legenda="Sektor (kosong = semua)"
                        opsi={opsi.OpsiSektor.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                        terpilih={sektor}
                        saatBerubah={AturSektor}
                        galat={galat.Sasaran}
                    />
                </div>
                <BidangTeks
                    label="Versi kasir minimal (opsional)"
                    nilai={versiMin}
                    saatBerubah={AturVersiMin}
                    galat={galat['Sasaran.VersiMinimal']}
                    keterangan="Misal 1.4.0. Back-office web tidak berversi."
                    kode
                />
                <BidangTeks
                    label="Versi kasir maksimal (opsional)"
                    nilai={versiMaks}
                    saatBerubah={AturVersiMaks}
                    galat={galat['Sasaran.VersiMaksimal']}
                    kode
                />
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={memproses}>
                        Simpan draf
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

function FormCabut({ p, saatSelesai }: { p: BarisPengumumanPlatform; saatSelesai: () => void }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const [alasan, AturAlasan] = useState('');
    const [memproses, AturMemproses] = useState(false);

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.post(
            `${alamat}/${p.Uuid}/cabut`,
            { Alasan: alasan },
            {
                preserveScroll: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
                onSuccess: saatSelesai,
            },
        );
    };

    return (
        <DialogFormulir
            judul={p.Status === 'Draf' ? `Buang draf ${p.Judul}` : `Cabut ${p.Judul}`}
            keterangan="Banner langsung hilang dari back-office dan aplikasi kasir (paling lambat satu menit)."
            galatUmum={props.errors.Umum}
            saatTutup={saatSelesai}
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeksPanjang
                    label="Alasan"
                    nilai={alasan}
                    saatBerubah={AturAlasan}
                    galat={props.errors.Alasan}
                    baris={2}
                    maksimal={255}
                    required
                />
                <DialogFooter className="sm:justify-start">
                    <Tombol type="submit" varian="bahaya" memproses={memproses}>
                        {p.Status === 'Draf' ? 'Buang draf' : 'Cabut pengumuman'}
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Kembali
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}
