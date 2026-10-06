import { describe, expect, it } from 'vitest';

import { TerapkanFormat } from './FormatTeksKaya';

describe('Format teks kaya D-63', () => {
    it('tebal membungkus pilihan, menyisipkan contoh bila kosong, dan melepas bila sudah tebal', () => {
        expect(TerapkanFormat('halo dunia', 5, 10, 'tebal')).toEqual({ teks: 'halo **dunia**', mulai: 7, akhir: 12 });
        expect(TerapkanFormat('a ', 2, 2, 'tebal').teks).toBe('a **teks tebal**');
        expect(TerapkanFormat('**dunia**', 0, 9, 'tebal').teks).toBe('dunia');
    });

    it('butir & nomor bekerja per baris dan bergantian', () => {
        const awal = 'satu\ndua\n\ntiga';
        const butir = TerapkanFormat(awal, 0, awal.length, 'butir');
        expect(butir.teks).toBe('- satu\n- dua\n\n- tiga');
        expect(TerapkanFormat(butir.teks, butir.mulai, butir.akhir, 'butir').teks).toBe(awal);
        expect(TerapkanFormat(awal, 0, awal.length, 'nomor').teks).toBe('1. satu\n2. dua\n\n3. tiga');
    });

    it('subjudul mengganti awalan lain, hanya pada baris yang disentuh', () => {
        const hasil = TerapkanFormat('intro\n- butir\nakhir', 6, 6, 'subjudul');
        expect(hasil.teks).toBe('intro\n## butir\nakhir');
    });

    it('tautan membungkus pilihan dan menyorot alamat untuk diisi', () => {
        const hasil = TerapkanFormat('klik di sini', 5, 12, 'tautan');
        expect(hasil.teks).toBe('klik [di sini](https://)');
        expect(hasil.teks.slice(hasil.mulai, hasil.akhir)).toBe('https://');
    });
});
