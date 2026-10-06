import './Gaya/Situs.css';

import { createInertiaApp } from '@inertiajs/react';
import { StrictMode, type ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

/*
 * Entry situs pemasaran (D-21, payoung.id). Bundle ringan terpisah: hanya halaman di Halaman/Situs/ (tanpa TanStack
 * Query & kode back-office). Judul & meta SEO awal diisi server di view `Situs.blade.php`.
 */
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
        createRoot(el).render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
    progress: { color: 'var(--color-brand)' },
});
