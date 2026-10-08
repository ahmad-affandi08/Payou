import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import { BarisSubAkun } from '@/Halaman/Kelola/Pembayaran/Gerbang';

/*
 * Tahap 3 DOKU: baris baca-saja status sub account DOKU toko di Pembayaran › Gerbang. Hanya status; tidak ada ID,
 * pesan galat, atau tombol.
 */

afterEach(() => cleanup());

describe('BarisSubAkun (back-office tenant)', () => {
    it('belum dibuat: label "Belum dibuat" tanpa tombol', () => {
        render(<BarisSubAkun subAkun={null} />);

        expect(screen.getByText('Sub account DOKU Anda:')).toBeTruthy();
        expect(screen.getByText('Belum dibuat')).toBeTruthy();
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('prop belum dikirim server (undefined) diperlakukan sebagai belum dibuat', () => {
        render(<BarisSubAkun subAkun={undefined} />);

        expect(screen.getByText('Belum dibuat')).toBeTruthy();
    });

    it.each([
        ['Aktif', 'Aktif'],
        ['Menunggu', 'Menunggu'],
    ] as const)('status %s tampil sebagai label', (status, label) => {
        render(<BarisSubAkun subAkun={{ Status: status, LabelStatus: label }} />);

        expect(screen.getByText(label)).toBeTruthy();
        expect(screen.queryByText('Belum dibuat')).toBeNull();
        expect(screen.queryByRole('button')).toBeNull();
    });
});
