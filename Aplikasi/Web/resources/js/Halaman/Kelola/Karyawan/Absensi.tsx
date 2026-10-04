import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangOutlet from '@/Komponen/Formulir/BidangOutlet';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import PanelKalibrasiWajah from '@/Komponen/Karyawan/PanelKalibrasiWajah';
import AksiHalaman from '@/Komponen/Kelola/AksiHalaman';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { DefinisiSaring, KolomTabel } from '@/Komponen/TabelData/Tipe';
import PemilihJam from '@/Komponen/Tanggal/PemilihJam';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { TulisTanggal } from '@/Pustaka/Tanggal';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { BarisAbsensi, OpsiUuidNama, PropsAbsensi, StatusKehadiran } from '@/Tipe/Karyawan';

const alamat = '/kelola/karyawan/absensi';

/** Durasi menit → `7 j 45 m`. */
export function FormatDurasiMenit(menit: number | null): string {
    if (menit === null) {
        return '—';
    }

    const jam = Math.floor(menit / 60);
    const sisa = menit % 60;

    return jam === 0 ? `${sisa} m` : `${jam} j ${sisa} m`;
}

function JenisStatus(status: StatusKehadiran): 'sukses' | 'peringatan' | 'bahaya' | 'netral' {
    return status === 'TepatWaktu'
        ? 'sukses'
        : status === 'Terlambat'
          ? 'peringatan'
          : status === 'BelumKeluar'
            ? 'bahaya'
            : 'netral';
}

function TautanSwafoto({ a, jenis }: { a: BarisAbsensi; jenis: 'masuk' | 'keluar' }) {
    const ada = jenis === 'masuk' ? a.AdaSwafotoMasuk : a.AdaSwafotoKeluar;

    return ada ? (
        <a
            href={`${alamat}/${a.Uuid}/swafoto/${jenis}`}
            target="_blank"
            rel="noreferrer"
            className="text-keterangan text-brand underline"
        >
            Swafoto {jenis}
        </a>
    ) : null;
}

const kolom: KolomTabel<BarisAbsensi>[] = [
    {
        id: 'TanggalBisnis',
        accessorKey: 'TanggalBisnis',
        header: 'Tanggal',
        meta: { label: 'Tanggal', prioritas: 'penting', kelasSel: 'whitespace-nowrap' },
        cell: ({ row }) => FormatTanggal(row.original.TanggalBisnis),
    },
    {
        id: 'Karyawan',
        header: 'Karyawan',
        enableSorting: false,
        meta: { label: 'Karyawan', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col">
                <span className="font-semibold break-words">{a.NamaKaryawan ?? '—'}</span>
                {a.NamaOutlet ? <span className="text-keterangan text-teks-sekunder">{a.NamaOutlet}</span> : null}
            </span>
        ),
    },
    {
        id: 'MasukPada',
        accessorKey: 'MasukPada',
        header: 'Masuk',
        meta: { label: 'Masuk', prioritas: 'penting' },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col">
                <span className="font-mono tabular-nums">{a.JamMasuk}</span>
                <TautanSwafoto a={a} jenis="masuk" />
            </span>
        ),
    },
    {
        id: 'Keluar',
        header: 'Keluar',
        enableSorting: false,
        meta: { label: 'Keluar', prioritas: 'penting' },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col">
                <span className="font-mono tabular-nums">
                    {a.JamKeluar ?? '—'}
                    {a.KeluarBeda ? ' (+1 hari)' : ''}
                </span>
                <TautanSwafoto a={a} jenis="keluar" />
            </span>
        ),
    },
    {
        id: 'Durasi',
        header: 'Durasi',
        enableSorting: false,
        meta: { label: 'Durasi', prioritas: 'rendah', angka: true },
        cell: ({ row }) => FormatDurasiMenit(row.original.DurasiMenit),
    },
    {
        id: 'Jadwal',
        header: 'Jadwal',
        enableSorting: false,
        meta: { label: 'Jadwal', prioritas: 'rendah' },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col">
                <span className="font-mono tabular-nums">{a.Jadwal ?? '—'}</span>
                {a.TerlambatMenit > 0 ? (
                    <span className="text-keterangan text-teks-sekunder">Terlambat {a.TerlambatMenit} menit</span>
                ) : null}
            </span>
        ),
    },
    {
        id: 'Status',
        header: 'Status',
        enableSorting: false,
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row: { original: a } }) => (
            <span className="flex flex-col items-start gap-1">
                <LabelStatus jenis={JenisStatus(a.Status)} teks={a.LabelStatus} />
                {a.Sumber === 'Web' ? (
                    <span className="text-keterangan text-teks-sekunder">
                        Dari HP | {a.JarakMasukMeter ?? '—'} m dari outlet
                        {a.JarakKeluarMeter !== null ? ` (keluar ${String(a.JarakKeluarMeter)} m)` : ''} | wajah{' '}
                        {a.KemiripanWajahMasuk ?? '—'}
                    </span>
                ) : null}
                {a.Sumber === 'Manual' || a.Dikoreksi ? (
                    <span className="text-keterangan text-teks-sekunder" title={a.AlasanKoreksi ?? undefined}>
                        {a.Sumber === 'Manual' ? 'Dicatat manual' : 'Dikoreksi'}
                        {a.AlasanKoreksi ? `: ${a.AlasanKoreksi}` : ''}
                    </span>
                ) : null}
            </span>
        ),
    },
];

/** `0830` → `08:30` (ketikan tanpa titik dua); lainnya apa adanya. */
export function RapikanJamKetik(teks: string): string {
    const bersih = teks.trim();

    return /^\d{4}$/.test(bersih) ? `${bersih.slice(0, 2)}:${bersih.slice(2)}` : bersih;
}

type Dialog = { jenis: 'tambah' } | { jenis: 'koreksi'; absensi: BarisAbsensi };

/**
 * F-18 EMP-03: rekap absensi dari aplikasi kasir: jam masuk/keluar waktu outlet, durasi, keterlambatan, swafoto.
 * v3.34: pengelola (`karyawan.kelola`) mencatat absensi yang terlewat dan mengoreksi jam, dengan alasan yang tercatat.
 */
export default function HalamanAbsensi({ Absensi, OpsiKaryawan, OpsiOutlet, BolehKoreksi }: PropsAbsensi) {
    const [dialog, AturDialog] = useState<Dialog | null>(null);
    const Tutup = () => AturDialog(null);
    const saring: DefinisiSaring[] = [
        { id: 'TanggalBisnis', label: 'Tanggal', jenis: 'rentangTanggal' },
        {
            id: 'Karyawan',
            label: 'Karyawan',
            jenis: 'pilihanBanyak',
            opsi: OpsiKaryawan.map((k) => ({ nilai: k.Uuid, label: k.Nama })),
        },
        {
            id: 'Outlet',
            label: 'Outlet',
            jenis: 'pilihanBanyak',
            opsi: OpsiOutlet.map((o) => ({ nilai: o.Uuid, label: o.Nama })),
        },
        {
            id: 'BelumKeluar',
            label: 'Belum absen keluar',
            jenis: 'pilihan',
            opsi: [{ nilai: '1', label: 'Ya' }],
        },
    ];

    return (
        <TataLetakAplikasi judul="Absensi">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Absen masuk & keluar dari aplikasi kasir dengan PIN dan swafoto (bila perangkat berkamera). Jam memakai
                zona waktu outlet; terlambat dihitung dari jadwal kerja.
            </p>
            {BolehKoreksi ? (
                <AksiHalaman>
                    <Tombol onClick={() => AturDialog({ jenis: 'tambah' })}>Catat absensi terlewat</Tombol>
                </AksiHalaman>
            ) : null}
            <TabelData
                id="karyawan-absensi"
                label="Rekap absensi"
                kolom={kolom}
                sumber={{ mode: 'server', alamat, awal: Absensi }}
                ambilIdBaris={(a) => a.Uuid}
                urutBawaan="-MasukPada"
                cari="Cari nama karyawan"
                saring={saring}
                {...(BolehKoreksi
                    ? {
                          aksiBaris: (a: BarisAbsensi) => (
                              <DropdownMenuItem onSelect={() => AturDialog({ jenis: 'koreksi', absensi: a })}>
                                  Koreksi jam
                              </DropdownMenuItem>
                          ),
                      }
                    : {})}
                labelBaris={(a) => `absensi ${a.NamaKaryawan ?? ''} ${FormatTanggal(a.TanggalBisnis)}`}
                kosong={{ ilustrasi: true, judul: 'Belum ada absensi. Karyawan absen dari aplikasi kasir di outlet.' }}
            />
            {BolehKoreksi ? <PanelKalibrasiWajah /> : null}
            {dialog !== null ? (
                <FormAbsensi
                    absensi={dialog.jenis === 'koreksi' ? dialog.absensi : null}
                    opsiKaryawan={OpsiKaryawan}
                    opsiOutlet={OpsiOutlet}
                    saatSelesai={Tutup}
                />
            ) : null}
        </TataLetakAplikasi>
    );
}

function KeOpsi(daftar: OpsiUuidNama[]) {
    return daftar.map((o) => ({ Nilai: o.Uuid, Label: o.Nama }));
}

/** Satu formulir untuk tambah (absensi null) dan koreksi; tanggal = tanggal bisnis, jam = jam dinding outlet. */
function FormAbsensi({
    absensi,
    opsiKaryawan,
    opsiOutlet,
    saatSelesai,
}: {
    absensi: BarisAbsensi | null;
    opsiKaryawan: OpsiUuidNama[];
    opsiOutlet: OpsiUuidNama[];
    saatSelesai: () => void;
}) {
    const hariIni = TulisTanggal(new Date());
    const formulir = useForm({
        Outlet: '',
        Karyawan: '',
        Tanggal: absensi?.TanggalBisnis ?? hariIni,
        JamMasuk: absensi?.JamMasuk ?? '',
        JamKeluar: absensi?.JamKeluar ?? '',
        KeluarHariBerikutnya: absensi?.KeluarBeda ?? false,
        Alasan: '',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.transform((d) => ({ ...d, JamKeluar: d.JamKeluar === '' ? null : d.JamKeluar }));

        if (absensi === null) {
            formulir.post(alamat, { preserveScroll: true, onSuccess: saatSelesai });
        } else {
            formulir.put(`${alamat}/${absensi.Uuid}`, { preserveScroll: true, onSuccess: saatSelesai });
        }
    };

    const Jam = (kolom: 'JamMasuk' | 'JamKeluar', label: string, wajib: boolean) => (
        <PemilihJam
            label={label}
            nilai={formulir.data[kolom]}
            saatBerubah={(nilai) => formulir.setData(kolom, nilai)}
            galat={formulir.errors[kolom]}
            keterangan="Jam outlet. Ketik (misal 0830) atau pilih."
            tombolSekarang
            required={wajib}
        />
    );

    return (
        <DialogFormulir
            judul={absensi === null ? 'Catat absensi terlewat' : `Koreksi absensi ${absensi.NamaKaryawan ?? ''}`}
            keterangan={
                absensi === null
                    ? 'Untuk karyawan yang lupa absen di kasir. Tercatat sebagai absensi manual atas nama Anda.'
                    : `${FormatTanggal(absensi.TanggalBisnis)}, ${absensi.NamaOutlet ?? ''}. Jam lama tetap tersimpan di log audit.`
            }
            saatTutup={saatSelesai}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                {absensi === null ? (
                    <>
                        <BidangOutlet
                            nilai={formulir.data.Outlet}
                            opsi={KeOpsi(opsiOutlet)}
                            saatBerubah={(nilai) => formulir.setData('Outlet', nilai)}
                            galat={formulir.errors.Outlet}
                        />
                        <BidangPilihan
                            label="Karyawan"
                            nilai={formulir.data.Karyawan}
                            opsi={KeOpsi(opsiKaryawan)}
                            saatBerubah={(nilai) => formulir.setData('Karyawan', nilai)}
                            galat={formulir.errors.Karyawan}
                            required
                        />
                    </>
                ) : null}
                <PemilihTanggal
                    label="Tanggal masuk"
                    nilai={formulir.data.Tanggal}
                    saatBerubah={(nilai) => formulir.setData('Tanggal', nilai)}
                    galat={formulir.errors.Tanggal}
                    max={hariIni}
                    required
                />
                {Jam('JamMasuk', 'Jam masuk', true)}
                {Jam('JamKeluar', 'Jam keluar (kosongkan bila belum)', false)}
                <div className="flex items-end pb-2">
                    <KotakCentang
                        label="Keluar keesokan harinya (shift malam)"
                        nilai={formulir.data.KeluarHariBerikutnya}
                        saatBerubah={(nilai) => formulir.setData('KeluarHariBerikutnya', nilai)}
                    />
                </div>
                <div className="sm:col-span-2">
                    <BidangTeks
                        label="Alasan"
                        nilai={formulir.data.Alasan}
                        saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                        galat={formulir.errors.Alasan}
                        keterangan="Misal: lupa absen keluar, HP kasir mati. Minimal 5 huruf."
                        maxLength={255}
                        required
                    />
                </div>
                <DialogFooter className="sm:col-span-2 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        {absensi === null ? 'Simpan absensi' : 'Simpan koreksi'}
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}
