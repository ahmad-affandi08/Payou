import { Link, usePage } from '@inertiajs/react';

import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

/**
 * Pemberitahuan batas outlet tercapai. Edisi SaaS menautkan ke menu Langganan (naik paket/add-on); edisi Lisensi
 * (D-35) tidak punya langganan, jadi batasnya hanya bisa dinaikkan dengan berkas lisensi baru dari penjual.
 */
export default function PemberitahuanBatasOutlet() {
    const { props } = usePage<PropsBersamaAplikasi>();

    if (props.Edisi === 'Lisensi') {
        return (
            <Pemberitahuan jenis="info" judul="Batas outlet lisensi sudah tercapai">
                Hubungi penjual lisensi Payoung untuk menambah outlet. Outlet yang diarsipkan tidak dihitung.
            </Pemberitahuan>
        );
    }

    return (
        <Pemberitahuan jenis="info" judul="Batas outlet paket sudah tercapai">
            Tingkatkan paket atau tambah add-on outlet di{' '}
            <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                menu Langganan
            </Link>
            . Outlet yang diarsipkan tidak dihitung.
        </Pemberitahuan>
    );
}
