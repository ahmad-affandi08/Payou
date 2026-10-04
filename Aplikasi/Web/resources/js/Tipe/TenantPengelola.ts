import type { Pilihan } from '@/Tipe/Pengelola';

/** Props halaman tenant Platform Pengelola (P-07), sesuai TenantKontroler & TampilanTenant di Backend. */
export type BarisTenant = {
    Uuid: string;
    Nama: string;
    Slug: string;
    EmailPemilik: string | null;
    KodePaket: string | null;
    StatusLangganan: string | null;
    TrialBerakhirPada: string | null;
    Penanda: string | null;
    DibuatPada: string;
};

export type LanggananTenant = {
    Status: string;
    StatusSebelumDitangguhkan: string | null;
    StatusSetelahDiaktifkan: string | null;
    BisaDiaktifkan: boolean;
    KodePaket: string;
    NamaPaket: string;
    TrialBerakhirPada: string | null;
    PeriodeMulai: string | null;
    PeriodeSelesai: string | null;
    SiklusTagihan: string;
    PerpanjanganTrial: number;
    SisaPerpanjanganTrial: number;
};

export type OverrideTenant = {
    Uuid: string;
    Jenis: 'Batas' | 'Fitur' | 'Trial';
    Kunci: string;
    Nilai: string | null;
    BerakhirPada: string;
    Aktif: boolean;
    Alasan: string;
    DibuatOleh: string;
    DibuatPada: string;
};

export type RiwayatTindakan = {
    Id: number;
    Aksi: string;
    Pelaku: string;
    NilaiLama: Record<string, unknown> | null;
    NilaiBaru: Record<string, unknown> | null;
    Alasan: string | null;
    DibuatPada: string;
};

export type Tampilan360 = {
    /** P-12: mitra perujuk (null = mendaftar langsung). */
    MitraPerujuk: { Uuid: string; Kode: string; Nama: string; MulaiPada: string } | null;
    Profil: {
        Uuid: string;
        Nama: string;
        Slug: string;
        Npwp: string | null;
        Pkp: boolean;
        ZonaWaktu: string;
        Status: string;
        Penanda: string | null;
        DibuatPada: string;
        TemplateSektor: string[];
    };
    Langganan: LanggananTenant | null;
    Pemakaian: { Label: string; Pakai: number; Batas: number | null }[];
    /** D-49: add-on yang dimiliki tenant. */
    Addon: { Kode: string; Nama: string; Jumlah: number; SelesaiPada: string; Aktif: boolean; Berhenti: boolean }[];
    Organisasi: {
        Outlet: { Kode: string; Nama: string; TemplateSektor: string | null; KodeKota: string | null }[];
        JumlahGudang: number;
        JumlahMerek: number;
    };
    Anggota: {
        Nama: string;
        Email: string;
        NoHp: string | null;
        EmailTerverifikasi: boolean;
        Pemilik: boolean;
        Status: string;
    }[];
    PersetujuanLegal: { Jenis: string; Versi: number | null; Pengguna: string; DisetujuiPada: string }[];
    Override: OverrideTenant[];
    Catatan: { Uuid: string; Isi: string; Penulis: string; DibuatPada: string }[];
    Riwayat: RiwayatTindakan[];
};

export type PilihanTenant = {
    KategoriPenangguhan: Pilihan[];
    Penanda: Pilihan[];
    KolomBatas: string[];
    Fitur: Pilihan[];
};

export type AturanTenant = { MaksHariTrial: number; MaksKaliTrial: number; MaksHariOverride: number };

export const labelBatas: Record<string, string> = {
    BatasOutlet: 'Outlet',
    BatasPerangkatPerOutlet: 'Perangkat per outlet',
    BatasPengguna: 'Pengguna',
    BatasSku: 'SKU',
    KuotaPesanWaBulanan: 'Kuota pesan WA per bulan',
    BatasPenyimpananMb: 'Penyimpanan (MB)',
};
