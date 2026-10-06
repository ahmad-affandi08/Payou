import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import PemilihTanggalWaktu from '@/Komponen/Tanggal/PemilihTanggalWaktu';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { UbahIsoKeWaktuLokal, UbahWaktuLokalKeIsoUtc } from '@/Pustaka/Tanggal';

type PropsDialogJadwalTerbit = {
    jadwalSaatIni: string | null;
    memproses: boolean;
    galat?: string | undefined;
    saatTutup: () => void;
    /** `null` membatalkan jadwal. */
    saatSimpan: (iso: string | null) => void;
};

/** Dialog "Jadwalkan terbit": draf pada saat jadwal tiba yang diterbitkan otomatis (D-63). */
export default function DialogJadwalTerbit({
    jadwalSaatIni,
    memproses,
    galat,
    saatTutup,
    saatSimpan,
}: PropsDialogJadwalTerbit) {
    const [nilai, AturNilai] = useState(UbahIsoKeWaktuLokal(jadwalSaatIni));
    const iso = UbahWaktuLokalKeIsoUtc(nilai);

    return (
        <DialogFormulir
            judul="Jadwalkan terbit"
            keterangan="Halaman diterbitkan otomatis pada waktu ini (waktu perangkat Anda). Yang tampil nanti adalah draf pada saat itu, jadi Anda masih bisa menyuntingnya."
            saatTutup={saatTutup}
        >
            <div className="flex flex-col gap-4">
                <PemilihTanggalWaktu
                    label="Waktu terbit"
                    nilai={nilai}
                    saatBerubah={AturNilai}
                    galat={galat}
                    jamBawaan="08:00"
                />
                <DialogFooter className="gap-2">
                    {jadwalSaatIni !== null ? (
                        <Tombol varian="sekunder" disabled={memproses} onClick={() => saatSimpan(null)}>
                            Batalkan jadwal
                        </Tombol>
                    ) : (
                        <Tombol varian="sekunder" onClick={saatTutup}>
                            Batal
                        </Tombol>
                    )}
                    <Tombol memproses={memproses} disabled={iso === null} onClick={() => saatSimpan(iso)}>
                        Simpan jadwal
                    </Tombol>
                </DialogFooter>
            </div>
        </DialogFormulir>
    );
}
