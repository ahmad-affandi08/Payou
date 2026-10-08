import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import Panel from '@/Komponen/Kelola/Panel';
import Tombol from '@/Komponen/Formulir/Tombol';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import type { Tampilan360 } from '@/Tipe/TenantPengelola';

const jenisStatus = { Menunggu: 'peringatan', Aktif: 'sukses', Gagal: 'bahaya', Dinonaktifkan: 'netral' } as const;

type PropsPanel = {
    uuidTenant: string;
    data: Tampilan360['SubAkunPembayaran'];
    /** Izin `integrasi.kelola`: sub account memakai kredensial akun induk DOKU platform. */
    bolehKelola: boolean;
};

/**
 * Sub account pembayaran DOKU tenant (tahap 3). Sub account dibuat otomatis memakai Akun DOKU Payoung (induk),
 * kredensial yang sama dengan tagihan langganan. Tombol hanya aktif bila belum ada ID sub account dan gerbang platform
 * sudah diisi; hasil pembuatan tampil lewat dialog hasil layout konsol.
 */
export default function PanelSubAkunPembayaran({ uuidTenant, data, bolehKelola }: PropsPanel) {
    const [memproses, AturMemproses] = useState(false);
    const sub = data.Sub;
    const sudahAda = sub !== null && !sub.BisaDibuat;
    const nonaktif = sudahAda || !data.GerbangPlatformAktif || !bolehKelola;
    const label = sub === null || sudahAda ? 'Buat sub account otomatis' : 'Coba buat lagi';

    const Buat = () => {
        router.post(
            `/tenant/${uuidTenant}/sub-akun-pembayaran`,
            {},
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <Panel
            judul="Sub account pembayaran (DOKU)"
            keterangan="Wadah dana QRIS tenant di DOKU. Dibuat dari Akun DOKU Payoung (induk)."
        >
            <dl className="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-3 gap-y-2 text-isi">
                <dt className="text-teks-sekunder">Status</dt>
                <dd>
                    <LabelStatus
                        jenis={sub === null ? 'netral' : jenisStatus[sub.Status]}
                        teks={sub === null ? 'Belum dibuat' : sub.LabelStatus}
                    />
                </dd>
                <dt className="text-teks-sekunder">ID sub account</dt>
                <dd className="break-all text-teks-utama">
                    {sub?.IdSubAkun ? <span className="font-mono text-label">{sub.IdSubAkun}</span> : 'Belum ada'}
                </dd>
                {sub ? (
                    <>
                        <dt className="text-teks-sekunder">Diminta</dt>
                        <dd className="text-teks-utama">{FormatTanggalWaktu(sub.DibuatPada)}</dd>
                    </>
                ) : null}
            </dl>

            {sub?.PesanGalat ? <Pemberitahuan jenis="bahaya">{sub.PesanGalat}</Pemberitahuan> : null}

            {!data.GerbangPlatformAktif ? (
                <Pemberitahuan jenis="peringatan">
                    Akun DOKU Payoung (induk) belum diisi. Isi Client ID dan Secret key di{' '}
                    <Link href="/integrasi" className="font-semibold underline">
                        menu Integrasi
                    </Link>{' '}
                    dulu.
                </Pemberitahuan>
            ) : null}

            <div className="flex flex-wrap items-center gap-2">
                <Tombol varian="sekunder" disabled={nonaktif} memproses={memproses} onClick={Buat}>
                    {label}
                </Tombol>
                {sudahAda ? (
                    <span className="text-keterangan text-teks-sekunder">Sub account sudah terdaftar di DOKU.</span>
                ) : !bolehKelola ? (
                    <span className="text-keterangan text-teks-sekunder">
                        Hanya peran dengan izin kelola integrasi (Teknis, Super Admin) yang bisa membuatnya.
                    </span>
                ) : null}
            </div>
        </Panel>
    );
}
