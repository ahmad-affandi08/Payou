import { useEffect, useId, useMemo, useRef, useState, type ChangeEvent } from 'react';

import { GalatBidang, KerangkaBidang, KeteranganBidang, LabelBidang } from '@/Komponen/Formulir/BagianBidang';
import { PeriksaBerkasGambar } from '@/Komponen/Formulir/BidangGambar';
import { Button } from '@/Komponen/Ui/button';
import { Input } from '@/Komponen/Ui/input';
import { FormatUkuranBerkas } from '@/Pustaka/FormatUkuran';

const EKSTENSI_FOTO = ['jpg', 'jpeg', 'png'];

type PropsBidangFotoKyc = {
    label: string;
    berkas: File | null;
    saatBerubah: (berkas: File | null) => void;
    /** Kamera yang dibuka di HP: depan untuk selfie, belakang untuk dokumen dan tempat usaha. */
    kamera: 'user' | 'environment';
    ukuranMaksimalMb: number;
    /** Foto sudah tersimpan dari kunjungan sebelumnya (isinya tidak pernah ditampilkan ulang). */
    sudahTersimpan?: boolean;
    keterangan?: string;
    galat?: string | undefined;
    disabled?: boolean;
};

/**
 * Foto KYC (KTP, selfie, tempat usaha): ambil dari kamera HP atau pilih berkas, dengan pratinjau dan pemeriksaan jenis &
 * ukuran di peramban (server tetap memeriksa isi berkasnya). Foto yang sudah tersimpan di server tidak ditampilkan lagi:
 * hanya ada penandanya, dan memilih foto baru menggantikannya.
 */
export default function BidangFotoKyc({
    label,
    berkas,
    saatBerubah,
    kamera,
    ukuranMaksimalMb,
    sudahTersimpan = false,
    keterangan,
    galat,
    disabled,
}: PropsBidangFotoKyc) {
    const id = useId();
    const masukan = useRef<HTMLInputElement>(null);
    const [galatLokal, AturGalatLokal] = useState<string | null>(null);
    const pratinjau = useMemo(
        () => (berkas !== null && typeof URL.createObjectURL === 'function' ? URL.createObjectURL(berkas) : null),
        [berkas],
    );
    const pesanGalat = galatLokal ?? galat;

    useEffect(
        () => () => {
            if (pratinjau !== null) {
                URL.revokeObjectURL(pratinjau);
            }
        },
        [pratinjau],
    );

    const Pilih = (peristiwa: ChangeEvent<HTMLInputElement>) => {
        const terpilih = peristiwa.target.files?.[0] ?? null;

        if (terpilih === null) {
            return;
        }

        const pesan = PeriksaBerkasGambar(terpilih, EKSTENSI_FOTO, ukuranMaksimalMb * 1024);
        AturGalatLokal(pesan);

        if (pesan !== null) {
            peristiwa.target.value = '';

            return;
        }

        saatBerubah(terpilih);
    };

    const Batalkan = () => {
        saatBerubah(null);
        AturGalatLokal(null);

        if (masukan.current) {
            masukan.current.value = '';
        }
    };

    return (
        <KerangkaBidang galat={pesanGalat} className="gap-2">
            <LabelBidang htmlFor={id}>{label}</LabelBidang>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                <div className="flex size-32 shrink-0 items-center justify-center overflow-hidden rounded-kontrol border border-garis bg-latar">
                    {pratinjau ? (
                        <img
                            src={pratinjau}
                            alt={`Pratinjau ${label}`}
                            className="max-h-full max-w-full object-contain"
                        />
                    ) : (
                        <span className="px-2 text-center text-keterangan text-teks-sekunder">
                            {sudahTersimpan ? 'Foto sudah tersimpan' : 'Belum ada foto'}
                        </span>
                    )}
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <Input
                        ref={masukan}
                        id={id}
                        type="file"
                        accept="image/jpeg,image/png"
                        capture={kamera}
                        onChange={Pilih}
                        disabled={disabled}
                        aria-invalid={pesanGalat ? true : undefined}
                        aria-describedby={`${id}-keterangan${pesanGalat ? ` ${id}-galat` : ''}`}
                        className="cursor-pointer py-1.5 file:mr-3 file:text-label file:font-semibold file:text-teks-utama"
                    />
                    <KeteranganBidang id={`${id}-keterangan`}>
                        {`${keterangan ? `${keterangan} ` : ''}Format JPG atau PNG, maksimal ${ukuranMaksimalMb} MB.`}
                    </KeteranganBidang>
                    {berkas ? (
                        <p className="flex flex-wrap items-center gap-2 text-keterangan text-teks-utama">
                            <span className="break-all">
                                {berkas.name} | {FormatUkuranBerkas(berkas.size)}
                            </span>
                            <Button
                                type="button"
                                variant="link"
                                onClick={Batalkan}
                                className="h-auto p-0 text-keterangan font-semibold text-brand underline"
                            >
                                Ambil ulang
                            </Button>
                        </p>
                    ) : null}
                </div>
            </div>
            <div aria-live="polite">
                {pesanGalat ? <GalatBidang id={`${id}-galat`}>{pesanGalat}</GalatBidang> : null}
            </div>
        </KerangkaBidang>
    );
}
