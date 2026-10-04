import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import DialogMasukPembeli, { type ProfilPembeli } from '@/Komponen/TokoOnline/DialogMasukPembeli';
import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { KirimJson } from '@/Pustaka/PermintaanJson';

type PesananRiwayat = {
    Nomor: string;
    Outlet: string | null;
    JenisPemenuhan: string;
    Status: string;
    LabelStatus: string;
    Total: string;
    DibuatPada: string | null;
    UrlStatus: string;
};

type BelanjaRiwayat = {
    Nomor: string;
    Outlet: string | null;
    Tanggal: string;
    Status: string;
    LabelStatus: string;
    Total: string;
    UrlStruk: string;
};

type Props = {
    Slug: string;
    Toko: { Nama: string } | null;
    AkunAktif: boolean;
    Pelanggan: ProfilPembeli | null;
    Riwayat: { Pesanan: PesananRiwayat[]; Belanja: BelanjaRiwayat[] } | null;
};

const statusGagal = ['Ditolak', 'Dibatalkan', 'Kedaluwarsa', 'Void', 'Diretur'];
const statusSelesai = ['Selesai', 'Lunas'];

function AmbilJenisStatus(status: string): 'sukses' | 'bahaya' | 'netral' {
    if (statusGagal.includes(status)) return 'bahaya';
    if (statusSelesai.includes(status)) return 'sukses';
    return 'netral';
}

/**
 * F-17 bagian 3: "Akun saya" pembeli toko online. Belum masuk → ajakan masuk dengan WhatsApp. Sudah masuk → profil,
 * poin & tier, riwayat pesanan online dan belanja di kasir (satu pelanggan, satu riwayat), ubah profil, keluar.
 */
export default function AkunTokoOnline({ Slug, Toko, AkunAktif, Pelanggan, Riwayat }: Props) {
    const [dialogMasuk, AturDialogMasuk] = useState(false);
    const [profil, AturProfil] = useState(() => ({
        Nama: Pelanggan?.Nama ?? '',
        Email: Pelanggan?.Email ?? '',
        TanggalLahir: Pelanggan?.TanggalLahir ?? '',
        SetujuPemasaran: Pelanggan?.SetujuPemasaran ?? false,
    }));
    const [pesan, AturPesan] = useState<{ jenis: 'sukses' | 'bahaya'; teks: string } | null>(null);
    const [memproses, AturMemproses] = useState<'profil' | 'keluar' | null>(null);

    function MuatUlang() {
        router.reload();
    }

    async function SimpanProfil(e: FormEvent) {
        e.preventDefault();
        AturMemproses('profil');
        AturPesan(null);
        try {
            await KirimJson(
                `/${Slug}/akun/profil`,
                { ...profil, Email: profil.Email || null, TanggalLahir: profil.TanggalLahir || null },
                'PUT',
            );
            AturPesan({ jenis: 'sukses', teks: 'Profil tersimpan.' });
            MuatUlang();
        } catch (galat: unknown) {
            AturPesan({ jenis: 'bahaya', teks: galat instanceof Error ? galat.message : 'Profil belum tersimpan.' });
        } finally {
            AturMemproses(null);
        }
    }

    async function Keluar(semua: boolean) {
        AturMemproses('keluar');
        try {
            await KirimJson(`/${Slug}/akun/keluar`, { Semua: semua });
            window.location.assign(`/${Slug}`);
        } catch (galat: unknown) {
            AturPesan({ jenis: 'bahaya', teks: galat instanceof Error ? galat.message : 'Belum berhasil keluar.' });
            AturMemproses(null);
        }
    }

    return (
        <main className="min-h-screen bg-latar text-teks-utama">
            <Head title={Toko ? `Akun saya | ${Toko.Nama}` : 'Akun saya'} />
            <header className="border-b border-garis bg-permukaan">
                <div className="mx-auto flex max-w-3xl flex-col gap-2 px-4 py-5">
                    <a href={`/${Slug}`} className="text-label text-brand underline">
                        Kembali belanja di {Toko?.Nama ?? 'toko'}
                    </a>
                    <JudulHalaman>Akun saya</JudulHalaman>
                </div>
            </header>
            <div className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-6">
                {Toko === null ? (
                    <Pemberitahuan jenis="peringatan">Toko tidak ditemukan.</Pemberitahuan>
                ) : Pelanggan === null ? (
                    <section className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-4">
                        <h2 className="text-subjudul font-semibold text-teks-utama">Masuk ke {Toko.Nama}</h2>
                        {AkunAktif ? (
                            <>
                                <p className="text-isi text-teks-sekunder">
                                    Masuk dengan nomor WhatsApp untuk melihat riwayat belanja dan poin Anda. Tidak perlu
                                    kata sandi.
                                </p>
                                <div>
                                    <Tombol onClick={() => AturDialogMasuk(true)}>Masuk dengan WhatsApp</Tombol>
                                </div>
                            </>
                        ) : (
                            <p className="text-isi text-teks-sekunder">
                                Toko ini belum membuka akun pembeli. Anda tetap bisa memesan tanpa masuk.
                            </p>
                        )}
                    </section>
                ) : (
                    <>
                        <section className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4">
                            <h2 className="text-subjudul font-semibold text-teks-utama">{Pelanggan.Nama}</h2>
                            <dl className="grid gap-2 text-isi sm:grid-cols-3">
                                <div>
                                    <dt className="text-keterangan text-teks-sekunder">WhatsApp</dt>
                                    <dd className="tabular-nums">{Pelanggan.NoHp}</dd>
                                </div>
                                <div>
                                    <dt className="text-keterangan text-teks-sekunder">Tingkat member</dt>
                                    <dd>{Pelanggan.Tier ?? 'Belum ada'}</dd>
                                </div>
                                {Pelanggan.Poin !== null ? (
                                    <div>
                                        <dt className="text-keterangan text-teks-sekunder">Poin</dt>
                                        <dd className="tabular-nums">{Pelanggan.Poin.toLocaleString('id-ID')} poin</dd>
                                    </div>
                                ) : null}
                            </dl>
                            <p className="text-keterangan text-teks-sekunder">
                                Poin bertambah saat pesanan dibayar di toko. Nomor WhatsApp adalah identitas akun Anda
                                dan tidak bisa diganti di sini.
                            </p>
                        </section>

                        <section
                            aria-labelledby="judul-pesanan"
                            className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4"
                        >
                            <h2 id="judul-pesanan" className="text-subjudul font-semibold text-teks-utama">
                                Pesanan online
                            </h2>
                            {(Riwayat?.Pesanan.length ?? 0) === 0 ? (
                                <p className="text-isi text-teks-sekunder">Belum ada pesanan online.</p>
                            ) : (
                                <ul className="divide-y divide-garis">
                                    {Riwayat?.Pesanan.map((p) => (
                                        <li
                                            key={p.Nomor}
                                            className="flex flex-wrap items-center justify-between gap-2 py-3"
                                        >
                                            <div className="flex min-w-0 flex-col">
                                                <a
                                                    href={p.UrlStatus}
                                                    className="font-mono text-isi text-brand underline"
                                                >
                                                    {p.Nomor}
                                                </a>
                                                <span className="text-keterangan text-teks-sekunder">
                                                    {FormatTanggalWaktu(p.DibuatPada)} | {p.JenisPemenuhan}
                                                    {p.Outlet ? ` | ${p.Outlet}` : ''}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <LabelStatus jenis={AmbilJenisStatus(p.Status)} teks={p.LabelStatus} />
                                                <span className="tabular-nums">{FormatRupiah(p.Total)}</span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section
                            aria-labelledby="judul-belanja"
                            className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4"
                        >
                            <h2 id="judul-belanja" className="text-subjudul font-semibold text-teks-utama">
                                Belanja di toko
                            </h2>
                            {(Riwayat?.Belanja.length ?? 0) === 0 ? (
                                <p className="text-isi text-teks-sekunder">Belum ada belanja tercatat.</p>
                            ) : (
                                <ul className="divide-y divide-garis">
                                    {Riwayat?.Belanja.map((b) => (
                                        <li
                                            key={b.Nomor}
                                            className="flex flex-wrap items-center justify-between gap-2 py-3"
                                        >
                                            <div className="flex min-w-0 flex-col">
                                                <a
                                                    href={b.UrlStruk}
                                                    className="font-mono text-isi text-brand underline"
                                                >
                                                    {b.Nomor}
                                                </a>
                                                <span className="text-keterangan text-teks-sekunder">
                                                    {FormatTanggal(b.Tanggal)}
                                                    {b.Outlet ? ` | ${b.Outlet}` : ''}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <LabelStatus jenis={AmbilJenisStatus(b.Status)} teks={b.LabelStatus} />
                                                <span className="tabular-nums">{FormatRupiah(b.Total)}</span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4">
                            <h2 className="text-subjudul font-semibold text-teks-utama">Profil</h2>
                            <form noValidate onSubmit={SimpanProfil} className="flex flex-col gap-4">
                                <BidangTeks
                                    label="Nama"
                                    nilai={profil.Nama}
                                    saatBerubah={(v) => AturProfil((x) => ({ ...x, Nama: v }))}
                                    autoComplete="name"
                                    required
                                />
                                <BidangTeks
                                    label="Email (opsional)"
                                    nilai={profil.Email}
                                    saatBerubah={(v) => AturProfil((x) => ({ ...x, Email: v }))}
                                    jenis="email"
                                    autoComplete="email"
                                />
                                <PemilihTanggal
                                    label="Tanggal lahir (opsional)"
                                    nilai={profil.TanggalLahir}
                                    saatBerubah={(v) => AturProfil((x) => ({ ...x, TanggalLahir: v }))}
                                    disabled={Pelanggan.TanggalLahir !== null}
                                    keterangan={
                                        Pelanggan.TanggalLahir !== null
                                            ? 'Sudah tersimpan. Untuk mengubahnya, hubungi toko.'
                                            : 'Hanya bisa diisi sekali. Dipakai untuk promo ulang tahun bila toko punya.'
                                    }
                                />
                                <KotakCentang
                                    label="Kirimi saya info promo lewat WhatsApp."
                                    nilai={profil.SetujuPemasaran}
                                    saatBerubah={(v) => AturProfil((x) => ({ ...x, SetujuPemasaran: v }))}
                                />
                                {pesan ? <Pemberitahuan jenis={pesan.jenis}>{pesan.teks}</Pemberitahuan> : null}
                                <div>
                                    <Tombol type="submit" memproses={memproses === 'profil'}>
                                        Simpan profil
                                    </Tombol>
                                </div>
                            </form>
                        </section>

                        <section className="flex flex-wrap gap-3">
                            <Tombol
                                varian="sekunder"
                                memproses={memproses === 'keluar'}
                                onClick={() => void Keluar(false)}
                            >
                                Keluar
                            </Tombol>
                            <Tombol varian="sekunder" disabled={memproses !== null} onClick={() => void Keluar(true)}>
                                Keluar dari semua perangkat
                            </Tombol>
                        </section>
                    </>
                )}
            </div>
            {dialogMasuk && Toko ? (
                <DialogMasukPembeli
                    slug={Slug}
                    namaToko={Toko.Nama}
                    saatMasuk={() => {
                        AturDialogMasuk(false);
                        MuatUlang();
                    }}
                    saatTutup={() => AturDialogMasuk(false)}
                />
            ) : null}
        </main>
    );
}
