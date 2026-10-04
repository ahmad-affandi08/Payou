/**
 * Bantuan interaksi test untuk komponen Radix di jsdom. Bukan kode produksi.
 * Pelengkap API peramban ada di `SiapkanLingkunganUji.ts` (dipasang otomatis lewat `test.setupFiles`).
 */
import { fireEvent, screen } from '@testing-library/react';

/** Buka DropdownMenu Radix: pemicunya bereaksi pada pointerdown tombol utama, bukan click. */
export function BukaMenu(pemicu: HTMLElement): void {
    fireEvent.pointerDown(pemicu, { button: 0, ctrlKey: false, pointerType: 'mouse' });
}

/** Pilih tab Radix: pemicunya bereaksi pada mousedown tombol utama. */
export function PilihTab(tab: HTMLElement): void {
    fireEvent.mouseDown(tab, { button: 0, ctrlKey: false });
}

/**
 * Alamat unduhan satu format dari `TombolEkspor` (D-43): membuka menunya lalu membaca `href` item format tersebut.
 * `label` = nama tombol (bawaan "Ekspor").
 */
export function AmbilHrefEkspor(format: 'xlsx' | 'csv' | 'cetak', label: string | RegExp = 'Ekspor'): string | null {
    const pemicu = screen.getByRole('button', { name: label });
    BukaMenu(pemicu);
    const nama = { xlsx: /Excel/, csv: /CSV/, cetak: /Cetak/ }[format];

    const href = screen.getByRole('menuitem', { name: nama }).getAttribute('href');
    // Tutup lagi: menu terbuka menyembunyikan sisa halaman dari kueri aksesibilitas.
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    return href;
}
