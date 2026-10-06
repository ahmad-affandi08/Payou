import { describe, expect, it } from 'vitest';

import { AmbilDaftarIsi, BuatIdJudul } from './DaftarIsiTeks';

describe('DaftarIsiTeks (D-72)', () => {
    it('membuat id jangkar dari judul', () => {
        expect(BuatIdJudul('2.1 Pendaftaran')).toBe('2-1-pendaftaran');
        expect(BuatIdJudul('Hukum yang berlaku & sengketa')).toBe('hukum-yang-berlaku-sengketa');
        expect(BuatIdJudul('!!!')).toBe('bagian');
    });

    it('mengambil judul tingkat 1 dan 2 yang berdiri sendiri, mengabaikan paragraf dan daftar', () => {
        const teks = [
            'Pembuka dokumen.',
            '# 1. Layanan',
            'Isi pasal.\n- butir satu\n- butir dua',
            '## 1.1 Rincian',
            '### Anak judul',
            '# 2. Akun',
        ].join('\n\n');

        expect(AmbilDaftarIsi(teks)).toEqual([
            { Id: '1-layanan', Judul: '1. Layanan', Tingkat: 1 },
            { Id: '1-1-rincian', Judul: '1.1 Rincian', Tingkat: 2 },
            { Id: '2-akun', Judul: '2. Akun', Tingkat: 1 },
        ]);
    });

    it('judul yang menempel dengan paragraf tidak masuk daftar isi', () => {
        expect(AmbilDaftarIsi('# Judul\nIsi tanpa baris kosong')).toEqual([]);
    });
});
