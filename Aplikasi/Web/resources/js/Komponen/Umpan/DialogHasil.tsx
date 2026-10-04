import { useEffect, useState } from 'react';

import gambarBerhasil from '@/Aset/HasilAksi/Berhasil.svg';
import gambarGagal from '@/Aset/HasilAksi/Gagal.svg';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Komponen/Ui/alert-dialog';

/** Lama dialog berhasil terbuka sebelum menutup sendiri. Dialog gagal tidak pernah menutup sendiri. */
export const LAMA_DIALOG_BERHASIL_MS = 5000;

type PropsDialogHasil = {
    /** Pesan berhasil dari server (`Kilat`). */
    berhasil: string | null | undefined;
    /** Pesan gagal umum dari server (`errors.Umum`). Menang atas pesan berhasil bila keduanya ada. */
    gagal: string | undefined;
    /**
     * Penanda navigasi: berubah tiap respons halaman (objek `props` Inertia). Pesan yang sama dua kali berturut-turut
     * tetap membuka dialog lagi karena penandanya berbeda.
     */
    penanda: unknown;
};

/**
 * Galat yang datang saat dialog lain masih terbuka (konfirmasi batal/void/hapus) sudah ditampilkan dialog itu di
 * tempatnya; dialog hasil kedua di atasnya hanya membingungkan.
 */
function AdaDialogTerbuka(): boolean {
    return (
        typeof document !== 'undefined' &&
        document.querySelector('[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"]') !== null
    );
}

type IsiDialog = { jenis: 'berhasil' | 'gagal'; pesan: string };

/**
 * Hasil sebuah aksi (simpan, ubah, posting, void, gagal) sebagai dialog di tengah layar, bukan banner di atas halaman
 * yang ikut tergulir dan sering terlewat di HP. Berhasil menutup sendiri setelah beberapa detik; gagal menunggu
 * tombol "Tutup". Status permanen (langganan, verifikasi email, 2FA) tetap banner `Pemberitahuan`.
 */
export default function DialogHasil({ berhasil, gagal, penanda }: PropsDialogHasil) {
    const [isi, AturIsi] = useState<IsiDialog | null>(null);

    // Respons baru (penanda berganti) membuka dialog; menutupnya tidak boleh membukanya lagi. Disetel saat render
    // (pola "menyesuaikan state terhadap prop"), bukan di efek, supaya tidak ada satu render dengan isi lama.
    const [penandaDilihat, AturPenandaDilihat] = useState<unknown>(undefined);

    if (penandaDilihat !== penanda) {
        AturPenandaDilihat(penanda);

        if (gagal && !AdaDialogTerbuka()) {
            AturIsi({ jenis: 'gagal', pesan: gagal });
        } else if (!gagal && berhasil) {
            AturIsi({ jenis: 'berhasil', pesan: berhasil });
        }
    }

    const jenis = isi?.jenis;

    useEffect(() => {
        if (jenis !== 'berhasil') {
            return;
        }

        const pewaktu = window.setTimeout(() => AturIsi(null), LAMA_DIALOG_BERHASIL_MS);

        return () => window.clearTimeout(pewaktu);
    }, [jenis, isi]);

    const berhasilTampil = isi?.jenis === 'berhasil';

    return (
        <AlertDialog open={isi !== null} onOpenChange={(buka) => (buka ? undefined : AturIsi(null))}>
            <AlertDialogContent
                size="sm"
                data-jenis={isi?.jenis}
                className="items-center rounded-panel p-6 shadow-none"
            >
                <img
                    src={berhasilTampil ? gambarBerhasil : gambarGagal}
                    alt=""
                    aria-hidden="true"
                    width={240}
                    height={160}
                    draggable={false}
                    className="mx-auto h-28 w-auto"
                />
                <AlertDialogHeader className="place-items-center text-center">
                    <AlertDialogTitle className="text-subjudul font-semibold text-teks-utama">
                        {berhasilTampil ? 'Berhasil' : 'Belum berhasil'}
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-isi text-teks-sekunder wrap-anywhere">
                        {isi?.pesan}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter className="sm:justify-center">
                    <AlertDialogAction
                        autoFocus
                        className="h-8 px-6 text-label font-semibold pointer-coarse:h-11"
                        variant={berhasilTampil ? 'default' : 'outline'}
                    >
                        {berhasilTampil ? 'Oke' : 'Tutup'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
