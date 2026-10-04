import { router } from '@inertiajs/react';

import Tombol from '@/Komponen/Formulir/Tombol';
import { AlamatPembelian } from '@/Komponen/Pembelian/BagianDokumenPembelian';
import type { KonteksAksiMassal } from '@/Komponen/TabelData/TabelData';

type BarisHutang = { Uuid: string; UuidPemasok: string | null };

/** Batas server `SimpanPembayaranHutang::MAKS_FAKTUR`. */
export const MaksFakturMassal = 100;

/**
 * Aksi untuk faktur hutang terpilih (F-04): membuka formulir pembayaran dengan semua faktur terpilih terisi sisa penuh.
 * Satu pembayaran hanya untuk satu pemasok, jadi pilihan lintas pemasok ditolak dengan penjelasan.
 */
export default function AksiMassalHutang({ konteks }: { konteks: KonteksAksiMassal<BarisHutang> }) {
    const pemasok = new Set(konteks.terpilih.map((f) => f.UuidPemasok ?? ''));
    const satuPemasok = pemasok.size === 1 && !pemasok.has('');
    const terlaluBanyak = konteks.terpilih.length > MaksFakturMassal;
    const nonaktif = !satuPemasok || terlaluBanyak;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Tombol
                varian="sekunder"
                disabled={nonaktif}
                onClick={() =>
                    router.visit(
                        `${AlamatPembelian}/pembayaran/buat?pemasok=${konteks.terpilih[0]?.UuidPemasok ?? ''}&faktur=${konteks.terpilih
                            .map((f) => f.Uuid)
                            .join(',')}`,
                    )
                }
            >
                Bayar {konteks.terpilih.length.toLocaleString('id-ID')} faktur terpilih
            </Tombol>
            {!satuPemasok ? (
                <p className="text-keterangan text-teks-sekunder">
                    Satu pembayaran hanya untuk satu pemasok. Pilih faktur dari pemasok yang sama.
                </p>
            ) : null}
            {terlaluBanyak ? (
                <p className="text-keterangan text-bahaya">Maksimal {MaksFakturMassal} faktur sekali bayar.</p>
            ) : null}
        </div>
    );
}
