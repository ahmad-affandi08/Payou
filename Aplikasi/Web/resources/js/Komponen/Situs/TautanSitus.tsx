import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** Jalur yang bukan halaman situs (dilayani bundle/host lain): pakai tautan biasa, bukan kunjungan Inertia. */
const JALUR_BUKAN_SITUS = [
    'masuk',
    'daftar',
    'kelola',
    'legal',
    's',
    'api',
    'kompatibilitas-perangkat',
    'gambar-situs',
    'peta-situs',
    'undangan',
    'lupa-kata-sandi',
];

/** Tautan internal ke halaman situs lain (bisa navigasi Inertia tanpa muat ulang). */
export function CekTautanHalamanSitus(tautan: string): boolean {
    if (!tautan.startsWith('/') || tautan.startsWith('//')) {
        return false;
    }

    const segmen = tautan.slice(1).split(/[/?#]/)[0] ?? '';

    return !JALUR_BUKAN_SITUS.includes(segmen);
}

type PropsTautanSitus = {
    href: string;
    children: ReactNode;
    className?: string;
    'aria-label'?: string;
};

/**
 * Tautan situs pemasaran (D-21): halaman situs lewat Inertia `Link`; jalur sistem, domain tenant, dan tautan luar lewat
 * `<a>` biasa. Tautan https ke situs lain (WhatsApp, media sosial, toko aplikasi) dibuka di tab baru.
 */
export default function TautanSitus({ href, children, className, ...lainnya }: PropsTautanSitus) {
    if (CekTautanHalamanSitus(href)) {
        return (
            <Link href={href} className={className} {...lainnya}>
                {children}
            </Link>
        );
    }

    const luar = /^https?:\/\//.test(href) && typeof window !== 'undefined' && !href.startsWith(window.location.origin);
    const bukanSitus = luar && !/^https?:\/\/[^/]*payoung\./.test(href);

    return (
        <a
            href={href}
            className={className}
            {...(bukanSitus ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
            {...lainnya}
        >
            {children}
        </a>
    );
}
