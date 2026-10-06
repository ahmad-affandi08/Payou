import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import GaleriBlok from './GaleriBlok';
import InspektorBlok from './InspektorBlok';
import type { BlokDraf } from './Tipe';

afterEach(cleanup);

const LABEL = { Hero: 'Hero', Harga: 'Paket & harga', FAQ: 'Tanya jawab' };

describe('Studio editor situs (D-73)', () => {
    it('galeri blok mencari berdasarkan nama dan menyisipkan lewat klik', () => {
        const SaatPilih = vi.fn();
        render(
            <GaleriBlok
                jenis={['Hero', 'Harga', 'FAQ']}
                label={LABEL}
                saatPilih={SaatPilih}
                keterangan="Blok baru masuk di bagian paling bawah halaman."
                bolehUbah
            />,
        );

        expect(screen.getByText('Blok baru masuk di bagian paling bawah halaman.')).toBeTruthy();
        fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'tanya' } });
        expect(screen.queryByText('Hero')).toBeNull();

        fireEvent.click(screen.getByText('Tanya jawab'));
        expect(SaatPilih).toHaveBeenCalledWith('FAQ');
    });

    it('galeri blok dinonaktifkan bagi yang tidak boleh mengubah', () => {
        const SaatPilih = vi.fn();
        render(<GaleriBlok jenis={['Hero']} label={LABEL} saatPilih={SaatPilih} keterangan="" bolehUbah={false} />);

        fireEvent.click(screen.getByText('Hero'));
        expect(SaatPilih).not.toHaveBeenCalled();
    });

    it('inspektor tanpa blok terpilih mengajak memilih dan membuka pengaturan halaman', () => {
        const SaatPengaturan = vi.fn();
        render(
            <InspektorBlok
                blok={null}
                indeks={-1}
                jumlah={0}
                skema={undefined}
                label=""
                ikon={[]}
                galat={{}}
                bolehUbah
                saatBerubah={vi.fn()}
                saatPindah={vi.fn()}
                saatGandakan={vi.fn()}
                saatHapus={vi.fn()}
                saatSisipkan={vi.fn()}
                saatPengaturanHalaman={SaatPengaturan}
            />,
        );

        expect(screen.getByText('Belum ada blok dipilih')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: /Pengaturan halaman/ }));
        expect(SaatPengaturan).toHaveBeenCalled();
    });

    it('inspektor blok terpilih menampilkan aksi blok dan menonaktifkan arah yang tidak mungkin', () => {
        const SaatPindah = vi.fn();
        const SaatHapus = vi.fn();
        const blok: BlokDraf = { _id: 'b1', Jenis: 'TeksBebas', Judul: 'Cerita kami', Isi: 'Halo' };
        render(
            <InspektorBlok
                blok={blok}
                indeks={0}
                jumlah={2}
                skema={{ Bidang: [] } as never}
                label="Teks bebas"
                ikon={[]}
                galat={{}}
                bolehUbah
                saatBerubah={vi.fn()}
                saatPindah={SaatPindah}
                saatGandakan={vi.fn()}
                saatHapus={SaatHapus}
                saatSisipkan={vi.fn()}
                saatPengaturanHalaman={vi.fn()}
            />,
        );

        expect(screen.getByText('Cerita kami')).toBeTruthy();
        expect((screen.getByRole('button', { name: 'Naikkan blok' }) as HTMLButtonElement).disabled).toBe(true);
        fireEvent.click(screen.getByRole('button', { name: 'Turunkan blok' }));
        expect(SaatPindah).toHaveBeenCalledWith(1);
        fireEvent.click(screen.getByRole('button', { name: 'Hapus blok' }));
        expect(SaatHapus).toHaveBeenCalled();
    });
});
