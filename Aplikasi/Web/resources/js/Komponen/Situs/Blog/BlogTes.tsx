import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import HalamanArtikel from '@/Halaman/Situs/Artikel';
import HalamanBlog from '@/Halaman/Situs/Blog';
import type { DataSitus, RingkasanArtikel } from '@/Tipe/Situs';

let props: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...sisa }: { href: string; children: ReactNode }) => (
        <a href={href} {...sisa}>
            {children}
        </a>
    ),
    router: { on: () => () => undefined },
    usePage: () => ({ props, url: '/blog' }),
}));

const situs: DataSitus = {
    NamaSitus: 'PAYOU',
    Slogan: null,
    Logo: null,
    Menu: [],
    MenuKaki: [],
    TeksKaki: null,
    Kontak: { WhatsApp: null, TautanWhatsApp: null, Email: null, Telepon: null, Alamat: null, JamLayanan: null },
    MediaSosial: {},
    Pengumuman: null,
    TautanUnduh: {},
    TombolDaftar: { Label: 'Coba gratis', Tautan: '/daftar' },
    TombolMasuk: { Label: 'Masuk', Tautan: '/masuk' },
    WhatsAppMelayang: false,
    Tahun: 2026,
};

function BuatArtikel(slug: string, tambahan: Partial<RingkasanArtikel> = {}): RingkasanArtikel {
    return {
        Slug: slug,
        Judul: `Judul ${slug}`,
        Ringkasan: 'Ringkasan singkat.',
        Kategori: 'Tips kasir',
        NamaPenulis: null,
        Sampul: null,
        DiterbitkanPada: '2026-09-20T03:00:00+00:00',
        ...tambahan,
    };
}

afterEach(cleanup);

describe('Situs bagian B2: blog', () => {
    it('daftar artikel menaut ke /blog/{slug}, chip kategori, dan navigasi halaman', () => {
        props = {
            Situs: situs,
            Halaman: { Seo: { Judul: 'Blog | PAYOU', Deskripsi: 'Tips usaha.' } },
            Artikel: [BuatArtikel('tips-satu'), BuatArtikel('tips-dua')],
            Kategori: ['Stok', 'Tips kasir'],
            KategoriAktif: 'Tips kasir',
            HalamanKe: 1,
            JumlahHalaman: 2,
        };
        render(<HalamanBlog />);

        expect(screen.getByRole('link', { name: 'Judul tips-satu' }).getAttribute('href')).toBe('/blog/tips-satu');
        expect(screen.getByRole('link', { name: 'Stok' }).getAttribute('href')).toBe('/blog?kategori=Stok');
        expect(screen.getByRole('link', { name: 'Berikutnya' }).getAttribute('href')).toBe(
            '/blog?kategori=Tips+kasir&halaman=2',
        );
        expect(screen.getByText('Halaman 1 dari 2')).toBeTruthy();
    });

    it('blog kosong menampilkan keadaan kosong', () => {
        props = {
            Situs: situs,
            Halaman: { Seo: { Judul: 'Blog', Deskripsi: '' } },
            Artikel: [],
            Kategori: [],
            KategoriAktif: null,
            HalamanKe: 1,
            JumlahHalaman: 1,
        };
        render(<HalamanBlog />);
        expect(screen.getByText(/Belum ada artikel/)).toBeTruthy();
    });

    it('artikel: judul h1, isi berformat aman, jejak kategori, dan artikel terkait', () => {
        props = {
            Situs: situs,
            Halaman: { Seo: { Judul: 'Tips satu | PAYOU', Deskripsi: '' } },
            Artikel: { ...BuatArtikel('tips-satu'), Isi: '## Langkah\n\n<script>x</script>', DiubahPada: null },
            Terkait: [BuatArtikel('tips-dua')],
        };
        render(<HalamanArtikel />);

        expect(screen.getByRole('heading', { level: 1, name: 'Judul tips-satu' })).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Langkah' })).toBeTruthy();
        expect(screen.getByText('<script>x</script>')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Tips kasir' }).getAttribute('href')).toBe(
            '/blog?kategori=Tips%20kasir',
        );
        expect(screen.getByRole('link', { name: 'Judul tips-dua' })).toBeTruthy();
    });
});
