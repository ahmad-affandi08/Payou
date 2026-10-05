import { router } from '@inertiajs/react';

/** Bilah muncul setelah jeda ini; kunjungan yang lebih cepat tidak menampilkan apa pun (D-58). */
export const JEDA_MUNCUL_MS = 150;
/** Begitu muncul, bilah tampil minimal selama ini supaya tidak berkedip. */
export const TAMPIL_MINIMAL_MS = 400;

/**
 * Bilah tipis di tepi atas saat pindah halaman Inertia; menggantikan progress bawaan Inertia (`progress: false`).
 * Mengembalikan fungsi pelepas. Aman dipanggil sekali per entry point.
 */
export function PasangBilahNavigasi(): () => void {
    const bilah = document.createElement('div');
    bilah.className = 'bilah-navigasi';
    bilah.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bilah);

    let aktif = 0;
    let penundaMuncul: number | undefined;
    let penundaSelesai: number | undefined;
    let mulaiTampil = 0;

    const Mulai = () => {
        aktif += 1;
        if (aktif > 1) {
            return;
        }
        window.clearTimeout(penundaSelesai);
        window.clearTimeout(penundaMuncul);
        bilah.removeAttribute('data-keadaan');
        penundaMuncul = window.setTimeout(() => {
            mulaiTampil = Date.now();
            bilah.dataset.keadaan = 'jalan';
        }, JEDA_MUNCUL_MS);
    };

    const Selesai = () => {
        aktif = Math.max(0, aktif - 1);
        if (aktif > 0) {
            return;
        }
        window.clearTimeout(penundaMuncul);
        if (bilah.dataset.keadaan !== 'jalan') {
            return;
        }
        const sisa = Math.max(0, TAMPIL_MINIMAL_MS - (Date.now() - mulaiTampil));
        penundaSelesai = window.setTimeout(() => {
            bilah.dataset.keadaan = 'selesai';
        }, sisa);
    };

    // Prefetch berjalan diam-diam di latar; tidak boleh memunculkan bilah.
    const LepasMulai = router.on('start', (peristiwa) => {
        if (!peristiwa.detail.visit.prefetch) {
            Mulai();
        }
    });
    const LepasSelesai = router.on('finish', (peristiwa) => {
        if (!peristiwa.detail.visit.prefetch) {
            Selesai();
        }
    });

    return () => {
        LepasMulai();
        LepasSelesai();
        window.clearTimeout(penundaMuncul);
        window.clearTimeout(penundaSelesai);
        bilah.remove();
    };
}
