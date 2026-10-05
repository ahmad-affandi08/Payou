import { Link, useForm } from '@inertiajs/react';
import { useId, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Checkbox } from '@/Komponen/Ui/checkbox';
import { Label } from '@/Komponen/Ui/label';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

type PropsLengkapiGoogle = {
    Dibuka: boolean;
    Akun: { Nama: string; Email: string };
    Paket: { Kode: string; Nama: string; MasaTrialHari: number }[];
    PaketTerpilih: string;
};

/**
 * D-57 langkah kedua "Daftar dengan Google": Google sudah membuktikan email, jadi tidak ada kolom email, kata sandi,
 * maupun CAPTCHA. Yang tersisa: nama, nomor WhatsApp (notifikasi toko lewat WhatsApp, D-33), nama usaha, paket, dan
 * persetujuan Syarat & Ketentuan.
 */
export default function HalamanLengkapiGoogle({ Dibuka, Akun, Paket, PaketTerpilih }: PropsLengkapiGoogle) {
    const formulir = useForm({
        Nama: Akun.Nama,
        NoHp: '',
        NamaUsaha: '',
        Paket: Paket.some((paket) => paket.Kode === PaketTerpilih) ? PaketTerpilih : (Paket[0]?.Kode ?? ''),
        Setuju: false,
    });
    const galat = formulir.errors as Record<string, string | undefined>;
    const idSetuju = useId();

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/daftar/google');
    };

    if (!Dibuka) {
        return (
            <TataLetakAutentikasi judul="Daftar">
                <Pemberitahuan jenis="info" judul="Pendaftaran belum dibuka">
                    Kami sedang menyiapkan layanan. Silakan kembali lagi nanti.
                </Pemberitahuan>
            </TataLetakAutentikasi>
        );
    }

    return (
        <TataLetakAutentikasi
            judul="Lengkapi pendaftaran"
            keterangan="Tinggal beberapa isian lagi untuk memulai masa trial."
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <Pemberitahuan jenis="info">
                    Anda mendaftar dengan akun Google <strong className="break-all">{Akun.Email}</strong>. Berikutnya
                    cukup masuk dengan Google, tanpa kata sandi dan tanpa kode verifikasi dua langkah.
                </Pemberitahuan>
                <BidangTeks
                    label="Nama Anda"
                    autoComplete="name"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={galat.Nama}
                    required
                />
                <BidangTeks
                    label="Nama usaha"
                    autoComplete="organization"
                    nilai={formulir.data.NamaUsaha}
                    saatBerubah={(nilai) => formulir.setData('NamaUsaha', nilai)}
                    galat={galat.NamaUsaha}
                    required
                />
                <BidangTeks
                    label="Nomor WhatsApp"
                    inputMode="tel"
                    autoComplete="tel"
                    keterangan="Misal 081234567890. Dipakai untuk notifikasi usaha Anda."
                    nilai={formulir.data.NoHp}
                    saatBerubah={(nilai) => formulir.setData('NoHp', nilai)}
                    galat={galat.NoHp ?? galat.Email}
                    required
                />
                <BidangPilihan
                    label="Paket yang dicoba"
                    nilai={formulir.data.Paket}
                    opsi={Paket.map((paket) => ({
                        Nilai: paket.Kode,
                        Label:
                            paket.MasaTrialHari > 0 ? `${paket.Nama} (trial ${paket.MasaTrialHari} hari)` : paket.Nama,
                    }))}
                    saatBerubah={(nilai) => formulir.setData('Paket', nilai)}
                    galat={galat.Paket}
                />
                <div className="flex items-start gap-2">
                    <Checkbox
                        id={idSetuju}
                        className="mt-0.5 border-garis-input"
                        checked={formulir.data.Setuju}
                        onCheckedChange={(status) => formulir.setData('Setuju', status === true)}
                        aria-invalid={galat.Setuju ? true : undefined}
                    />
                    <Label htmlFor={idSetuju} className="block text-isi leading-normal font-normal text-teks-utama">
                        Saya menyetujui{' '}
                        <a
                            href="/legal/syarat-ketentuan"
                            target="_blank"
                            rel="noreferrer"
                            className="font-semibold text-brand underline"
                        >
                            Syarat & Ketentuan
                        </a>
                        ,{' '}
                        <a
                            href="/legal/kebijakan-privasi"
                            target="_blank"
                            rel="noreferrer"
                            className="font-semibold text-brand underline"
                        >
                            Kebijakan Privasi
                        </a>
                        , serta{' '}
                        <a
                            href="/legal/perjanjian-pemrosesan-data"
                            target="_blank"
                            rel="noreferrer"
                            className="font-semibold text-brand underline"
                        >
                            Perjanjian Pemrosesan Data
                        </a>
                        .
                    </Label>
                </div>
                {galat.Setuju ? <p className="text-keterangan font-semibold text-bahaya">{galat.Setuju}</p> : null}
                <Tombol type="submit" memproses={formulir.processing}>
                    Daftar dan mulai trial
                </Tombol>
                <p className="text-keterangan text-teks-sekunder">
                    Bukan akun Anda?{' '}
                    <Link href="/daftar" className="font-semibold text-brand underline">
                        Kembali ke pendaftaran
                    </Link>
                </p>
            </form>
        </TataLetakAutentikasi>
    );
}
