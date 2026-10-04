import { usePage } from '@inertiajs/react';

import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

/**
 * Audit kemudahan pakai #33 (D-38, D-48): fitur khusus sektor tampil bila salah satu outlet (atau jenis usaha tambahan)
 * memakai sektor berawalan itu. `sektor` kosong = fitur umum; daftar sektor outlet kosong = sektor belum diketahui,
 * semua tampil. Awalan cocok per segmen: `SVC` mencakup `SVC-WRK`, `SVC-WRK` hanya Bengkel.
 */
export function CekSesuaiSektor(menu: { sektor?: string[] | undefined }, sektorOutlet: string[]): boolean {
    if (menu.sektor === undefined || sektorOutlet.length === 0) {
        return true;
    }

    return menu.sektor.some((awalan) => sektorOutlet.some((kode) => kode === awalan || kode.startsWith(`${awalan}-`)));
}

/** Hook: apakah tenant ini punya usaha bersektor salah satu awalan di `sektor`. */
export function PakaiSektor(sektor: string[]): boolean {
    const { props } = usePage<PropsBersamaAplikasi>();

    return CekSesuaiSektor({ sektor }, props.SektorOutlet ?? []);
}
