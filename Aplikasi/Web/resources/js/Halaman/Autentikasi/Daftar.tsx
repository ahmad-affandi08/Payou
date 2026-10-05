import { Link, useForm } from '@inertiajs/react';
import { useId, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TombolGoogle, { PemisahAtau } from '@/Komponen/Formulir/TombolGoogle';
import WidgetCaptcha from '@/Komponen/Formulir/WidgetCaptcha';
import { Checkbox } from '@/Komponen/Ui/checkbox';
import { Label } from '@/Komponen/Ui/label';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

type PropsDaftar = {
    Dibuka: boolean;
    Paket: { Kode: string; Nama: string; MasaTrialHari: number }[];
    PaketTerpilih: string;
    KunciSitusCaptcha: string | null;
    MasukGoogle?: boolean;
};

/** Registrasi tenant (F-00 langkah 1). */
export default function HalamanDaftar({
    Dibuka,
    Paket,
    PaketTerpilih,
    KunciSitusCaptcha,
    MasukGoogle = false,
}: PropsDaftar) {
    const formulir = useForm({
        Nama: '',
        Email: '',
        NoHp: '',
        KataSandi: '',
        KonfirmasiKataSandi: '',
        NamaUsaha: '',
        Paket: Paket.some((paket) => paket.Kode === PaketTerpilih) ? PaketTerpilih : (Paket[0]?.Kode ?? ''),
        Setuju: false,
    });
    const galat = formulir.errors as Record<string, string | undefined>;
    const idSetuju = useId();
    // Token disimpan di state tersendiri: setter useState stabil, jadi widget CAPTCHA tidak dirender ulang saat mengetik.
    const [tokenCaptcha, AturTokenCaptcha] = useState('');
    const [urutanResetCaptcha, AturUrutanResetCaptcha] = useState(0);

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.transform((isian) => ({ ...isian, TokenCaptcha: tokenCaptcha }));
        formulir.post('/daftar', {
            // Token CAPTCHA hanya berlaku sekali: setelah galat apa pun, minta CAPTCHA baru; isian lain tetap.
            onError: () => AturUrutanResetCaptcha((urutan) => urutan + 1),
            onFinish: () => formulir.reset('KataSandi', 'KonfirmasiKataSandi'),
        });
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
        <TataLetakAutentikasi judul="Daftar gratis" keterangan="Mulai masa trial tanpa kartu kredit." lebar="sedang">
            {MasukGoogle ? (
                <div className="mb-4 flex flex-col gap-3">
                    <TombolGoogle href={`/masuk/google?tujuan=daftar&paket=${encodeURIComponent(formulir.data.Paket)}`}>
                        Daftar dengan Google
                    </TombolGoogle>
                    <PemisahAtau />
                </div>
            ) : null}
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
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
                    label="Email"
                    jenis="email"
                    autoComplete="email"
                    nilai={formulir.data.Email}
                    saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                    galat={galat.Email}
                    required
                />
                <BidangTeks
                    label="Nomor WhatsApp"
                    inputMode="tel"
                    autoComplete="tel"
                    keterangan="Misal 081234567890."
                    nilai={formulir.data.NoHp}
                    saatBerubah={(nilai) => formulir.setData('NoHp', nilai)}
                    galat={galat.NoHp}
                    required
                />
                <BidangTeks
                    label="Kata sandi"
                    jenis="password"
                    autoComplete="new-password"
                    keterangan="Minimal 8 karakter, berisi huruf dan angka."
                    nilai={formulir.data.KataSandi}
                    saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                    galat={galat.KataSandi}
                    required
                />
                <BidangTeks
                    label="Ulangi kata sandi"
                    jenis="password"
                    autoComplete="new-password"
                    nilai={formulir.data.KonfirmasiKataSandi}
                    saatBerubah={(nilai) => formulir.setData('KonfirmasiKataSandi', nilai)}
                    galat={galat.KonfirmasiKataSandi}
                    required
                />
                <div className="sm:col-span-2">
                    <BidangPilihan
                        label="Paket yang dicoba"
                        nilai={formulir.data.Paket}
                        opsi={Paket.map((paket) => ({
                            Nilai: paket.Kode,
                            Label:
                                paket.MasaTrialHari > 0
                                    ? `${paket.Nama} (trial ${paket.MasaTrialHari} hari)`
                                    : paket.Nama,
                        }))}
                        saatBerubah={(nilai) => formulir.setData('Paket', nilai)}
                        galat={galat.Paket}
                    />
                </div>
                <div className="flex items-start gap-2 sm:col-span-2">
                    <Checkbox
                        id={idSetuju}
                        className="mt-0.5 border-garis-input"
                        checked={formulir.data.Setuju}
                        onCheckedChange={(status) => formulir.setData('Setuju', status === true)}
                        aria-invalid={galat.Setuju ? true : undefined}
                        aria-describedby={galat.Setuju ? `${idSetuju}-galat` : undefined}
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
                {galat.Setuju ? (
                    <p id={`${idSetuju}-galat`} className="text-keterangan font-semibold text-bahaya sm:col-span-2">
                        {galat.Setuju}
                    </p>
                ) : null}
                {KunciSitusCaptcha ? (
                    <div className="sm:col-span-2">
                        <WidgetCaptcha
                            kunciSitus={KunciSitusCaptcha}
                            saatBerubah={AturTokenCaptcha}
                            urutanReset={urutanResetCaptcha}
                            galat={galat.TokenCaptcha}
                        />
                    </div>
                ) : null}
                <div className="flex flex-col gap-3 sm:col-span-2">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Daftar dan mulai trial
                    </Tombol>
                    <p className="text-keterangan text-teks-sekunder">
                        Sudah punya akun?{' '}
                        <Link href="/masuk" className="font-semibold text-brand underline">
                            Masuk
                        </Link>
                    </p>
                </div>
            </form>
        </TataLetakAutentikasi>
    );
}
