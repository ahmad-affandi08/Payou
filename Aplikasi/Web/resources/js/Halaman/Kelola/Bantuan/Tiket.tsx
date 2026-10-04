import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import PercakapanTiket, { type PesanTiket } from '@/Komponen/Dukungan/PercakapanTiket';
import { jenisLabelStatusTiket, type BatasLampiran, type StatusTiket } from '@/Komponen/Dukungan/StatusTiket';
import BidangBerkas from '@/Komponen/Formulir/BidangBerkas';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Card } from '@/Komponen/Ui/card';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant } from '@/Tipe/Organisasi';

type DetailTiket = {
    Uuid: string;
    Nomor: string;
    Judul: string;
    LabelKategori: string;
    LabelPrioritas: string;
    Status: StatusTiket;
    LabelStatus: string;
    DibuatPada: string;
    BisaDibalas: boolean;
    BisaDiselesaikan: boolean;
    Pesan: PesanTiket[];
};

/** Percakapan tiket bantuan tenant: balas, lampirkan berkas, tandai selesai (P-09). */
export default function TiketBantuan({ Tiket, Lampiran }: { Tiket: DetailTiket; Lampiran: BatasLampiran }) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const bolehKelola = PunyaIzinTenant(props.Akses, IzinTenant.BantuanTiketKelola);
    const formulir = useForm<{ Isi: string; Lampiran: File[] }>({ Isi: '', Lampiran: [] });
    const [menyelesaikan, AturMenyelesaikan] = useState(false);
    const galatLampiran = Object.entries(formulir.errors).find(([kunci]) => kunci.startsWith('Lampiran'))?.[1];
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/kelola/bantuan/${Tiket.Uuid}/balasan`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => formulir.reset(),
        });
    };
    const Selesaikan = () =>
        router.post(
            `/kelola/bantuan/${Tiket.Uuid}/selesaikan`,
            {},
            { preserveScroll: true, onStart: () => AturMenyelesaikan(true), onFinish: () => AturMenyelesaikan(false) },
        );

    return (
        // D-27: remah roti halaman dihapus; jalan kembali ke daftar tiket ikut jejak halaman di tata letak.
        <TataLetakAplikasi judul={Tiket.Judul} jejak={[{ label: 'Semua tiket', href: '/kelola/bantuan' }]}>
            <div className="flex flex-wrap items-center gap-2 text-label text-teks-sekunder">
                <span className="font-mono">{Tiket.Nomor}</span>
                <LabelStatus jenis={jenisLabelStatusTiket[Tiket.Status]} teks={Tiket.LabelStatus} />
                <span>
                    {Tiket.LabelKategori} | Prioritas {Tiket.LabelPrioritas} | Dibuat{' '}
                    {FormatTanggalWaktu(Tiket.DibuatPada)}
                </span>
            </div>

            <PercakapanTiket
                pesan={Tiket.Pesan}
                tautanLampiran={(uuid) => `/kelola/bantuan/${Tiket.Uuid}/lampiran/${uuid}`}
            />

            {!bolehKelola ? (
                <Pemberitahuan jenis="info" judul="Hanya bisa melihat">
                    Peran Anda hanya bisa melihat tiket bantuan. Minta pemilik usaha atau Admin untuk membalas tiket
                    ini.
                </Pemberitahuan>
            ) : Tiket.BisaDibalas ? (
                <Card className="p-5 rounded-panel shadow-none">
                    <form onSubmit={Kirim} className="flex flex-col gap-3" noValidate>
                        {Tiket.Status === 'Selesai' ? (
                            <Pemberitahuan jenis="info">Mengirim balasan akan membuka lagi tiket ini.</Pemberitahuan>
                        ) : null}
                        <BidangTeksPanjang
                            label="Balasan Anda"
                            nilai={formulir.data.Isi}
                            maksimal={10000}
                            saatBerubah={(nilai) => formulir.setData('Isi', nilai)}
                            galat={formulir.errors.Isi}
                            required
                        />
                        <BidangBerkas
                            label="Lampiran"
                            berkas={formulir.data.Lampiran}
                            ekstensi={Lampiran.Ekstensi}
                            maksimal={Lampiran.Maksimal}
                            ukuranMaksimalKb={Lampiran.UkuranMaksimalKb}
                            saatBerubah={(berkas) => formulir.setData('Lampiran', berkas)}
                            galat={galatLampiran}
                        />
                        <div className="flex flex-wrap gap-3">
                            <Tombol type="submit" memproses={formulir.processing}>
                                Kirim balasan
                            </Tombol>
                            {Tiket.BisaDiselesaikan ? (
                                <Tombol varian="sekunder" memproses={menyelesaikan} onClick={Selesaikan}>
                                    Masalah sudah selesai
                                </Tombol>
                            ) : null}
                        </div>
                    </form>
                </Card>
            ) : (
                <Pemberitahuan jenis="info" judul="Tiket ditutup">
                    Tiket ini tidak bisa dibalas lagi. Bila masalahnya muncul lagi,{' '}
                    <Link href="/kelola/bantuan/buat" className="font-semibold text-brand underline">
                        buat tiket baru
                    </Link>{' '}
                    dan sebutkan nomor {Tiket.Nomor}.
                </Pemberitahuan>
            )}
        </TataLetakAplikasi>
    );
}
