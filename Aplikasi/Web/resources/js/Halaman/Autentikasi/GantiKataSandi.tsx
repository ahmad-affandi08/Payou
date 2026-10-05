import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

/** D-22: ganti kata sandi (wajib setelah kata sandi awal dibuat admin usaha). Perangkat lain otomatis keluar. */
export default function HalamanGantiKataSandi({
    Wajib,
    TanpaKataSandiLama = false,
}: {
    Wajib: boolean;
    TanpaKataSandiLama?: boolean;
}) {
    const formulir = useForm({ KataSandiLama: '', KataSandi: '', KonfirmasiKataSandi: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/ganti-kata-sandi', { onFinish: () => formulir.reset() });
    };

    return (
        <TataLetakAutentikasi
            judul="Ganti kata sandi"
            keterangan={
                TanpaKataSandiLama
                    ? 'Akun Anda masuk dengan Google. Atur kata sandi supaya Anda juga bisa masuk dengan email dan kata sandi.'
                    : Wajib
                      ? 'Kata sandi Anda dibuat oleh admin usaha. Ganti dengan kata sandi yang hanya Anda ketahui sebelum melanjutkan.'
                      : 'Setelah disimpan, perangkat lain yang masuk dengan akun ini akan keluar otomatis.'
            }
        >
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                {TanpaKataSandiLama ? null : (
                    <BidangTeks
                        label={Wajib ? 'Kata sandi awal' : 'Kata sandi saat ini'}
                        jenis="password"
                        autoComplete="current-password"
                        nilai={formulir.data.KataSandiLama}
                        saatBerubah={(nilai) => formulir.setData('KataSandiLama', nilai)}
                        galat={formulir.errors.KataSandiLama}
                        autoFocus
                        required
                    />
                )}
                <BidangTeks
                    label="Kata sandi baru"
                    jenis="password"
                    autoComplete="new-password"
                    keterangan="Minimal 8 karakter, berisi huruf dan angka."
                    nilai={formulir.data.KataSandi}
                    saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                    galat={formulir.errors.KataSandi}
                    required
                />
                <BidangTeks
                    label="Ulangi kata sandi baru"
                    jenis="password"
                    autoComplete="new-password"
                    nilai={formulir.data.KonfirmasiKataSandi}
                    saatBerubah={(nilai) => formulir.setData('KonfirmasiKataSandi', nilai)}
                    galat={formulir.errors.KonfirmasiKataSandi}
                    required
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kata sandi baru
                </Tombol>
            </form>
        </TataLetakAutentikasi>
    );
}
