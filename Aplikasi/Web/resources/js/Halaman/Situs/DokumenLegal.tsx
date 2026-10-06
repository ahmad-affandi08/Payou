import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import TeksKaya from '@/Komponen/Situs/TeksKaya';
import { Separator } from '@/Komponen/Ui/separator';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakSitus from '@/TataLetak/TataLetakSitus';

type PropsDokumenLegal = {
    Dokumen: { Label: string; Judul: string; Versi: number; BerlakuMulai: string; Isi: string; Terjadwal: boolean };
};

/**
 * Dokumen legal versi yang berlaku (P-06, F-00 langkah 1), di domain pemasaran `payoung.id`.
 *
 * Dua hal yang diperbaiki di D-28: halaman ini dulu berdiri sendiri tanpa kepala & kaki situs, padahal alamatnya
 * ada di domain pemasaran dan pengunjung sampai ke sini dari kaki situs — jadi tidak ada jalan kembali. Isinya juga
 * ditulis Markdown (`DokumenLegal.Isi`, §13.6) tetapi dirender sebagai teks mentah, sehingga `#` dan `-` tampil
 * sebagai tanda baca dan dokumen sepanjang belasan pasal jadi satu dinding teks. `TeksKaya` merender subset yang
 * sama dengan artikel blog, tanpa HTML sama sekali.
 */
export default function HalamanDokumenLegalPublik({ Dokumen }: PropsDokumenLegal) {
    return (
        <TataLetakSitus judul={Dokumen.Label}>
            <main className="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-10">
                <JudulHalaman>{Dokumen.Judul}</JudulHalaman>
                <p className="text-keterangan text-teks-sekunder">
                    Versi {Dokumen.Versi} | {Dokumen.Terjadwal ? 'akan berlaku mulai' : 'berlaku mulai'}{' '}
                    {FormatTanggal(Dokumen.BerlakuMulai)}
                </p>
                <Separator className="bg-garis" />
                <TeksKaya teks={Dokumen.Isi} className="text-isi text-teks-utama" />
            </main>
        </TataLetakSitus>
    );
}
