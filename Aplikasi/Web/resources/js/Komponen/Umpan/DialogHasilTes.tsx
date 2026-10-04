import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import DialogHasil, { LAMA_DIALOG_BERHASIL_MS } from './DialogHasil';

describe('DialogHasil', () => {
    beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }));
    afterEach(() => vi.useRealTimers());

    it('tidak menampilkan apa pun tanpa pesan', () => {
        render(<DialogHasil berhasil={null} gagal={undefined} penanda={{}} />);

        expect(screen.queryByRole('alertdialog')).toBeNull();
    });

    it('menampilkan pesan berhasil lalu menutup sendiri', () => {
        render(<DialogHasil berhasil="Produk Kopi disimpan." gagal={undefined} penanda={{}} />);

        const dialog = screen.getByRole('alertdialog');
        expect(dialog.textContent).toContain('Berhasil');
        expect(dialog.textContent).toContain('Produk Kopi disimpan.');
        expect(screen.getByRole('button', { name: 'Oke' })).toBeTruthy();

        act(() => {
            vi.advanceTimersByTime(LAMA_DIALOG_BERHASIL_MS + 10);
        });

        expect(screen.queryByRole('alertdialog')).toBeNull();
    });

    it('pesan gagal menunggu tombol Tutup dan menang atas pesan berhasil', () => {
        render(<DialogHasil berhasil="Disimpan." gagal="Stok tidak cukup." penanda={{}} />);

        const dialog = screen.getByRole('alertdialog');
        expect(dialog.textContent).toContain('Belum berhasil');
        expect(dialog.textContent).toContain('Stok tidak cukup.');

        act(() => {
            vi.advanceTimersByTime(LAMA_DIALOG_BERHASIL_MS * 3);
        });
        expect(screen.getByRole('alertdialog')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Tutup' }));
        expect(screen.queryByRole('alertdialog')).toBeNull();
    });

    it('pesan yang sama muncul lagi bila penandanya berganti', () => {
        const { rerender: RenderUlang } = render(<DialogHasil berhasil="Disimpan." gagal={undefined} penanda={{}} />);

        act(() => {
            vi.advanceTimersByTime(LAMA_DIALOG_BERHASIL_MS + 10);
        });
        expect(screen.queryByRole('alertdialog')).toBeNull();

        RenderUlang(<DialogHasil berhasil="Disimpan." gagal={undefined} penanda={{}} />);

        expect(screen.getByRole('alertdialog')).toBeTruthy();
    });
});
