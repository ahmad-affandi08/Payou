import { router } from '@inertiajs/react';
import { useState } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import type { KonteksAksiMassal } from '@/Komponen/TabelData/TabelData';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { JumlahkanDesimal } from '@/Pustaka/HitungDesimal';
import { FormatRupiah } from '@/Pustaka/Format';

/** Batas server `TerimaPembayaranLanggananMassal::MAKS`. */
export const MaksVerifikasiMassal = 50;

/**
 * Terima banyak bukti transfer sekaligus di antrean verifikasi (P-08). Jumlah diterima = jumlah yang dilaporkan di tiap
 * pembayaran, jadi petugas wajib menegaskan bahwa setiap mutasi rekening sudah dicocokkan sebelum tombol aktif.
 * Pembayaran yang tidak cocok dengan total tagihan atau sudah tidak menunggu dilewati server dan alasannya dirangkum.
 */
export default function AksiMassalVerifikasi<T extends { Uuid: string; Jumlah: string }>({
    konteks,
}: {
    konteks: KonteksAksiMassal<T>;
}) {
    const [terbuka, AturTerbuka] = useState(false);
    const [dicocokkan, AturDicocokkan] = useState(false);
    const [catatan, AturCatatan] = useState('');
    const [galat, AturGalat] = useState<Record<string, string>>({});
    const [memproses, AturMemproses] = useState(false);
    const uuid = konteks.terpilih.map((b) => b.Uuid);
    const total = JumlahkanDesimal(konteks.terpilih.map((b) => b.Jumlah));
    const terlaluBanyak = uuid.length > MaksVerifikasiMassal;

    const Kirim = () =>
        router.post(
            '/tagihan/pembayaran/terima-massal',
            { Uuid: uuid, SudahDicocokkan: dicocokkan, Catatan: catatan },
            {
                preserveScroll: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
                onError: (errors) => AturGalat(errors),
                onSuccess: () => {
                    AturTerbuka(false);
                    AturDicocokkan(false);
                    konteks.bersihkan();
                },
            },
        );

    return (
        <div className="flex flex-wrap items-center gap-2">
            {terlaluBanyak ? (
                <p className="w-full text-keterangan text-bahaya">
                    Maksimal {MaksVerifikasiMassal} pembayaran sekali proses. Kurangi pilihan.
                </p>
            ) : null}
            <Tombol disabled={terlaluBanyak} onClick={() => AturTerbuka(true)}>
                Terima {uuid.length.toLocaleString('id-ID')} pembayaran…
            </Tombol>
            {terbuka ? (
                <DialogFormulir
                    judul={`Terima ${uuid.length.toLocaleString('id-ID')} pembayaran`}
                    keterangan={`Total yang dilaporkan ${FormatRupiah(total)}. Setiap tagihan menjadi lunas dan langganan tenant aktif. Pembayaran yang jumlahnya tidak sama dengan total tagihan dilewati.`}
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
                        <KotakCentang
                            label="Saya sudah mencocokkan setiap bukti dengan mutasi rekening"
                            nilai={dicocokkan}
                            saatBerubah={AturDicocokkan}
                        />
                        {galat.SudahDicocokkan ? (
                            <p className="text-keterangan font-semibold text-bahaya">{galat.SudahDicocokkan}</p>
                        ) : null}
                        <BidangTeks
                            label="Catatan (opsional)"
                            nilai={catatan}
                            saatBerubah={AturCatatan}
                            galat={galat.Catatan}
                            maxLength={500}
                            keterangan="Contoh: Mutasi BCA 23/09. Dicatat di audit tiap pembayaran."
                        />
                        <DialogFooter className="sm:justify-start">
                            <Tombol type="submit" memproses={memproses} disabled={!dicocokkan}>
                                Terima semua
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
