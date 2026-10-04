import { useId, useState } from 'react';

import { UraiTeksJam } from '@/Pustaka/Tanggal';

import PemilihJam from './PemilihJam';
import PemilihTanggal from './PemilihTanggal';

type PropsPemilihTanggalWaktu = {
    label: string;
    /** `TTTT-BB-HHTjj:mm` (format `datetime-local`) atau string kosong. */
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string | undefined;
    disabled?: boolean;
    /** Jam yang dipakai saat tanggal dipilih tetapi jam belum diisi. */
    jamBawaan?: string;
};

const polaTanggalWaktu = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/;

function PecahNilai(nilai: string): [string, string] {
    const cocok = polaTanggalWaktu.exec(nilai);

    return cocok === null ? ['', ''] : [cocok[1] ?? '', cocok[2] ?? ''];
}

/**
 * Tanggal + jam (24 jam) untuk jadwal berlaku, pemeliharaan, dsb. Tanggal memakai `PemilihTanggal`
 * (juga menerima tempelan `2026-11-01T08:00`), jam lewat `PemilihJam` (ketik `jj:mm` atau pilih). Nilai keluar setara `datetime-local`.
 */
export default function PemilihTanggalWaktu({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    disabled = false,
    jamBawaan = '00:00',
}: PropsPemilihTanggalWaktu) {
    const idJam = useId();
    const [tanggal, jam] = PecahNilai(nilai);
    const [teksJam, AturTeksJam] = useState(jam);
    const [jamTerakhir, AturJamTerakhir] = useState(jam);

    if (jam !== jamTerakhir) {
        AturJamTerakhir(jam);
        AturTeksJam(jam);
    }

    const Kirim = (tanggalBaru: string, jamBaru: string) => {
        const hasil = tanggalBaru === '' ? '' : `${tanggalBaru}T${jamBaru === '' ? jamBawaan : jamBaru}`;
        const [, jamHasil] = PecahNilai(hasil);
        AturJamTerakhir(jamHasil);
        AturTeksJam(jamHasil);
        saatBerubah(hasil);
    };

    return (
        <div className="flex flex-col gap-1">
            <div className="grid grid-cols-[minmax(0,1fr)_7.5rem] items-start gap-2">
                <PemilihTanggal
                    label={label}
                    nilai={tanggal}
                    saatBerubah={(baru) => {
                        const tempelan = polaTanggalWaktu.exec(baru);

                        if (tempelan) {
                            Kirim(tempelan[1] ?? '', tempelan[2] ?? '');
                        } else {
                            Kirim(baru, UraiTeksJam(teksJam) ?? '');
                        }
                    }}
                    terimaTanggalWaktu
                    galat={galat}
                    keterangan={keterangan}
                    disabled={disabled}
                    className="[&_input]:min-w-0"
                />
                <PemilihJam
                    id={idJam}
                    label="Jam"
                    labelAria={`Jam ${label}`}
                    nilai={teksJam}
                    contoh="jj:mm"
                    disabled={disabled || tanggal === ''}
                    ringkas
                    saatBerubah={(baru) => {
                        AturTeksJam(baru);
                        const sah = UraiTeksJam(baru);
                        if (sah !== undefined) {
                            Kirim(tanggal, sah);
                        }
                    }}
                />
            </div>
        </div>
    );
}
