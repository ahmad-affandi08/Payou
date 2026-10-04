import { router } from '@inertiajs/react';
import { useState } from 'react';

import PilihanCari from '@/Komponen/Formulir/PilihanCari';
import Tombol from '@/Komponen/Formulir/Tombol';
import type { KonteksAksiMassal } from '@/Komponen/TabelData/TabelData';
import type { OpsiPeranPengguna } from '@/Tipe/Organisasi';

type AksiPengguna = 'Nonaktifkan' | 'Aktifkan' | 'Peran';

/** Batas server `UbahAnggotaMassal::MAKS`. */
export const MaksPenggunaMassal = 100;

type BarisPengguna = { Uuid: string; Nama: string };

/**
 * Aksi untuk pengguna terpilih (F-02): nonaktifkan, aktifkan kembali, atau ganti peran (akses outlet masing-masing
 * dipertahankan). `bolehSentuh` menentukan baris yang boleh diubah dari sini (bukan akun sendiri, dan Pemilik hanya oleh
 * Pemilik); pilihan yang memuat baris tak boleh disentuh dikunci supaya pengguna tidak menunggu penolakan server.
 */
export default function AksiMassalPengguna<T extends BarisPengguna>({
    konteks,
    peran,
    bolehSentuh,
    bolehUbah,
    bolehNonaktifkan,
}: {
    konteks: KonteksAksiMassal<T>;
    peran: OpsiPeranPengguna[];
    bolehSentuh: (baris: T) => boolean;
    bolehUbah: boolean;
    bolehNonaktifkan: boolean;
}) {
    const [uuidPeran, AturUuidPeran] = useState('');
    const [memproses, AturMemproses] = useState<AksiPengguna | null>(null);
    const uuid = konteks.terpilih.map((p) => p.Uuid);
    const terlarang = konteks.terpilih.filter((p) => !bolehSentuh(p));
    const terlaluBanyak = uuid.length > MaksPenggunaMassal;
    const nonaktif = memproses !== null || terlaluBanyak || terlarang.length > 0;

    const Jalankan = (aksi: AksiPengguna) =>
        router.post(
            '/kelola/pengguna/massal',
            { Aksi: aksi, Uuid: uuid, UuidPeran: aksi === 'Peran' ? uuidPeran : null },
            {
                preserveScroll: true,
                onStart: () => AturMemproses(aksi),
                onFinish: () => AturMemproses(null),
                onSuccess: () => konteks.bersihkan(),
            },
        );

    return (
        <div className="flex flex-wrap items-end gap-2">
            {terlaluBanyak ? (
                <p className="w-full text-keterangan text-bahaya">
                    Maksimal {MaksPenggunaMassal} pengguna sekali proses. Kurangi pilihan.
                </p>
            ) : null}
            {terlarang.length > 0 ? (
                <p className="w-full text-keterangan text-teks-sekunder">
                    Akun Anda sendiri atau Pemilik (bila Anda bukan Pemilik) tidak bisa diubah dari sini:{' '}
                    {terlarang.map((p) => p.Nama).join(', ')}. Hapus dari pilihan.
                </p>
            ) : null}
            {bolehUbah ? (
                <>
                    <div className="w-56">
                        <PilihanCari
                            label="Peran baru"
                            nilai={uuidPeran}
                            opsi={peran.map((p) => ({ Nilai: p.Uuid, Label: p.Nama }))}
                            placeholder="Pilih peran"
                            saatBerubah={AturUuidPeran}
                            aria-label="Peran baru"
                        />
                    </div>
                    <Tombol
                        varian="sekunder"
                        disabled={nonaktif || uuidPeran === ''}
                        memproses={memproses === 'Peran'}
                        onClick={() => Jalankan('Peran')}
                    >
                        Ganti peran
                    </Tombol>
                </>
            ) : null}
            {bolehNonaktifkan ? (
                <>
                    <Tombol
                        varian="sekunder"
                        disabled={nonaktif}
                        memproses={memproses === 'Aktifkan'}
                        onClick={() => Jalankan('Aktifkan')}
                    >
                        Aktifkan
                    </Tombol>
                    <Tombol
                        varian="bahaya"
                        disabled={nonaktif}
                        memproses={memproses === 'Nonaktifkan'}
                        onClick={() => Jalankan('Nonaktifkan')}
                    >
                        Nonaktifkan
                    </Tombol>
                </>
            ) : null}
        </div>
    );
}
