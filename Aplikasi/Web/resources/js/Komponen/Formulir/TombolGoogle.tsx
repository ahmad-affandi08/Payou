import gambarGoogle from '@/Aset/Merek/Google.webp';
import { Button } from '@/Komponen/Ui/button';

type PropsTombolGoogle = {
    /** Alamat awal alur, mis. `/masuk/google` atau `/masuk/google?tujuan=daftar`. */
    href: string;
    children: string;
};

/**
 * Tombol "Masuk/Daftar/Tautkan dengan Google" (D-57). Tautan biasa, bukan kunjungan Inertia: alurnya mengalihkan ke
 * Google lalu kembali lewat panggilan balik server. Logo "G" resmi tanpa diubah warnanya, di atas latar putih sesuai
 * pedoman merek Google; teks selalu tampil.
 */
export default function TombolGoogle({ href, children }: PropsTombolGoogle) {
    return (
        <Button
            asChild
            variant="outline"
            className="h-11 w-full gap-3 border-garis-input bg-permukaan text-label font-semibold text-teks-utama"
        >
            <a href={href}>
                <img src={gambarGoogle} alt="" width={20} height={20} className="size-5" />
                {children}
            </a>
        </Button>
    );
}

/** Pemisah "atau" antara formulir kata sandi dan tombol Google. */
export function PemisahAtau() {
    return (
        <div className="flex items-center gap-3" role="separator" aria-label="atau">
            <span className="h-px flex-1 bg-garis" />
            <span className="text-keterangan text-teks-sekunder">atau</span>
            <span className="h-px flex-1 bg-garis" />
        </div>
    );
}
