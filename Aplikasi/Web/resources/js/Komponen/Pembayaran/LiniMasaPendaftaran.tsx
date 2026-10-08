import { CheckIcon, CircleDotIcon, CircleIcon, XIcon } from 'lucide-react';

import type { StatusPendaftaranMerchant } from '@/Tipe/AktivasiQris';

type Langkah = { kunci: string; judul: string; keterangan: string };

type KeadaanLangkah = 'selesai' | 'berjalan' | 'menunggu' | 'gagal';

const URUTAN: StatusPendaftaranMerchant[] = ['Draf', 'Dikirim', 'Ditinjau', 'Aktif'];

/**
 * Posisi tiap langkah menurut status: `Gagal` berhenti di langkah kirim, `Ditolak` berhenti di langkah tinjau.
 */
export function HitungKeadaanLangkah(status: StatusPendaftaranMerchant): KeadaanLangkah[] {
    if (status === 'Gagal') {
        return ['selesai', 'gagal', 'menunggu', 'menunggu'];
    }

    if (status === 'Ditolak') {
        return ['selesai', 'selesai', 'gagal', 'menunggu'];
    }

    if (status === 'Aktif') {
        return ['selesai', 'selesai', 'selesai', 'selesai'];
    }

    const posisi = URUTAN.indexOf(status);

    return URUTAN.map((_, indeks) => (indeks < posisi ? 'selesai' : indeks === posisi ? 'berjalan' : 'menunggu'));
}

const ikon = {
    selesai: <CheckIcon className="size-4 text-sukses" aria-hidden="true" />,
    berjalan: <CircleDotIcon className="size-4 text-brand" aria-hidden="true" />,
    menunggu: <CircleIcon className="size-4 text-teks-sekunder" aria-hidden="true" />,
    gagal: <XIcon className="size-4 text-bahaya" aria-hidden="true" />,
} as const;

const teksKeadaan = {
    selesai: 'selesai',
    berjalan: 'sedang berjalan',
    menunggu: 'menunggu',
    gagal: 'bermasalah',
} as const;

/** Linimasa pendaftaran: Draf, Dikirim, Ditinjau (1 sampai 2 hari kerja), Disetujui. Status selalu disertai teks. */
export default function LiniMasaPendaftaran({ status }: { status: StatusPendaftaranMerchant }) {
    const keadaan = HitungKeadaanLangkah(status);
    const langkah: Langkah[] = [
        { kunci: 'draf', judul: 'Data diisi', keterangan: 'Data pemilik, rekening, dan foto tersimpan.' },
        { kunci: 'dikirim', judul: 'Dikirim ke DOKU', keterangan: 'Payoung mengirim data Anda ke DOKU.' },
        {
            kunci: 'ditinjau',
            judul: status === 'Ditolak' ? 'Ditolak DOKU' : 'Ditinjau DOKU',
            keterangan:
                status === 'Ditolak'
                    ? 'DOKU tidak bisa menyetujui pendaftaran ini.'
                    : 'Biasanya 1 sampai 2 hari kerja.',
        },
        { kunci: 'aktif', judul: 'Disetujui', keterangan: 'Akun merchant Anda siap dipakai.' },
    ];

    return (
        <ol className="grid gap-3" aria-label="Tahap pendaftaran">
            {langkah.map((satu, indeks) => (
                <li
                    key={satu.kunci}
                    className="flex gap-3"
                    aria-current={keadaan[indeks] === 'berjalan' ? 'step' : undefined}
                >
                    <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border border-garis bg-permukaan">
                        {ikon[keadaan[indeks] ?? 'menunggu']}
                    </span>
                    <div className="min-w-0">
                        <p className="text-isi font-semibold text-teks-utama">
                            {satu.judul}{' '}
                            <span className="text-keterangan font-normal text-teks-sekunder">
                                ({teksKeadaan[keadaan[indeks] ?? 'menunggu']})
                            </span>
                        </p>
                        <p className="text-keterangan text-teks-sekunder">{satu.keterangan}</p>
                    </div>
                </li>
            ))}
        </ol>
    );
}
