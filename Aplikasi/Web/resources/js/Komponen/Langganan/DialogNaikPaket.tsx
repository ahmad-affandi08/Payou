import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { Button } from '@/Komponen/Ui/button';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { FormatRupiah } from '@/Pustaka/Format';
import type { PenawaranFitur } from '@/Tipe/Aplikasi';

type PropsDialogNaikPaket = {
    kunci: string;
    penawaran: PenawaranFitur;
    namaPaket: string | null;
    /** Pemegang izin `langganan.kelola` (Pemilik) bisa langsung memilih paket atau meminta add-on. */
    bolehKelola: boolean;
    saatTutup: () => void;
};

/**
 * D-23: dialog ajakan untuk fitur di luar paket langganan (menu tetap tampil, seperti Majoo). Menawarkan paket termurah
 * yang memuat fitur itu (tautan ke Langganan dengan paket terpilih) dan add-on bila ada. D-49: add-on dibeli mandiri
 * (tagihan prorata sampai akhir periode, aktif setelah dibayar); bila belum memenuhi syarat, alasannya ditampilkan dan
 * permintaan bisa dikirim ke tim kami lewat tiket. Anggota tanpa izin langganan diminta menghubungi Pemilik.
 */
export default function DialogNaikPaket({ kunci, penawaran, namaPaket, bolehKelola, saatTutup }: PropsDialogNaikPaket) {
    const [memproses, AturMemproses] = useState(false);
    const { Paket: paket, Addon: addon } = penawaran;
    const hargaPaket = paket?.HargaBulanan ? ` mulai ${FormatRupiah(paket.HargaBulanan)}/bulan` : '';

    return (
        <DialogFormulir
            judul={`${penawaran.Nama} belum termasuk paket ${namaPaket ?? 'Anda'}`}
            keterangan={
                <>
                    {paket ? (
                        <span>
                            Fitur ini tersedia di paket <strong>{paket.Nama}</strong>
                            {hargaPaket}.
                        </span>
                    ) : null}
                    {addon ? (
                        <span>
                            {paket ? 'Atau tambahkan' : 'Tambahkan'} add-on <strong>{addon.Nama}</strong> seharga{' '}
                            {FormatRupiah(addon.HargaBulanan)}/bulan tanpa ganti paket.
                            {addon.BisaDibeli && addon.HargaProrata
                                ? ` Bila dibeli sekarang, tagihan pertama ${FormatRupiah(addon.HargaProrata)} (prorata sampai akhir periode, belum termasuk pajak).`
                                : ''}
                        </span>
                    ) : null}
                    {addon && !addon.BisaDibeli && addon.AlasanTidakBisa ? <span>{addon.AlasanTidakBisa}</span> : null}
                    {!paket && !addon ? <span>Hubungi kami untuk mengaktifkan fitur ini.</span> : null}
                    {!bolehKelola ? <span>Minta Pemilik usaha untuk naik paket atau menambah add-on.</span> : null}
                </>
            }
            saatTutup={saatTutup}
        >
            <DialogFooter className="sm:justify-start">
                {bolehKelola && addon?.BisaDibeli ? (
                    <Tombol
                        varian="utama"
                        memproses={memproses}
                        onClick={() =>
                            router.post(
                                '/kelola/langganan/addon/beli',
                                { KodeAddon: addon.Kode },
                                { onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
                            )
                        }
                    >
                        Beli add-on {addon.Nama}
                    </Tombol>
                ) : null}
                {bolehKelola && paket ? (
                    <Button
                        asChild
                        variant={addon?.BisaDibeli ? 'outline' : 'default'}
                        className="h-8 pointer-coarse:h-11"
                    >
                        <Link href={`/kelola/langganan?paket=${encodeURIComponent(paket.Kode)}`}>
                            Lihat paket {paket.Nama}
                        </Link>
                    </Button>
                ) : null}
                {bolehKelola && addon && !addon.BisaDibeli ? (
                    <Tombol
                        varian="sekunder"
                        memproses={memproses}
                        onClick={() =>
                            router.post(
                                '/kelola/langganan/addon',
                                { KunciFitur: kunci },
                                { onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
                            )
                        }
                    >
                        Minta lewat tim kami
                    </Tombol>
                ) : null}
                {bolehKelola && !paket && !addon ? (
                    <Button asChild variant="outline" className="h-8 pointer-coarse:h-11">
                        <Link href="/kelola/bantuan/buat">Hubungi kami</Link>
                    </Button>
                ) : null}
                <Tombol varian="sekunder" onClick={saatTutup}>
                    Nanti saja
                </Tombol>
            </DialogFooter>
        </DialogFormulir>
    );
}
