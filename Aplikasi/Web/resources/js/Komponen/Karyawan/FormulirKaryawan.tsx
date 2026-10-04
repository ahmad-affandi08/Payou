import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import Tombol from '@/Komponen/Formulir/Tombol';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisKaryawan, OpsiUuidNama } from '@/Tipe/Karyawan';

export const AlamatKaryawan = '/kelola/karyawan';

type IsianKaryawan = {
    Nama: string;
    Jabatan: string;
    LevelStaf: string;
    GajiPokok: string;
    TarifLemburPerJam: string;
    PotonganTerlambatPerMenit: string;
    PotonganTidakMasukPerHari: string;
    UuidPengguna: string;
    UuidOutlet: string;
};

function BuatIsian(k: BarisKaryawan | null): IsianKaryawan {
    return {
        Nama: k?.Nama ?? '',
        Jabatan: k?.Jabatan ?? '',
        LevelStaf: k?.LevelStaf ?? '',
        GajiPokok: (k?.GajiPokok ?? '').replace(/\.00$/, ''),
        TarifLemburPerJam: (k?.TarifLemburPerJam ?? '').replace(/\.00$/, ''),
        PotonganTerlambatPerMenit: (k?.PotonganTerlambatPerMenit ?? '').replace(/\.00$/, ''),
        PotonganTidakMasukPerHari: (k?.PotonganTidakMasukPerHari ?? '').replace(/\.00$/, ''),
        UuidPengguna: k?.UuidPengguna ?? '',
        UuidOutlet: k?.UuidOutlet ?? '',
    };
}

/** Isian kosong dikirim sebagai null. */
export function SusunDataKaryawan(isian: IsianKaryawan): Record<string, string | null> {
    return Object.fromEntries(Object.entries(isian).map(([k, v]) => [k, v.trim() === '' ? null : v.trim()]));
}

type PropsIsiFormulirKaryawan = {
    karyawan: BarisKaryawan | null;
    opsiPengguna: OpsiUuidNama[];
    opsiOutlet: OpsiUuidNama[];
    /** Dipanggil setelah tersimpan (panel ubah menutup diri). Halaman tambah tidak memakainya: server mengarahkan. */
    saatSelesai?: () => void;
    saatBatal: () => void;
};

/** Isian formulir karyawan (F-18), dipakai halaman "Tambah karyawan" dan panel ubah; `karyawan` null = tambah. */
export function IsiFormulirKaryawan({
    karyawan,
    opsiPengguna,
    opsiOutlet,
    saatSelesai,
    saatBatal,
}: PropsIsiFormulirKaryawan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [isian, AturIsian] = useState<IsianKaryawan>(() => BuatIsian(karyawan));
    const [memproses, AturMemproses] = useState(false);
    const Ubah = (ubah: Partial<IsianKaryawan>) => AturIsian({ ...isian, ...ubah });
    // D-46: karyawan yang punya akun memakai nama akunnya (satu sumber nama).
    const akun = opsiPengguna.find((p) => p.Uuid === isian.UuidPengguna);

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = {
            preserveScroll: true,
            onStart: () => AturMemproses(true),
            onFinish: () => AturMemproses(false),
            onSuccess: () => saatSelesai?.(),
        };

        if (karyawan === null) {
            router.post(AlamatKaryawan, SusunDataKaryawan(isian), opsi);
        } else {
            router.put(`${AlamatKaryawan}/${karyawan.Uuid}`, SusunDataKaryawan(isian), opsi);
        }
    };

    return (
        <form onSubmit={Simpan} className="flex flex-col gap-4" aria-label="Formulir karyawan" noValidate>
            <BidangTeks
                label="Nama karyawan"
                nilai={akun?.Nama ?? isian.Nama}
                saatBerubah={(nilai) => Ubah({ Nama: nilai })}
                galat={galat.Nama}
                {...(akun ? { keterangan: 'Nama mengikuti akun yang ditautkan. Ubah di Pengguna.' } : {})}
                disabled={akun !== undefined}
                maxLength={150}
                required
            />
            <BidangTeks
                label="Jabatan (opsional)"
                nilai={isian.Jabatan}
                saatBerubah={(nilai) => Ubah({ Jabatan: nilai })}
                galat={galat.Jabatan}
                keterangan="Misal Barista, Terapis, Mekanik."
                maxLength={80}
            />
            <BidangTeks
                label="Level staf (opsional)"
                nilai={isian.LevelStaf}
                saatBerubah={(nilai) => Ubah({ LevelStaf: nilai })}
                galat={galat.LevelStaf}
                keterangan="Dipakai aturan komisi bertingkat, misal Senior atau Junior."
                maxLength={40}
            />
            <BidangUang
                label="Gaji pokok (opsional)"
                nilai={isian.GajiPokok}
                saatBerubah={(nilai) => Ubah({ GajiPokok: nilai })}
                galat={galat.GajiPokok}
            />
            <BidangUang
                label="Tarif lembur per jam (opsional)"
                nilai={isian.TarifLemburPerJam}
                saatBerubah={(nilai) => Ubah({ TarifLemburPerJam: nilai })}
                galat={galat.TarifLemburPerJam}
                keterangan="Dipakai rekap gaji untuk lembur yang tercatat dari absensi. Kosong = lembur tidak dibayar otomatis."
            />
            <BidangUang
                label="Potongan per menit terlambat (opsional)"
                nilai={isian.PotonganTerlambatPerMenit}
                saatBerubah={(nilai) => Ubah({ PotonganTerlambatPerMenit: nilai })}
                galat={galat.PotonganTerlambatPerMenit}
                keterangan="Kosong = keterlambatan tidak memotong gaji."
            />
            <BidangUang
                label="Potongan per hari tidak masuk (opsional)"
                nilai={isian.PotonganTidakMasukPerHari}
                saatBerubah={(nilai) => Ubah({ PotonganTidakMasukPerHari: nilai })}
                galat={galat.PotonganTidakMasukPerHari}
                keterangan="Untuk hari berjadwal tanpa absensi. Kosong = tidak memotong gaji."
            />
            <BidangPilihan
                label="Akun untuk absen di POS (opsional)"
                nilai={isian.UuidPengguna}
                kosong="Tanpa akun"
                opsi={opsiPengguna.map((p) => ({ Nilai: p.Uuid, Label: p.Nama }))}
                saatBerubah={(nilai) => Ubah({ UuidPengguna: nilai })}
                galat={galat.UuidPengguna}
            />
            <BidangPilihan
                label="Outlet utama (opsional)"
                nilai={isian.UuidOutlet}
                kosong="Semua outlet"
                opsi={opsiOutlet.map((o) => ({ Nilai: o.Uuid, Label: o.Nama }))}
                saatBerubah={(nilai) => Ubah({ UuidOutlet: nilai })}
                galat={galat.UuidOutlet}
            />
            <div className="flex flex-wrap justify-end gap-2">
                <Tombol type="button" varian="sekunder" onClick={saatBatal}>
                    Batal
                </Tombol>
                <Tombol type="submit" memproses={memproses}>
                    Simpan karyawan
                </Tombol>
            </div>
        </form>
    );
}

/** Panel ubah karyawan (F-18). Tambah karyawan memakai halaman penuh `/kelola/karyawan/buat`. */
export default function FormulirKaryawan({
    karyawan,
    opsiPengguna,
    opsiOutlet,
    saatTutup,
}: {
    karyawan: BarisKaryawan;
    opsiPengguna: OpsiUuidNama[];
    opsiOutlet: OpsiUuidNama[];
    saatTutup: () => void;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();

    return (
        <DialogFormulir
            judul={`Ubah karyawan ${karyawan.Nama}`}
            jenis="panel"
            galatUmum={props.errors.Umum}
            saatTutup={saatTutup}
        >
            <IsiFormulirKaryawan
                karyawan={karyawan}
                opsiPengguna={opsiPengguna}
                opsiOutlet={opsiOutlet}
                saatSelesai={saatTutup}
                saatBatal={saatTutup}
            />
        </DialogFormulir>
    );
}
