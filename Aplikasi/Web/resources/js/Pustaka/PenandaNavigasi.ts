import { router } from '@inertiajs/react';
import gambarIkon from '@/Aset/Merek/IkonMerek.webp';

/** Penanda muncul setelah jeda ini; kunjungan yang lebih cepat tidak menampilkan apa pun (D-58, D-62). */
export const JEDA_MUNCUL_MS = 150;
/** Begitu muncul, penanda tampil minimal selama ini supaya tidak berkedip. */
export const TAMPIL_MINIMAL_MS = 400;
/** Jaring pengaman: penanda dipaksa selesai bila sinyal selesai tidak pernah datang. */
export const BATAS_TAMPIL_MS = 20000;

/**
 * Tanda muat logo payung bercincin (sama dengan `TandaMuat`) di atas tirai gelap tipis yang menutupi seluruh layar saat pindah halaman Inertia;
 * menggantikan progress bawaan Inertia (`progress: false`) dan bilah garis (dibuang di D-62).
 * Mengembalikan fungsi pelepas. Aman dipanggil sekali per entry point.
 */
export function PasangPenandaNavigasi(): () => void {
    const bilah = document.createElement('div');
    bilah.className = 'penanda-navigasi';
    bilah.setAttribute('role', 'status');
    bilah.setAttribute('aria-label', 'Memuat');
    bilah.innerHTML = `<div class="penanda-navigasi__kotak"><div class="tanda-muat" style="--u:56px"><span class="tanda-muat__isi"><img src="${gambarIkon}" alt=""></span></div></div>`;
    document.body.appendChild(bilah);

    let aktif = 0;
    let penundaMuncul: number | undefined;
    let penundaSelesai: number | undefined;
    let mulaiTampil = 0;
    let penundaPaksa: number | undefined;

    const Mulai = () => {
        aktif += 1;
        if (aktif > 1) {
            return;
        }
        window.clearTimeout(penundaSelesai);
        window.clearTimeout(penundaMuncul);
        window.clearTimeout(penundaPaksa);
        bilah.removeAttribute('data-keadaan');
        penundaMuncul = window.setTimeout(() => {
            mulaiTampil = Date.now();
            bilah.dataset.keadaan = 'jalan';
            penundaPaksa = window.setTimeout(() => {
                aktif = 0;
                bilah.dataset.keadaan = 'selesai';
            }, BATAS_TAMPIL_MS);
        }, JEDA_MUNCUL_MS);
    };

    const Selesai = () => {
        aktif = Math.max(0, aktif - 1);
        if (aktif > 0) {
            return;
        }
        window.clearTimeout(penundaMuncul);
        window.clearTimeout(penundaPaksa);
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
        window.clearTimeout(penundaPaksa);
        bilah.remove();
    };
}
