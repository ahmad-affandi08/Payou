import './Gaya/Situs.css';

import { createInertiaApp } from '@inertiajs/react';
import { StrictMode, type ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

/*
 * Entry situs pemasaran (D-21, payoung.id). Bundle ringan terpisah: hanya halaman di Halaman/Situs/ (tanpa TanStack
 * Query & kode back-office). Judul & meta SEO awal diisi server di view `Situs.blade.php`.
 */
const KUNCI_MUAT_ULANG = 'payoung.situs.muat-ulang';

/**
 * Halaman bergantung pada puluhan modul kecil (Vite memuat semuanya sebelum halaman pertama muncul). Bila satu
 * saja gagal diambil (sinyal HP putus sesaat, atau berkas lama sudah diganti setelah rilis baru), tanpa penanganan
 * halaman berhenti di kerangka HTML dari server selamanya. Muat ulang **sekali** per sesi; bila masih gagal,
 * kerangka server (judul, pengantar, tombol, dan gambar pertama) tetap terbaca dan bisa diklik.
 */
window.addEventListener('vite:preloadError', (p) => {
    try {
        if (window.sessionStorage.getItem(KUNCI_MUAT_ULANG) !== '1') {
            window.sessionStorage.setItem(KUNCI_MUAT_ULANG, '1');
            p.preventDefault();
            window.location.reload();
        }
    } catch {
        // Penyimpanan diblokir (mode privat): biarkan kerangka server yang tampil.
    }
});

const daftarHalaman = import.meta.glob<{ default: ComponentType }>('./Halaman/Situs/**/*.tsx');

async function MuatHalaman(nama: string): Promise<ComponentType> {
    const MuatModul = daftarHalaman[`./Halaman/${nama}.tsx`];

    if (!MuatModul || !nama.startsWith('Situs/')) {
        throw new Error(`Halaman situs tidak ditemukan: ${nama}`);
    }

    return (await MuatModul()).default;
}

void createInertiaApp({
    title: (judul) => judul,
    resolve: MuatHalaman,
    setup({ el, App, props }) {
        try {
            window.sessionStorage.removeItem(KUNCI_MUAT_ULANG);
        } catch {
            // Penyimpanan diblokir; tidak memengaruhi tampilan.
        }

        createRoot(el).render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
    progress: { color: 'var(--color-brand)' },
});
