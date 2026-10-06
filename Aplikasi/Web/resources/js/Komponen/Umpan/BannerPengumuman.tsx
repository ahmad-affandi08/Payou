import { XIcon } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/Komponen/Ui/button';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import type { PengumumanPlatform } from '@/Tipe/Aplikasi';

const KUNCI_SIMPAN = 'payoung.pengumuman-ditutup';

const JenisPemberitahuan: Record<PengumumanPlatform['Jenis'], 'info' | 'sukses' | 'peringatan' | 'bahaya'> = {
    Info: 'info',
    YangBaru: 'sukses',
    Pemeliharaan: 'peringatan',
    Penting: 'bahaya',
};

/** Uuid pengumuman yang sudah ditutup pengguna ini (kenyamanan per peramban; boleh hilang). */
export function BacaDitutup(): string[] {
    try {
        const isi: unknown = JSON.parse(window.localStorage.getItem(KUNCI_SIMPAN) ?? '[]');

        return Array.isArray(isi) ? isi.filter((u): u is string => typeof u === 'string') : [];
    } catch {
        return [];
    }
}

function SimpanDitutup(daftar: string[]) {
    try {
        // Simpan paling banyak 50 terakhir supaya tidak tumbuh tanpa batas.
        window.localStorage.setItem(KUNCI_SIMPAN, JSON.stringify(daftar.slice(-50)));
    } catch {
        // Penyimpanan peramban tidak tersedia: pengumuman tampil lagi di halaman berikutnya.
    }
}

/**
 * P-10 PGL-19: banner pengumuman platform di back-office. Penting & Pemeliharaan tidak bisa ditutup selama masa
 * tampilnya; Info & Yang baru bisa ditutup (diingat di peramban ini).
 */
export default function BannerPengumuman({ pengumuman }: { pengumuman: PengumumanPlatform[] }) {
    const [ditutup, AturDitutup] = useState<string[]>(BacaDitutup);
    const tampil = pengumuman.filter((p) => !(p.BolehDitutup && ditutup.includes(p.Uuid)));

    if (tampil.length === 0) {
        return null;
    }

    const Tutup = (uuid: string) => {
        const baru = [...ditutup, uuid];
        AturDitutup(baru);
        SimpanDitutup(baru);
    };

    return (
        <div className="flex flex-col gap-2" aria-label="Pengumuman Payoung" role="region">
            {tampil.map((p) => (
                <Pemberitahuan key={p.Uuid} jenis={JenisPemberitahuan[p.Jenis]} judul={`${p.LabelJenis}: ${p.Judul}`}>
                    <div className="flex items-start gap-2">
                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                            <p className="break-words whitespace-pre-line">{p.Isi}</p>
                            {p.PemeliharaanMulai && p.PemeliharaanSelesai ? (
                                <p className="font-semibold">
                                    Jadwal: {FormatTanggalWaktu(p.PemeliharaanMulai)} sampai{' '}
                                    {FormatTanggalWaktu(p.PemeliharaanSelesai)}
                                </p>
                            ) : null}
                            {p.Tautan ? (
                                <a
                                    href={p.Tautan}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="font-semibold text-brand underline"
                                >
                                    Selengkapnya
                                </a>
                            ) : null}
                        </div>
                        {p.BolehDitutup ? (
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Tutup pengumuman ${p.Judul}`}
                                onClick={() => Tutup(p.Uuid)}
                            >
                                <XIcon aria-hidden="true" />
                            </Button>
                        ) : null}
                    </div>
                </Pemberitahuan>
            ))}
        </div>
    );
}
