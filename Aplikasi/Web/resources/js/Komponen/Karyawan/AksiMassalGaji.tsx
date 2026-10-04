import { router } from '@inertiajs/react';
import { useState } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import Tombol from '@/Komponen/Formulir/Tombol';
import type { KonteksAksiMassal } from '@/Komponen/TabelData/TabelData';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import type { BarisGajiKaryawan } from '@/Tipe/Karyawan';

/** Batas server `KelolaRekapGaji::MAKS_TAMBAHAN_MASSAL`. */
export const MaksTambahanMassal = 500;

/**
 * Aksi untuk karyawan terpilih di draf rekap gaji (F-18): beri tambahan yang sama (THR, bonus, tunjangan) sekaligus.
 * Menambah ke tambahan yang sudah ada, bukan menggantinya. Rekap yang sudah dibayar tidak menampilkan aksi ini.
 */
export default function AksiMassalGaji({
    konteks,
    alamat,
}: {
    konteks: KonteksAksiMassal<BarisGajiKaryawan>;
    alamat: string;
}) {
    const [terbuka, AturTerbuka] = useState(false);
    const [tambahan, AturTambahan] = useState('');
    const [catatan, AturCatatan] = useState('');
    const [galat, AturGalat] = useState<Record<string, string>>({});
    const [memproses, AturMemproses] = useState(false);
    const uuid = konteks.terpilih.map((b) => b.UuidKaryawan);
    const terlaluBanyak = uuid.length > MaksTambahanMassal;

    const Kirim = () =>
        router.post(
            `${alamat}/tambahan-massal`,
            { Tambahan: tambahan, Catatan: catatan, Uuid: uuid },
            {
                preserveScroll: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
                onError: (errors) => AturGalat(errors),
                onSuccess: () => {
                    AturTerbuka(false);
                    konteks.bersihkan();
                },
            },
        );

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Tombol varian="sekunder" disabled={terlaluBanyak} onClick={() => AturTerbuka(true)}>
                Beri tambahan (THR, bonus)
            </Tombol>
            {terlaluBanyak ? (
                <p className="text-keterangan text-bahaya">Maksimal {MaksTambahanMassal} karyawan sekali proses.</p>
            ) : null}
            {terbuka ? (
                <DialogFormulir
                    judul={`Beri tambahan ke ${uuid.length.toLocaleString('id-ID')} karyawan`}
                    keterangan="Jumlah yang sama ditambahkan ke tambahan setiap karyawan terpilih. Tambahan yang sudah ada tidak diganti."
                    galatUmum={galat.Umum}
                    saatTutup={() => AturTerbuka(false)}
                >
                    <form
                        noValidate
                        className="flex flex-col gap-3"
                        onSubmit={(peristiwa) => {
                            peristiwa.preventDefault();
                            Kirim();
                        }}
                    >
                        <BidangUang
                            label="Tambahan per karyawan"
                            nilai={tambahan}
                            saatBerubah={AturTambahan}
                            galat={galat.Tambahan}
                            required
                        />
                        <BidangTeks
                            label="Catatan (opsional)"
                            nilai={catatan}
                            saatBerubah={AturCatatan}
                            galat={galat.Catatan}
                            maxLength={255}
                            keterangan="Contoh: THR 2026. Menggantikan catatan di baris terpilih."
                        />
                        {galat.Uuid ? <p className="text-keterangan font-semibold text-bahaya">{galat.Uuid}</p> : null}
                        <DialogFooter className="sm:justify-start">
                            <Tombol type="submit" memproses={memproses} disabled={tambahan.trim() === ''}>
                                Tambahkan
                            </Tombol>
                            <Tombol varian="sekunder" onClick={() => AturTerbuka(false)}>
                                Batal
                            </Tombol>
                        </DialogFooter>
                    </form>
                </DialogFormulir>
            ) : null}
        </div>
    );
}
