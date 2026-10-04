import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AksiHalaman from '@/Komponen/Kelola/AksiHalaman';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TabRilis from '@/Komponen/Pengelola/TabRilis';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { AmbilJenisStatusKompatibilitas, LabelSambunganPrinter, type BarisKompatibilitas } from '@/Tipe/Kompatibilitas';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

const kolom: KolomTabel<BarisKompatibilitas>[] = [
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Model',
        meta: { label: 'Model', prioritas: 'utama', wajib: true, kelasSel: 'text-teks-utama' },
        cell: ({ row: { original: b } }) => (
            <>
                <span className="block">{b.Nama}</span>
                <span className="block text-keterangan text-teks-sekunder">
                    {b.Jenis === 'Printer'
                        ? `Printer${b.Sambungan ? ` | ${LabelSambunganPrinter[b.Sambungan] ?? b.Sambungan}` : ''}`
                        : 'Perangkat'}
                    {b.Catatan ? ` | ${b.Catatan}` : ''}
                </span>
            </>
        ),
    },
    {
        id: 'Jenis',
        accessorKey: 'Jenis',
        header: 'Jenis',
        meta: { label: 'Jenis', prioritas: 'rendah' },
    },
    {
        id: 'Status',
        accessorKey: 'Status',
        header: 'Status',
        meta: { label: 'Status', prioritas: 'penting' },
        cell: ({ row: { original: b } }) => (
            <LabelStatus
                jenis={AmbilJenisStatusKompatibilitas(b.Status)}
                teks={b.StatusManual ? `${b.LabelStatus} (tim)` : b.LabelStatus}
            />
        ),
    },
    {
        id: 'JumlahPerangkat',
        accessorKey: 'JumlahPerangkat',
        header: 'Perangkat',
        meta: { label: 'Perangkat', prioritas: 'rendah', angka: true },
        cell: ({ row: { original: b } }) => `${String(b.JumlahPerangkat)} di ${String(b.JumlahTenant)} usaha`,
    },
    {
        id: 'JumlahLolos',
        accessorKey: 'JumlahLolos',
        header: 'Uji berhasil / gagal',
        meta: { label: 'Uji berhasil / gagal', prioritas: 'rendah', angka: true },
        cell: ({ row: { original: b } }) => `${String(b.JumlahLolos)} / ${String(b.JumlahGagal)}`,
    },
    {
        id: 'TerakhirDiujiPada',
        accessorKey: 'TerakhirDiujiPada',
        header: 'Terakhir diuji',
        meta: { label: 'Terakhir diuji', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
        cell: ({ row: { original: b } }) => (b.TerakhirDiujiPada ? FormatTanggalWaktu(b.TerakhirDiujiPada) : '—'),
    },
];

/**
 * Hardware Compatibility List (PRD §17.2.5a, v1.98): model perangkat & printer dari hasil Wizard Uji Perangkat di
 * lapangan (disegarkan otomatis tiap hari). Tim dapat menandai Tersertifikasi (lolos uji lab) atau Terbatas (kendala
 * diketahui) dengan catatan; baris berstatus selain "Belum diuji" tampil di halaman publik.
 */
export default function HalamanKompatibilitasPerangkat({ Baris }: { Baris: BarisKompatibilitas[] }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.RilisKelola);
    const [tandai, AturTandai] = useState<BarisKompatibilitas | null>(null);
    const [menyegarkan, AturMenyegarkan] = useState(false);
    const disegarkan = Baris.map((b) => b.DisegarkanPada)
        .filter((w): w is string => w !== null)
        .sort()
        .at(-1);

    const Segarkan = () => {
        AturMenyegarkan(true);
        router.post(
            '/kompatibilitas-perangkat/segarkan',
            {},
            { preserveScroll: true, onFinish: () => AturMenyegarkan(false) },
        );
    };

    return (
        <TataLetakPengelola judul="Rilis aplikasi">
            <TabRilis />
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Disusun dari hasil Wizard Uji Perangkat di aplikasi kasir, tanpa nama usaha. Kompatibel = lolos uji di
                lapangan; Terbatas = kegagalan sama atau lebih banyak dari keberhasilan. Tanda tim mengalahkan status
                otomatis.{' '}
                {disegarkan ? `Terakhir disegarkan ${FormatTanggalWaktu(disegarkan)}.` : 'Belum pernah disegarkan.'}
            </p>
            <AksiHalaman>
                {bolehKelola ? (
                    <Tombol onClick={Segarkan} memproses={menyegarkan}>
                        Segarkan sekarang
                    </Tombol>
                ) : null}
            </AksiHalaman>
            <TabelData
                id="pengelola-kompatibilitas-perangkat"
                label="Daftar kompatibilitas perangkat"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: Baris }}
                ambilIdBaris={(b) => b.Uuid}
                labelBaris={(b) => b.Nama}
                cari="Cari model"
                saring={[
                    {
                        id: 'Jenis',
                        label: 'Jenis',
                        jenis: 'pilihanBanyak',
                        opsi: [
                            { nilai: 'Perangkat', label: 'Perangkat' },
                            { nilai: 'Printer', label: 'Printer' },
                        ],
                    },
                    {
                        id: 'Status',
                        label: 'Status',
                        jenis: 'pilihanBanyak',
                        opsi: [
                            { nilai: 'Tersertifikasi', label: 'Tersertifikasi' },
                            { nilai: 'Kompatibel', label: 'Kompatibel' },
                            { nilai: 'Terbatas', label: 'Terbatas' },
                            { nilai: 'BelumDiuji', label: 'Belum diuji' },
                        ],
                    },
                ]}
                {...(bolehKelola
                    ? {
                          aksiBaris: (b: BarisKompatibilitas) => (
                              <DropdownMenuItem onSelect={() => AturTandai(b)}>Tandai status</DropdownMenuItem>
                          ),
                      }
                    : {})}
                kosong={{
                    ilustrasi: true,
                    judul: 'Belum ada hasil uji perangkat. Data muncul setelah kasir menjalankan Uji perangkat.',
                }}
            />
            {tandai ? <FormTandai baris={tandai} saatSelesai={() => AturTandai(null)} /> : null}
        </TataLetakPengelola>
    );
}

function FormTandai({ baris, saatSelesai }: { baris: BarisKompatibilitas; saatSelesai: () => void }) {
    const formulir = useForm({ Status: baris.StatusManual ?? '', Catatan: baris.Catatan ?? '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.put(`/kompatibilitas-perangkat/${baris.Uuid}`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <DialogFormulir
            judul={`Tandai ${baris.Nama}`}
            keterangan={`Status otomatis: ${baris.StatusOtomatis === 'BelumDiuji' ? 'Belum diuji' : baris.StatusOtomatis}. Catatan tampil di halaman publik.`}
            saatTutup={saatSelesai}
        >
            <form onSubmit={Kirim} className="grid gap-4" noValidate>
                <BidangPilihan
                    label="Status"
                    nilai={formulir.data.Status}
                    opsi={[
                        { Nilai: '', Label: 'Ikuti status otomatis' },
                        { Nilai: 'Tersertifikasi', Label: 'Tersertifikasi (lolos uji lab)' },
                        { Nilai: 'Terbatas', Label: 'Terbatas (ada kendala)' },
                    ]}
                    saatBerubah={(nilai) => formulir.setData('Status', nilai)}
                    galat={formulir.errors.Status}
                />
                <BidangTeksPanjang
                    label="Catatan"
                    nilai={formulir.data.Catatan}
                    saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                    galat={formulir.errors.Catatan}
                    required={formulir.data.Status !== ''}
                />
                <DialogFooter className="sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan status
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}
