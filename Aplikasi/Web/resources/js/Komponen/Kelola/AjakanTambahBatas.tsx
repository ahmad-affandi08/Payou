import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

/**
 * Bagian kalimat yang menautkan ke halaman milik Payoung SaaS (menu Langganan, tiket Bantuan, dokumentasi API publik).
 * Edisi Lisensi (D-35) tidak punya halaman itu, jadi `children` diganti `teksLisensi`: batas hanya bisa dinaikkan
 * dengan berkas lisensi baru dari penjual. `teksLisensi` null = bagian kalimatnya dihilangkan.
 */
export default function AjakanTambahBatas({
    children,
    teksLisensi = 'minta berkas lisensi dengan batas lebih besar ke penjual lisensi Payoung',
}: {
    children: ReactNode;
    teksLisensi?: string | null;
}) {
    const { props } = usePage<PropsBersamaAplikasi>();

    if (props.Edisi === 'Lisensi') {
        return teksLisensi;
    }

    return children;
}
