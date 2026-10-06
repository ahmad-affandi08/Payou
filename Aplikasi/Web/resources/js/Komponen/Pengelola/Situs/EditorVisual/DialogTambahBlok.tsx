import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Komponen/Ui/dialog';

import { AmbilInfoBlok, URUTAN_KATEGORI } from './PustakaBlok';

type PropsDialogTambahBlok = {
    terbuka: boolean;
    saatTutup: () => void;
    /** Kunci jenis blok yang tersedia (urutan dari server). */
    jenis: string[];
    label: Record<string, string>;
    saatPilih: (jenis: string) => void;
    keterangan: string;
};

/** Galeri "Tambah blok": jenis blok dikelompokkan, tiap kartu menjelaskan fungsinya (D-63). */
export default function DialogTambahBlok({
    terbuka,
    saatTutup,
    jenis,
    label,
    saatPilih,
    keterangan,
}: PropsDialogTambahBlok) {
    return (
        <Dialog open={terbuka} onOpenChange={(buka) => (buka ? undefined : saatTutup())}>
            <DialogContent className="max-h-[88dvh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="text-judul font-semibold text-teks-utama">Tambah blok</DialogTitle>
                    <DialogDescription className="text-isi text-teks-sekunder">{keterangan}</DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-6">
                    {URUTAN_KATEGORI.map((kategori) => {
                        const daftar = jenis.filter((j) => AmbilInfoBlok(j).kategori === kategori.kunci);

                        if (daftar.length === 0) {
                            return null;
                        }

                        return (
                            <section key={kategori.kunci} aria-label={kategori.judul} className="flex flex-col gap-2">
                                <div>
                                    <h3 className="text-label font-semibold text-teks-utama">{kategori.judul}</h3>
                                    <p className="text-keterangan text-teks-sekunder">{kategori.keterangan}</p>
                                </div>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {daftar.map((j) => {
                                        const info = AmbilInfoBlok(j);
                                        const Ikon = info.ikon;

                                        return (
                                            <button
                                                key={j}
                                                type="button"
                                                onClick={() => saatPilih(j)}
                                                className="flex items-start gap-3 rounded-panel border border-garis bg-permukaan p-3 text-left outline-none transition-colors hover:border-brand hover:bg-brand-lembut focus-visible:ring-2 focus-visible:ring-brand"
                                            >
                                                <span className="flex size-10 shrink-0 items-center justify-center rounded-kontrol bg-brand-lembut text-brand">
                                                    <Ikon className="size-5" aria-hidden />
                                                </span>
                                                <span className="flex min-w-0 flex-col gap-0.5">
                                                    <span className="text-isi font-semibold text-teks-utama">
                                                        {label[j] ?? j}
                                                    </span>
                                                    <span className="text-keterangan text-teks-sekunder">
                                                        {info.deskripsi}
                                                    </span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </section>
                        );
                    })}
                </div>
            </DialogContent>
        </Dialog>
    );
}
