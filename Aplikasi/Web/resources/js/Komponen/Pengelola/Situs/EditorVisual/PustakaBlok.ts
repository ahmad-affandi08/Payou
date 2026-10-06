import {
    BadgeCheck,
    ChartColumn,
    CircleHelp,
    Download,
    FileText,
    Handshake,
    Image,
    LayoutTemplate,
    Mail,
    Megaphone,
    Phone,
    Play,
    Quote,
    Rows3,
    Sparkles,
    Store,
    Tag,
    type LucideIcon,
} from 'lucide-react';

export type KategoriBlok = 'Pembuka' | 'Isi' | 'Bukti' | 'Ajakan';

export const URUTAN_KATEGORI: { kunci: KategoriBlok; judul: string; keterangan: string }[] = [
    { kunci: 'Pembuka', judul: 'Pembuka', keterangan: 'Bagian paling atas halaman' },
    { kunci: 'Isi', judul: 'Isi halaman', keterangan: 'Menjelaskan produk, jenis usaha, dan fitur' },
    { kunci: 'Bukti', judul: 'Bukti & harga', keterangan: 'Meyakinkan pengunjung' },
    { kunci: 'Ajakan', judul: 'Ajakan & kontak', keterangan: 'Mengajak pengunjung bertindak' },
];

export type InfoBlok = { kategori: KategoriBlok; deskripsi: string; ikon: LucideIcon };

/** Penjelasan singkat tiap jenis blok untuk galeri "Tambah blok" (D-63). Kunci = jenis blok di `SkemaBagianSitus`. */
export const INFO_BLOK: Record<string, InfoBlok> = {
    Hero: {
        kategori: 'Pembuka',
        deskripsi: 'Judul besar, penjelasan singkat, tombol, dan gambar produk.',
        ikon: LayoutTemplate,
    },
    Keunggulan: {
        kategori: 'Isi',
        deskripsi: 'Kartu berikon untuk fitur atau alasan memilih Anda (2 sampai 4 kolom).',
        ikon: Sparkles,
    },
    Sektor: {
        kategori: 'Isi',
        deskripsi: 'Daftar jenis usaha yang dilayani, masing-masing dengan tautan.',
        ikon: Store,
    },
    GambarTeks: {
        kategori: 'Isi',
        deskripsi: 'Gambar di satu sisi dan teks serta poin-poin di sisi lain.',
        ikon: Image,
    },
    TeksBebas: { kategori: 'Isi', deskripsi: 'Tulisan panjang: paragraf, subjudul, daftar, tautan.', ikon: FileText },
    Video: { kategori: 'Isi', deskripsi: 'Video YouTube yang tampil di halaman (tanpa cookie pelacak).', ikon: Play },
    Statistik: { kategori: 'Bukti', deskripsi: 'Angka-angka penting. Isi hanya dengan data nyata.', ikon: ChartColumn },
    Testimoni: {
        kategori: 'Bukti',
        deskripsi: 'Kutipan pelanggan dengan nama dan usaha. Isi hanya dengan ulasan nyata.',
        ikon: Quote,
    },
    LogoMitra: { kategori: 'Bukti', deskripsi: 'Deretan logo klien atau mitra.', ikon: Handshake },
    Harga: { kategori: 'Bukti', deskripsi: 'Tabel paket dan harga, otomatis dari Katalog paket.', ikon: Tag },
    Faq: {
        kategori: 'Bukti',
        deskripsi: 'Pertanyaan yang sering diajukan, bisa dibuka satu per satu.',
        ikon: CircleHelp,
    },
    Cta: {
        kategori: 'Ajakan',
        deskripsi: 'Kotak ajakan di dasar halaman dengan satu atau dua tombol.',
        ikon: Megaphone,
    },
    FormulirProspek: {
        kategori: 'Ajakan',
        deskripsi: 'Formulir kontak atau minta demo. Isian masuk ke tab Prospek.',
        ikon: Mail,
    },
    Kontak: { kategori: 'Ajakan', deskripsi: 'WhatsApp, email, telepon, dan alamat dari Pengaturan.', ikon: Phone },
    UnduhAplikasi: {
        kategori: 'Ajakan',
        deskripsi: 'Tombol unduh Android, iPhone/iPad, dan Windows dari Pengaturan.',
        ikon: Download,
    },
};

const CADANGAN: InfoBlok = { kategori: 'Isi', deskripsi: '', ikon: Rows3 };

export function AmbilInfoBlok(jenis: string): InfoBlok {
    return INFO_BLOK[jenis] ?? CADANGAN;
}

/** Ikon status di daftar blok yang tidak punya jenis dikenal. */
export const IkonCadangan = BadgeCheck;
