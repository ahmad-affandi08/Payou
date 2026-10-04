import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TataLetakAutentikasiPengelola from '@/TataLetak/TataLetakAutentikasiPengelola';
import type { PropsBersamaPengelola } from '@/Tipe/Pengelola';

type PropsVerifikasi = {
    /** D-42: true = konfirmasi kode sebelum aksi berbahaya, bukan langkah masuk. */
    Konfirmasi?: boolean;
    MenitKonfirmasi?: number;
    HariPerangkatTepercaya?: number;
};

/**
 * Verifikasi 2FA saat masuk (BR-P01.2). D-42: anggota boleh mempercayai browser ini sehingga login berikutnya cukup
 * kata sandi; aksi berbahaya tetap meminta kode lewat mode Konfirmasi. Kode pemulihan bisa dipakai bila ponsel tidak ada.
 */
export default function Verifikasi({
    Konfirmasi = false,
    MenitKonfirmasi = 15,
    HariPerangkatTepercaya = 90,
}: PropsVerifikasi) {
    const { props } = usePage<PropsBersamaPengelola>();
    const formulir = useForm({ Kode: '', PercayaiPerangkat: false });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(Konfirmasi ? '/dua-faktor/konfirmasi' : '/dua-faktor/verifikasi', {
            onFinish: () => formulir.reset('Kode'),
        });
    };
    const Keluar = () => router.post('/keluar');
    const Kembali = () => window.history.back();

    return (
        <TataLetakAutentikasiPengelola
            judul={Konfirmasi ? 'Konfirmasi dengan kode' : 'Verifikasi dua langkah'}
            keterangan={
                Konfirmasi
                    ? `Aksi ini penting, jadi kami minta kode dari aplikasi autentikator sekali lagi. Setelah itu kode tidak diminta lagi selama ${MenitKonfirmasi} menit.`
                    : 'Masukkan 6 digit kode dari aplikasi autentikator, atau salah satu kode pemulihan.'
            }
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Kode"
                    kode
                    autoComplete="one-time-code"
                    maxLength={11}
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai)}
                    galat={formulir.errors.Kode ?? props.errors.Umum}
                    autoFocus
                    required
                />
                {Konfirmasi ? null : (
                    <div className="flex flex-col gap-1">
                        <KotakCentang
                            label={`Percayai perangkat ini ${HariPerangkatTepercaya} hari`}
                            nilai={formulir.data.PercayaiPerangkat}
                            saatBerubah={(nilai) => formulir.setData('PercayaiPerangkat', nilai)}
                        />
                        <p className="text-keterangan text-teks-sekunder">
                            Login berikutnya di browser ini cukup kata sandi. Jangan dicentang di komputer bersama.
                        </p>
                    </div>
                )}
                <Tombol type="submit" memproses={formulir.processing}>
                    {Konfirmasi ? 'Konfirmasi' : 'Verifikasi'}
                </Tombol>
                <Tombol varian="sekunder" onClick={Konfirmasi ? Kembali : Keluar}>
                    {Konfirmasi ? 'Batal' : 'Keluar'}
                </Tombol>
            </form>
        </TataLetakAutentikasiPengelola>
    );
}
