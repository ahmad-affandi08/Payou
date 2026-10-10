import { useEffect, useRef, useState, type RefObject } from 'react';

/** Perangkat sentuh (HP/tablet): keyboard layar naik saat kotak teks difokuskan. */
export function LayarSentuh(): boolean {
    return typeof window !== 'undefined' && window.matchMedia?.('(pointer: coarse)').matches === true;
}

/**
 * Popover pilihan hanya `modal` bila pemicunya berada di dalam Dialog/Sheet/Popover lain: kunci gulir milik
 * pembungkus itu akan memblokir sentuhan di isi popover yang di-portal ke body. Di halaman biasa popover
 * dibiarkan non-modal supaya halaman tetap bisa digulir saat daftar terbuka (di iOS, mode modal mengunci
 * gulir halaman dan daftar yang tergeser keyboard tidak bisa dijangkau lagi).
 */
export function PemicuDalamPembungkus(pemicu: HTMLElement | null): boolean {
    return pemicu?.closest('[role="dialog"], [role="alertdialog"], [data-slot="popover-content"]') != null;
}

/**
 * Perilaku bersama popover ber-kotak-cari (`PilihanCari`, `PemilihProduk`) di perangkat sentuh:
 *
 * 1. Kotak cari TIDAK otomatis fokus saat popover dibuka. Di iOS/Android, fokus otomatis langsung memunculkan
 *    keyboard, menggeser halaman ke atas, dan menutupi daftarnya (bug "dropdown ketutup, tidak bisa digulir").
 *    Keyboard baru naik ketika pengguna mengetuk kotak cari sendiri. Di desktop fokus otomatis tetap ada.
 * 2. Tinggi popover mengikuti area yang benar-benar terlihat (`visualViewport`, yang menyusut saat keyboard naik),
 *    bukan tinggi layar penuh — daftar menyusut dan menggulir di atas keyboard, tidak tertimpa.
 */
export function usePerilakuPopoverCari(pemicu: RefObject<HTMLElement | null>, terbuka: boolean) {
    const [modal, AturModal] = useState(false);
    const isi = useRef<HTMLDivElement | null>(null);

    /** Panggil sebelum popover dibuka: menentukan `modal` dari tempat pemicu berada. */
    const SiapkanBuka = () => AturModal(PemicuDalamPembungkus(pemicu.current));

    const SaatBukaFokus = (peristiwa: Event) => {
        // Fokus ke kotak cari, bukan ke item pertama — kecuali di layar sentuh (lihat butir 1).
        peristiwa.preventDefault();
        const elemen = peristiwa.currentTarget as HTMLElement;

        if (LayarSentuh()) {
            elemen.focus({ preventScroll: true });

            return;
        }

        elemen.querySelector('input')?.focus({ preventScroll: true });
    };

    useEffect(() => {
        const tampak = typeof window === 'undefined' ? null : window.visualViewport;

        if (!terbuka || tampak == null || !LayarSentuh()) {
            return;
        }

        let terukur: HTMLElement | null = null;
        const Ukur = () => {
            const elemen = isi.current;

            if (elemen == null) {
                return;
            }

            terukur = elemen;

            // Sisa area terlihat di bawah tepi atas popover (koordinat viewport tata letak).
            const sisa = tampak.offsetTop + tampak.height - elemen.getBoundingClientRect().top - 8;
            elemen.style.setProperty('--tinggi-tampak', `${Math.max(160, Math.floor(sisa))}px`);
        };

        // Isi popover baru terpasang setelah Portal selesai; ukur ulang di frame berikutnya.
        const bingkai = window.requestAnimationFrame(Ukur);
        tampak.addEventListener('resize', Ukur);
        tampak.addEventListener('scroll', Ukur);

        return () => {
            window.cancelAnimationFrame(bingkai);
            tampak.removeEventListener('resize', Ukur);
            tampak.removeEventListener('scroll', Ukur);
            terukur?.style.removeProperty('--tinggi-tampak');
        };
    }, [terbuka]);

    return { modal, isi, SiapkanBuka, SaatBukaFokus };
}

/** Batas tinggi isi popover: ruang tersedia menurut Radix, dipotong lagi oleh area terlihat saat keyboard naik. */
export const GayaTinggiPopoverCari = {
    maxHeight: 'min(var(--radix-popover-content-available-height), var(--tinggi-tampak, 100vh))',
} as const;
