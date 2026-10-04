import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

/*
 * Penjaga luapan kisi di HP. File penjaga: kalau test ini gagal, perbaiki CSS-nya, bukan test-nya.
 *
 * Regresi yang dijaga: `grid` tanpa `grid-cols-*` berkolom `auto`, sehingga satu bidang dengan teks panjang (pilihan
 * lokasi stok) melebarkan kisi dan halaman melewati lebar layar 360px. Aturan dasar `.grid` harus berkolom
 * `minmax(0, 1fr)` dan berada di `@layer base` supaya utilitas `grid-cols-*` tetap menang.
 */
describe('Gaya/Aplikasi.css kisi', () => {
    const css = readFileSync('resources/js/Gaya/Aplikasi.css', 'utf-8');

    it('memberi kolom tunggal minmax(0, 1fr) pada .grid di layer base', () => {
        const base = css.slice(css.indexOf('@layer base'));

        expect(base).toMatch(/\.grid\s*\{\s*grid-template-columns:\s*minmax\(0,\s*1fr\);/);
    });
});
