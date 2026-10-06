import { router, usePage } from '@inertiajs/react';

import KartuFormulir from '@/Komponen/Formulir/KartuFormulir';
import { AlamatKaryawan, IsiFormulirKaryawan } from '@/Komponen/Karyawan/FormulirKaryawan';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsBuatKaryawan } from '@/Tipe/Karyawan';

const isianForm = [
    'Nama',
    'Jabatan',
    'LevelStaf',
    'GajiPokok',
    'TarifLemburPerJam',
    'PotonganTerlambatPerMenit',
    'PotonganTidakMasukPerHari',
    'UuidPengguna',
    'UuidOutlet',
];

/** F-18 EMP-01 halaman "Tambah karyawan". Setelah disimpan, server mengarahkan kembali ke daftar karyawan. */
export default function HalamanBuatKaryawan({ OpsiPengguna, OpsiOutlet }: PropsBuatKaryawan) {
    const { props } = usePage<PropsBersamaAplikasi>();

    return (
        <TataLetakAplikasi judul="Tambah karyawan">
            <DaftarGalatServer galat={props.errors} kecuali={isianForm} />
            <KartuFormulir keterangan="Karyawan dijadwalkan per outlet dan absen masuk/keluar di aplikasi kasir dengan PIN akunnya. Karyawan tanpa akun tetap bisa dijadwalkan, tetapi tidak bisa absen di POS.">
                <IsiFormulirKaryawan
                    karyawan={null}
                    opsiPengguna={OpsiPengguna}
                    opsiOutlet={OpsiOutlet}
                    saatBatal={() => router.visit(AlamatKaryawan)}
                />
            </KartuFormulir>
        </TataLetakAplikasi>
    );
}
