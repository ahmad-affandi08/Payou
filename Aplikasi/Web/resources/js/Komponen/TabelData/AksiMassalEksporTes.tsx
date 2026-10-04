import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { AmbilHrefEkspor } from '@/Pengujian/InteraksiRadix';
import AksiMassalEkspor from '@/Komponen/TabelData/AksiMassalEkspor';

type Baris = { Uuid: string };

function Konteks(uuid: string[]) {
    return {
        terpilih: uuid.map((u) => ({ Uuid: u })),
        semuaHasil: false,
        total: uuid.length,
        keadaan: { cari: '', urut: [], halaman: 1, perHalaman: 25, saring: {} },
        bersihkan: vi.fn(),
    };
}

describe('AksiMassalEkspor', () => {
    afterEach(() => cleanup());

    it('menyebut jumlah terpilih dan mengunduh hanya Uuid terpilih', () => {
        render(
            <AksiMassalEkspor<Baris>
                konteks={Konteks(['01J9AAA0000000000000000001', '01J9AAA0000000000000000002'])}
                alamat="/kelola/penjualan/ekspor"
                ambilUuid={(b) => b.Uuid}
            />,
        );

        const csv = AmbilHrefEkspor('csv', /Ekspor 2 terpilih/);

        expect(csv).toContain('format=csv');
        expect(csv).toContain('uuid=01J9AAA0000000000000000001%2C01J9AAA0000000000000000002');
    });

    it('menonaktifkan tombol dan menjelaskan bila terpilih melebihi batas', () => {
        const banyak = Array.from({ length: 201 }, (_, i) => `01J9AAA${String(i).padStart(19, '0')}`);
        render(
            <AksiMassalEkspor<Baris>
                konteks={Konteks(banyak)}
                alamat="/kelola/penjualan/ekspor"
                ambilUuid={(b) => b.Uuid}
            />,
        );

        expect((screen.getByRole('button', { name: /Ekspor 201 terpilih/ }) as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByText(/Maksimal 200 baris/)).toBeTruthy();
    });
});
