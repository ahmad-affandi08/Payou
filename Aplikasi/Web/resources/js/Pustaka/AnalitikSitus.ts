/**
 * Analitik situs pemasaran (bagian B): Google Analytics 4 & Meta Pixel dimuat **hanya** setelah pengunjung menyetujui
 * cookie analitik (UU PDP). Pilihan disimpan di peramban; ID divalidasi ulang agar tidak menyisipkan skrip lain.
 */

export type PengaturanAnalitik = { IdGoogleAnalytics: string | null; IdMetaPixel: string | null };

export type PilihanCookie = 'terima' | 'tolak';

export const KUNCI_PERSETUJUAN = 'payoung.persetujuan-cookie';

const POLA_GA = /^G-[A-Z0-9]{4,16}$/i;
const POLA_PIXEL = /^[0-9]{6,20}$/;

type JendelaAnalitik = Window & {
    dataLayer?: unknown[];
    gtag?: (...argumen: unknown[]) => void;
    fbq?: ((...argumen: unknown[]) => void) & { queue?: unknown[]; loaded?: boolean; version?: string };
    _fbq?: unknown;
};

export function AdaAnalitik(a: PengaturanAnalitik | undefined): a is PengaturanAnalitik {
    return Boolean(
        a &&
        ((a.IdGoogleAnalytics && POLA_GA.test(a.IdGoogleAnalytics)) ||
            (a.IdMetaPixel && POLA_PIXEL.test(a.IdMetaPixel))),
    );
}

export function AmbilPilihanCookie(): PilihanCookie | null {
    try {
        const nilai = window.localStorage.getItem(KUNCI_PERSETUJUAN);

        return nilai === 'terima' || nilai === 'tolak' ? nilai : null;
    } catch {
        return null;
    }
}

export function SimpanPilihanCookie(pilihan: PilihanCookie): void {
    try {
        window.localStorage.setItem(KUNCI_PERSETUJUAN, pilihan);
    } catch {
        // Penyimpanan diblokir: pilihan berlaku untuk kunjungan ini saja.
    }
}

/** Audit F-22: peristiwa untuk membuka ulang bilah persetujuan dari tautan "Pengaturan cookie" di kaki situs. */
export const PERISTIWA_ATUR_COOKIE = 'payoung:atur-cookie';

export function BukaPengaturanCookie(): void {
    window.dispatchEvent(new Event(PERISTIWA_ATUR_COOKIE));
}

/**
 * Hapus cookie analitik pihak pertama (GA4 `_ga*`, `_gid`, `_gat*`; Meta `_fbp`, `_fbc`) di domain ini dan domain
 * induknya setelah pengunjung menarik persetujuan.
 */
export function HapusCookieAnalitik(): void {
    const nama = document.cookie
        .split(';')
        .map((c) => c.split('=')[0]?.trim() ?? '')
        .filter((n) => /^(_ga|_gid|_gat|_fbp|_fbc)/.test(n));
    const bagian = window.location.hostname.split('.');
    const domain = bagian.map((_, i) => bagian.slice(i).join('.')).filter((d) => d.includes('.'));

    for (const n of nama) {
        document.cookie = `${n}=; Max-Age=0; path=/`;
        for (const d of domain) {
            document.cookie = `${n}=; Max-Age=0; path=/; domain=.${d}`;
        }
    }
}

function PasangSkrip(src: string): void {
    if (document.querySelector(`script[src="${src}"]`)) {
        return;
    }

    const skrip = document.createElement('script');
    skrip.async = true;
    skrip.src = src;
    document.head.appendChild(skrip);
}

/** Muat GA4 & Meta Pixel (sekali). Dipanggil hanya setelah pengunjung menerima cookie. */
export function MuatAnalitik(a: PengaturanAnalitik): void {
    const w = window as JendelaAnalitik;

    if (a.IdGoogleAnalytics && POLA_GA.test(a.IdGoogleAnalytics) && !w.gtag) {
        w.dataLayer = w.dataLayer ?? [];
        w.gtag = function () {
            // eslint-disable-next-line prefer-rest-params
            w.dataLayer?.push(arguments);
        };
        w.gtag('js', new Date());
        w.gtag('config', a.IdGoogleAnalytics, { anonymize_ip: true });
        PasangSkrip(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(a.IdGoogleAnalytics)}`);
    }

    if (a.IdMetaPixel && POLA_PIXEL.test(a.IdMetaPixel) && !w.fbq) {
        const antrean: unknown[] = [];
        const Pixel = Object.assign(
            (...argumen: unknown[]) => {
                antrean.push(argumen);
            },
            { queue: antrean, loaded: true, version: '2.0' },
        );
        w.fbq = Pixel;
        w._fbq = Pixel;
        Pixel('init', a.IdMetaPixel);
        Pixel('track', 'PageView');
        PasangSkrip('https://connect.facebook.net/en_US/fbevents.js');
    }
}

/** Catat tampilan halaman setelah navigasi Inertia (hanya bila analitik sudah dimuat). */
export function CatatTampilanHalaman(a: PengaturanAnalitik, jalur: string): void {
    const w = window as JendelaAnalitik;

    if (w.gtag && a.IdGoogleAnalytics) {
        w.gtag('event', 'page_view', { page_path: jalur });
    }

    if (w.fbq) {
        w.fbq('track', 'PageView');
    }
}

/** Catat prospek terkirim sebagai konversi (tanpa data pribadi). */
export function CatatProspek(jenis: string): void {
    const w = window as JendelaAnalitik;
    w.gtag?.('event', 'generate_lead', { jenis });
    w.fbq?.('track', 'Lead');
}
