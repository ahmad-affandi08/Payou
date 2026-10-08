/** Status pendaftaran merchant pembayaran (DOKU Partner) yang dilihat tenant. */
export type StatusPendaftaranMerchant = 'Draf' | 'Dikirim' | 'Ditinjau' | 'Aktif' | 'Ditolak' | 'Gagal';

/** Pendaftaran tersimpan. NIK & rekening hanya tersamar; foto tidak pernah dikirim, hanya penanda tersimpan. */
export type PendaftaranMerchantTenant = {
    Uuid: string;
    Status: StatusPendaftaranMerchant;
    LabelStatus: string;
    BisaDiubah: boolean;
    NamaPemilik: string | null;
    NikTersamar: string | null;
    Email: string | null;
    NomorHp: string | null;
    NamaUsaha: string | null;
    AlamatUsaha: string | null;
    IdReferensiBank: number | null;
    NamaBank: string | null;
    NamaPemilikRekening: string | null;
    RekeningTersamar: string | null;
    FotoTersimpan: { Ktp: boolean; Swafoto: boolean; BuktiUsaha: boolean };
    PesanGalat: string | null;
    AlasanPenolakan: string | null;
    DikirimPada: string | null;
    DisetujuiPada: string | null;
};

export type PropsAktivasiQris = {
    LayananTersedia: boolean;
    BatasFotoMb: number;
    DaftarBank: { Id: number; Nama: string }[];
    Awal: { NamaPemilik: string; Email: string; NamaUsaha: string };
    Pendaftaran: PendaftaranMerchantTenant | null;
};
