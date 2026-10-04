import { Head, router } from '@inertiajs/react';
import {
    CameraIcon,
    CheckCircle2Icon,
    ClockIcon,
    LogInIcon,
    LogOutIcon,
    MapPinIcon,
    QrCodeIcon,
    ScanFaceIcon,
    ShieldCheckIcon,
    SunIcon,
    WifiIcon,
    WifiOffIcon,
} from 'lucide-react';
import { useCallback, useEffect, useId, useRef, useState } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Spinner } from '@/Komponen/Ui/spinner';
import JudulHalaman from '@/Komponen/Umpan/JudulHalaman';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { AmbilPendeteksiQr, CekKodeQrLengkap, NormalkanKodeQr } from '@/Fitur/Absensi/KodeQr';
import { AmbilPetunjukWajah, KeAkurasiMeter, KeTeksKoordinat } from '@/Fitur/Absensi/SidikWajah';
import { FormatDurasi, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { GalatPermintaan, KirimJson } from '@/Pustaka/PermintaanJson';
import { BuatUlid } from '@/Pustaka/Ulid';

export type PropsAbsensi = {
    NamaToko: string;
    NamaKaryawan: string;
    AlamatDasar: string;
    AlamatModelWajah: string;
    Wajah: { Status: 'Menunggu' | 'Disetujui' | 'Ditolak'; Label: string; AlasanTolak: string | null } | null;
    AbsensiTerbuka: { Uuid: string; MasukPada: string; NamaOutlet: string | null } | null;
    Riwayat: { MasukPada: string; KeluarPada: string | null; NamaOutlet: string | null }[];
    JumlahFotoDaftar: number;
    /** Outlet karyawan mewajibkan kode dari layar QR outlet (berganti tiap 30 detik). */
    WajibQr: boolean;
};

type HasilFoto = { Sidik: number[]; Swafoto: string };
type Lokasi = { Lintang: string; Bujur: string; AkurasiMeter: number };

/** Lama maksimal menunggu GPS mencapai akurasi yang baik sebelum memakai hasil terbaik yang ada. */
const BATAS_TUNGGU_GPS_MS = 15_000;
const AKURASI_CUKUP_METER = 25;

const formatJam = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' });
const formatJamDetik = new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    timeZone: 'Asia/Jakarta',
});
const formatHariPanjang = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
});
const formatHari = new Intl.DateTimeFormat('id-ID', { day: '2-digit', timeZone: 'Asia/Jakarta' });
const formatBulan = new Intl.DateTimeFormat('id-ID', { month: 'short', timeZone: 'Asia/Jakarta' });

/** "08.30" dari waktu ISO, zona WIB. */
function FormatJam(iso: string): string {
    return formatJam.format(new Date(iso));
}

function SalamWaktu(sekarang: Date): string {
    const jam = Number(
        new Intl.DateTimeFormat('en-GB', { hour: '2-digit', hour12: false, timeZone: 'Asia/Jakarta' }).format(sekarang),
    );

    if (jam < 11) {
        return 'Selamat pagi';
    }

    if (jam < 15) {
        return 'Selamat siang';
    }

    return jam < 18 ? 'Selamat sore' : 'Selamat malam';
}

/** Detik antara dua waktu ISO (atau sampai [sampai]); tidak pernah negatif. */
function SelisihDetik(dari: string, sampai: Date | string): number {
    return Math.max(0, Math.floor((new Date(sampai).getTime() - new Date(dari).getTime()) / 1000));
}

/**
 * F-18 bagian 4 (D-37): halaman absensi web karyawan di HP pribadi. Mobile-first, satu tugas per layar, bisa dipasang
 * ke layar utama (PWA; cakupan hanya halaman ini). Wajah didaftarkan sekali (persetujuan UU PDP, langsung aktif),
 * lalu tiap absen: lokasi GPS + pindai wajah dengan kedip → server menilai radius outlet & kecocokan wajah.
 *
 * Tampilan: kepala merek dengan jam hidup & salam, kartu aksi tumpang-tindih di bawahnya (tombol absen bulat besar,
 * lama bekerja berjalan), lalu riwayat sebagai garis waktu berdurasi.
 */
export default function HalamanAbsensi(props: PropsAbsensi) {
    const { NamaToko, NamaKaryawan, AlamatDasar, Wajah, Riwayat } = props;
    const jalur = new URL(AlamatDasar, window.location.origin).pathname;
    const daring = useStatusDaring();
    const sekarang = useSekarang();

    useEffect(() => {
        // Gagal mendaftarkan service worker tidak menghalangi absen; hanya fitur pasang & cache model yang hilang.
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(`${jalur}/pekerja-layanan`, { scope: jalur }).catch(() => undefined);
        }
    }, [jalur]);

    return (
        <div className="min-h-dvh bg-latar">
            <Head title={`Absen | ${NamaToko}`}>
                <link rel="manifest" href={`${jalur}/manifest`} />
                <meta name="mobile-web-app-capable" content="yes" />
                <meta name="apple-mobile-web-app-capable" content="yes" />
                <meta name="apple-mobile-web-app-title" content="Absen" />
                <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
            </Head>

            <header className="bg-brand-gelap px-4 pt-6 pb-20 text-permukaan">
                <div className="mx-auto flex w-full max-w-md flex-col gap-5">
                    <div className="flex items-center justify-between gap-3">
                        <p className="min-w-0 truncate text-label font-semibold text-brand-gelap-teks">{NamaToko}</p>
                        <span
                            className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-brand-gelap-sorot px-2.5 py-1 text-keterangan font-semibold text-permukaan"
                            role="status"
                        >
                            {daring ? (
                                <WifiIcon aria-hidden="true" className="size-3.5" />
                            ) : (
                                <WifiOffIcon aria-hidden="true" className="size-3.5" />
                            )}
                            {daring ? 'Tersambung' : 'Luring'}
                        </span>
                    </div>
                    <div className="flex flex-col gap-1">
                        <p className="text-isi text-brand-gelap-teks">{SalamWaktu(sekarang)},</p>
                        <JudulHalaman className="text-permukaan">{NamaKaryawan}</JudulHalaman>
                    </div>
                    <div className="flex items-end justify-between gap-3">
                        <p
                            className="text-sorotan-hp font-semibold tabular-nums text-permukaan"
                            aria-label="Jam sekarang"
                        >
                            {formatJamDetik.format(sekarang)}
                        </p>
                        <p className="pb-1 text-right text-keterangan text-brand-gelap-teks">
                            {formatHariPanjang.format(sekarang)}
                        </p>
                    </div>
                </div>
            </header>

            <main className="mx-auto -mt-12 flex w-full max-w-md flex-col gap-5 px-4 pb-8 tepi-bawah-aman">
                {!daring ? (
                    <Pemberitahuan jenis="peringatan">
                        <span className="inline-flex items-center gap-2">
                            <WifiOffIcon aria-hidden="true" className="size-4 shrink-0" />
                            Tidak ada koneksi internet. Absen butuh internet karena lokasi dan wajah diperiksa server.
                        </span>
                    </Pemberitahuan>
                ) : null}

                {Wajah?.Status === 'Disetujui' ? (
                    <PanelAbsen {...props} jalur={jalur} daring={daring} sekarang={sekarang} />
                ) : Wajah?.Status === 'Menunggu' ? (
                    <section className="flex flex-col items-center gap-3 rounded-panel border border-garis bg-permukaan p-6 text-center">
                        <span className="grid size-14 place-items-center rounded-full bg-info-lembut text-info">
                            <ClockIcon aria-hidden="true" className="size-7" />
                        </span>
                        <p className="text-isi text-teks-utama">
                            Wajah Anda sudah terdaftar dan menunggu persetujuan pengelola. Setelah disetujui, buka
                            lagi halaman ini untuk absen.
                        </p>
                    </section>
                ) : (
                    <PanelDaftarWajah {...props} jalur={jalur} daring={daring} />
                )}

                {Riwayat.length > 0 ? <GarisWaktuRiwayat riwayat={Riwayat} sekarang={sekarang} /> : null}
            </main>
        </div>
    );
}

function GarisWaktuRiwayat({ riwayat, sekarang }: { riwayat: PropsAbsensi['Riwayat']; sekarang: Date }) {
    return (
        <section className="flex flex-col gap-3" aria-labelledby="judul-riwayat">
            <h2 id="judul-riwayat" className="text-subjudul font-semibold text-teks-utama">
                Absen terakhir
            </h2>
            <ul className="flex flex-col gap-2">
                {riwayat.map((baris) => {
                    const selesai = baris.KeluarPada !== null;

                    return (
                        <li
                            key={baris.MasukPada}
                            className="flex items-center gap-3 rounded-panel border border-garis bg-permukaan p-3"
                        >
                            <span
                                className="flex w-12 shrink-0 flex-col items-center rounded-panel bg-brand-lembut py-1.5 text-brand"
                                aria-hidden="true"
                            >
                                <span className="text-judul leading-none font-semibold tabular-nums">
                                    {formatHari.format(new Date(baris.MasukPada))}
                                </span>
                                <span className="text-keterangan uppercase">
                                    {formatBulan.format(new Date(baris.MasukPada))}
                                </span>
                            </span>
                            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span className="text-isi font-semibold text-teks-utama tabular-nums">
                                    {FormatJam(baris.MasukPada)}
                                    {selesai ? ` – ${FormatJam(baris.KeluarPada as string)}` : ' – …'}
                                    <span className="sr-only"> ({FormatTanggalWaktu(baris.MasukPada)})</span>
                                </span>
                                <span className="truncate text-keterangan text-teks-sekunder">
                                    {baris.NamaOutlet ?? 'Outlet'}
                                </span>
                            </span>
                            <span className="flex shrink-0 flex-col items-end gap-1">
                                <span className="text-keterangan font-semibold text-teks-utama tabular-nums">
                                    {FormatDurasi(SelisihDetik(baris.MasukPada, baris.KeluarPada ?? sekarang))}
                                </span>
                                <span className="text-keterangan text-teks-sekunder">
                                    {selesai ? 'Selesai' : 'Belum absen keluar'}
                                </span>
                            </span>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

function PanelAbsen({
    AbsensiTerbuka,
    AlamatModelWajah,
    WajibQr,
    jalur,
    daring,
    sekarang,
}: PropsAbsensi & { jalur: string; daring: boolean; sekarang: Date }) {
    const [pindai, AturPindai] = useState(false);
    const [langkah, AturLangkah] = useState<string | null>(null);
    const [galat, AturGalat] = useState<string | null>(null);
    const [berhasil, AturBerhasil] = useState<string | null>(null);
    const lokasi = useRef<Promise<Lokasi> | null>(null);
    // Satu Uuid per absen masuk, dipakai ulang saat mencoba lagi (koneksi putus setelah server mencatat tidak
    // menghasilkan galat "sudah absen"), dan baru diganti setelah berhasil.
    const uuidMasuk = useRef<string | null>(null);
    const keluar = AbsensiTerbuka !== null;
    // Kolom kode QR tampil bila outlet utama mewajibkannya, atau setelah server meminta (outlet jadwal lain).
    const [perluQr, AturPerluQr] = useState(WajibQr);
    const [kodeQr, AturKodeQr] = useState('');
    const [pindaiQr, AturPindaiQr] = useState(false);
    const qrSiap = !perluQr || CekKodeQrLengkap(kodeQr);

    const Mulai = () => {
        AturGalat(null);
        AturBerhasil(null);
        // Lokasi dicari bersamaan dengan pindai wajah supaya karyawan tidak menunggu dua kali.
        lokasi.current = AmbilLokasi();
        lokasi.current.catch(() => undefined);
        AturPindai(true);
    };

    const Kirim = async ([foto]: HasilFoto[]) => {
        AturPindai(false);

        if (!foto || !lokasi.current) {
            return;
        }

        try {
            AturLangkah('Mencari lokasi…');
            const posisi = await lokasi.current;
            AturLangkah('Mengirim absen…');
            await KirimJson(`${jalur}/${keluar ? 'keluar' : 'masuk'}`, {
                Uuid: keluar ? AbsensiTerbuka.Uuid : (uuidMasuk.current ??= BuatUlid()),
                ...posisi,
                SidikWajah: foto.Sidik,
                Swafoto: foto.Swafoto,
                ...(CekKodeQrLengkap(kodeQr) ? { KodeQr: kodeQr } : {}),
            });
            uuidMasuk.current = null;
            AturKodeQr('');
            AturBerhasil(keluar ? 'Absen keluar tercatat. Terima kasih!' : 'Absen masuk tercatat. Selamat bekerja!');
            router.reload({ only: ['AbsensiTerbuka', 'Riwayat'] });
        } catch (kesalahan) {
            if (
                kesalahan instanceof GalatPermintaan &&
                (kesalahan.kode === 'KodeQrWajib' || kesalahan.kode === 'KodeQrSalah')
            ) {
                AturPerluQr(true);
                AturKodeQr('');
            }

            AturGalat(kesalahan instanceof Error ? kesalahan.message : 'Absen gagal. Coba lagi.');
        } finally {
            AturLangkah(null);
        }
    };

    const sibuk = langkah !== null;

    return (
        <section
            className="flex flex-col gap-5 rounded-panel border border-garis bg-permukaan p-5"
            aria-live="polite"
            aria-label="Absensi"
        >
            <div className="flex flex-col items-center gap-2 text-center">
                {AbsensiTerbuka ? (
                    <>
                        <LabelStatus jenis="sukses" teks="Sedang bekerja" />
                        <p className="text-sorotan-besar-hp font-semibold tabular-nums text-teks-utama">
                            {FormatDurasi(SelisihDetik(AbsensiTerbuka.MasukPada, sekarang))}
                        </p>
                        <p className="text-isi text-teks-sekunder">
                            Masuk pukul {FormatJam(AbsensiTerbuka.MasukPada)}
                            {AbsensiTerbuka.NamaOutlet ? ` di ${AbsensiTerbuka.NamaOutlet}` : ''}
                        </p>
                    </>
                ) : (
                    <>
                        <LabelStatus jenis="netral" teks="Belum absen masuk" />
                        <p className="inline-flex items-center gap-2 text-isi text-teks-sekunder">
                            <SunIcon aria-hidden="true" className="size-4" />
                            Siap mulai kerja hari ini?
                        </p>
                    </>
                )}
            </div>

            {berhasil ? (
                <div
                    className="flex animate-in items-center gap-3 rounded-panel bg-sukses-lembut p-3 text-sukses duration-300 zoom-in-95 fade-in"
                    role="status"
                >
                    <CheckCircle2Icon aria-hidden="true" className="size-6 shrink-0" />
                    <p className="text-isi font-semibold">{berhasil}</p>
                </div>
            ) : null}
            {galat ? <Pemberitahuan jenis="bahaya">{galat}</Pemberitahuan> : null}

            {perluQr && !pindai ? (
                pindaiQr ? (
                    <PemindaiQr
                        saatTerbaca={(kode) => {
                            AturKodeQr(kode);
                            AturPindaiQr(false);
                        }}
                        saatBatal={() => AturPindaiQr(false)}
                    />
                ) : (
                    <div className="flex flex-col gap-2 rounded-panel bg-permukaan-redup p-3">
                        <BidangTeks
                            label="Kode QR outlet"
                            nilai={kodeQr}
                            saatBerubah={(nilai) => AturKodeQr(NormalkanKodeQr(nilai))}
                            inputMode="numeric"
                            maxLength={6}
                            autoComplete="off"
                            keterangan="Pindai QR di layar outlet, atau ketik 6 angka di bawahnya. Kode berganti tiap 30 detik."
                            required
                        />
                        {AmbilPendeteksiQr() ? (
                            <Tombol varian="sekunder" onClick={() => AturPindaiQr(true)}>
                                <QrCodeIcon aria-hidden="true" className="size-4" />
                                Pindai QR outlet
                            </Tombol>
                        ) : null}
                    </div>
                )
            ) : null}

            {pindai ? (
                <PemindaiWajah
                    alamatModel={AlamatModelWajah}
                    jumlah={1}
                    saatSelesai={(hasil) => void Kirim(hasil)}
                    saatBatal={() => AturPindai(false)}
                    saatGalat={(pesan) => {
                        AturPindai(false);
                        AturGalat(pesan);
                    }}
                />
            ) : (
                <>
                    <button
                        type="button"
                        disabled={!daring || sibuk || !qrSiap || pindaiQr}
                        aria-busy={sibuk || undefined}
                        onClick={Mulai}
                        className={`mx-auto flex size-40 flex-col items-center justify-center gap-2 rounded-full text-brand-teks ring-8 transition-transform duration-150 select-none focus-visible:ring-offset-2 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100 ${
                            keluar ? 'bg-brand-gelap ring-brand-gelap-gulir' : 'bg-brand ring-brand-lembut-sorot'
                        }`}
                    >
                        {sibuk ? (
                            <Spinner role={undefined} aria-label={undefined} aria-hidden="true" className="size-8" />
                        ) : keluar ? (
                            <LogOutIcon aria-hidden="true" className="size-9" />
                        ) : (
                            <LogInIcon aria-hidden="true" className="size-9" />
                        )}
                        <span className="px-3 text-center text-subjudul font-semibold">
                            {langkah ?? (keluar ? 'Absen keluar' : 'Absen masuk')}
                        </span>
                    </button>

                    <ul className="grid grid-cols-3 gap-2 text-center text-keterangan text-teks-sekunder">
                        <li className="flex flex-col items-center gap-1 rounded-panel bg-permukaan-redup px-2 py-2.5">
                            {daring ? (
                                <WifiIcon aria-hidden="true" className="size-5 text-sukses" />
                            ) : (
                                <WifiOffIcon aria-hidden="true" className="size-5 text-bahaya" />
                            )}
                            {daring ? 'Internet siap' : 'Tanpa internet'}
                        </li>
                        <li className="flex flex-col items-center gap-1 rounded-panel bg-permukaan-redup px-2 py-2.5">
                            <MapPinIcon aria-hidden="true" className="size-5 text-brand" />
                            Dalam radius outlet
                        </li>
                        <li className="flex flex-col items-center gap-1 rounded-panel bg-permukaan-redup px-2 py-2.5">
                            <ScanFaceIcon aria-hidden="true" className="size-5 text-brand" />
                            Wajah dicek
                        </li>
                    </ul>
                    <p className="text-center text-keterangan text-teks-sekunder">
                        Izinkan lokasi dan kamera saat diminta. Absen hanya bisa dilakukan di dalam radius outlet.
                    </p>
                </>
            )}
        </section>
    );
}

function PanelDaftarWajah({
    Wajah,
    AlamatModelWajah,
    JumlahFotoDaftar,
    jalur,
    daring,
}: PropsAbsensi & { jalur: string; daring: boolean }) {
    const [setuju, AturSetuju] = useState(false);
    const [pindai, AturPindai] = useState(false);
    const [mengirim, AturMengirim] = useState(false);
    const [galat, AturGalat] = useState<string | null>(null);
    const idSetuju = useId();

    const Kirim = async (foto: HasilFoto[]) => {
        AturPindai(false);
        AturMengirim(true);
        AturGalat(null);

        try {
            await KirimJson(`${jalur}/wajah`, {
                SidikWajah: foto.map((f) => f.Sidik),
                Foto: foto.map((f) => f.Swafoto),
                Persetujuan: setuju,
            });
            router.reload();
        } catch (kesalahan) {
            AturGalat(kesalahan instanceof Error ? kesalahan.message : 'Pendaftaran wajah gagal. Coba lagi.');
        } finally {
            AturMengirim(false);
        }
    };

    const langkah = setuju ? 2 : 1;

    return (
        <section
            className="flex flex-col gap-5 rounded-panel border border-garis bg-permukaan p-5"
            aria-labelledby="judul-daftar-wajah"
        >
            <div className="flex items-center gap-3">
                <span className="grid size-12 shrink-0 place-items-center rounded-full bg-brand-lembut text-brand">
                    <ScanFaceIcon aria-hidden="true" className="size-6" />
                </span>
                <div className="flex min-w-0 flex-col gap-1">
                    <h2 id="judul-daftar-wajah" className="text-judul font-semibold text-teks-utama">
                        Daftarkan wajah
                    </h2>
                    {Wajah?.Status === 'Ditolak' ? <LabelStatus jenis="bahaya" teks="Ditolak, daftar ulang" /> : null}
                </div>
            </div>
            {Wajah?.AlasanTolak ? (
                <Pemberitahuan jenis="peringatan">Alasan pengelola: {Wajah.AlasanTolak}</Pemberitahuan>
            ) : null}
            <p className="text-isi text-teks-sekunder">
                Sekali saja sebelum absen pertama. Kamera depan akan mengambil {JumlahFotoDaftar} foto wajah Anda
                berturut-turut. Pengelola memeriksa fotonya sebelum Anda bisa absen.
            </p>

            <ol className="grid grid-cols-2 gap-2 text-keterangan" aria-label="Langkah pendaftaran">
                {['Setujui penggunaan data', 'Rekam wajah'].map((nama, indeks) => {
                    const nomor = indeks + 1;
                    const aktif = nomor === langkah;
                    const lewat = nomor < langkah;

                    return (
                        <li
                            key={nama}
                            aria-current={aktif ? 'step' : undefined}
                            className={`flex items-center gap-2 rounded-panel border p-2 ${
                                aktif ? 'border-brand bg-brand-lembut text-teks-utama' : 'border-garis text-teks-sekunder'
                            }`}
                        >
                            <span
                                className={`grid size-6 shrink-0 place-items-center rounded-full font-semibold ${
                                    lewat ? 'bg-sukses text-permukaan' : aktif ? 'bg-brand text-brand-teks' : 'bg-permukaan-sorot'
                                }`}
                            >
                                {lewat ? <CheckCircle2Icon aria-hidden="true" className="size-4" /> : nomor}
                            </span>
                            {nama}
                        </li>
                    );
                })}
            </ol>

            <ul className="flex flex-col gap-2 rounded-panel bg-permukaan-redup p-3 text-keterangan text-teks-sekunder">
                <li className="flex items-start gap-2">
                    <SunIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand" />
                    Cari tempat yang terang, wajah menghadap lurus ke kamera.
                </li>
                <li className="flex items-start gap-2">
                    <ScanFaceIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand" />
                    Lepas masker dan kacamata gelap, lalu kedip saat diminta.
                </li>
                <li className="flex items-start gap-2">
                    <ShieldCheckIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand" />
                    Data wajah disimpan terenkripsi dan hanya dipakai untuk absensi.
                </li>
            </ul>

            <label
                htmlFor={idSetuju}
                className="flex items-start gap-3 rounded-panel border border-garis p-3 text-keterangan text-teks-utama"
            >
                <input
                    id={idSetuju}
                    type="checkbox"
                    className="mt-0.5 size-5 shrink-0 accent-brand"
                    checked={setuju}
                    onChange={(peristiwa) => AturSetuju(peristiwa.target.checked)}
                />
                <span>
                    Saya setuju data wajah (foto dan sidik wajah) saya diproses toko ini{' '}
                    <strong>hanya untuk absensi</strong>, disimpan terenkripsi, dan dihapus saat saya tidak lagi bekerja
                    di sini (UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi).
                </span>
            </label>

            {galat ? <Pemberitahuan jenis="bahaya">{galat}</Pemberitahuan> : null}

            {pindai ? (
                <PemindaiWajah
                    alamatModel={AlamatModelWajah}
                    jumlah={JumlahFotoDaftar}
                    saatSelesai={(hasil) => void Kirim(hasil)}
                    saatBatal={() => AturPindai(false)}
                    saatGalat={(pesan) => {
                        AturPindai(false);
                        AturGalat(pesan);
                    }}
                />
            ) : (
                <Tombol
                    ukuran="besar"
                    disabled={!setuju || !daring}
                    memproses={mengirim}
                    onClick={() => AturPindai(true)}
                >
                    <CameraIcon aria-hidden="true" className="size-4" />
                    Mulai rekam wajah
                </Tombol>
            )}
        </section>
    );
}

/**
 * Kamera depan + pemindaian wajah berulang (±4 kali per detik) sampai [jumlah] foto lolos: satu wajah, wajah asli &
 * hidup, sudah berkedip. Antar-foto ada jeda singkat supaya fotonya tidak identik.
 */
function PemindaiWajah({
    alamatModel,
    jumlah,
    saatSelesai,
    saatBatal,
    saatGalat,
}: {
    alamatModel: string;
    jumlah: number;
    saatSelesai: (hasil: HasilFoto[]) => void;
    saatBatal: () => void;
    saatGalat: (pesan: string) => void;
}) {
    const video = useRef<HTMLVideoElement>(null);
    const [petunjuk, AturPetunjuk] = useState('Menyiapkan kamera & model wajah…');
    const [terkumpul, AturTerkumpul] = useState(0);
    const selesai = useRef(false);

    const Hentikan = useCallback(() => {
        selesai.current = true;
        const aliran = video.current?.srcObject;

        if (aliran instanceof MediaStream) {
            aliran.getTracks().forEach((trek) => trek.stop());
        }
    }, []);

    useEffect(() => {
        selesai.current = false;
        const hasil: HasilFoto[] = [];

        const Jalankan = async () => {
            const { MuatMesinWajah, PindaiWajah, AmbilSwafoto } = await import('@/Fitur/Absensi/MesinWajah');
            let aliran: MediaStream;

            try {
                aliran = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 } },
                    audio: false,
                });
            } catch {
                saatGalat(
                    'Kamera tidak bisa dibuka. Izinkan akses kamera untuk situs ini di pengaturan browser, lalu coba lagi.',
                );

                return;
            }

            if (!video.current || selesai.current) {
                aliran.getTracks().forEach((trek) => trek.stop());

                return;
            }

            video.current.srcObject = aliran;
            await video.current.play().catch(() => undefined);

            let human;

            try {
                human = await MuatMesinWajah(alamatModel);
            } catch {
                Hentikan();
                saatGalat('Model wajah gagal dimuat. Periksa koneksi internet, lalu coba lagi.');

                return;
            }

            let kedip = false;

            while (!selesai.current && video.current) {
                const pindai = await PindaiWajah(human, video.current, kedip);
                kedip = pindai.Kedip;
                AturPetunjuk(AmbilPetunjukWajah(pindai.Penilaian));

                if (pindai.Penilaian.Lolos && pindai.Sidik) {
                    hasil.push({ Sidik: pindai.Sidik, Swafoto: AmbilSwafoto(video.current) });
                    AturTerkumpul(hasil.length);

                    if (hasil.length >= jumlah) {
                        Hentikan();
                        saatSelesai(hasil);

                        return;
                    }

                    // Foto berikutnya: kedip lagi & jeda supaya posisi wajah sedikit berbeda.
                    kedip = false;
                    AturPetunjuk('Foto tersimpan. Gerakkan kepala sedikit, lalu kedip lagi.');
                    await new Promise((selesaiTunggu) => setTimeout(selesaiTunggu, 900));
                }

                await new Promise((selesaiTunggu) => setTimeout(selesaiTunggu, 250));
            }
        };

        void Jalankan();

        return Hentikan;
        // Pemindaian dimulai sekali per tampilan panel.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <div className="flex flex-col items-center gap-4">
            <div className="relative aspect-square w-full max-w-72 overflow-hidden rounded-full border-4 border-brand bg-permukaan-redup ring-8 ring-brand-lembut">
                <video
                    ref={video}
                    className="size-full -scale-x-100 object-cover"
                    playsInline
                    muted
                    aria-label="Pratinjau kamera depan"
                />
            </div>
            <p
                className="flex min-h-12 w-full items-center justify-center gap-2 rounded-panel bg-brand-lembut px-3 py-2 text-center text-isi font-semibold text-teks-utama"
                aria-live="assertive"
            >
                <ScanFaceIcon aria-hidden="true" className="size-5 shrink-0 text-brand" />
                {petunjuk}
            </p>
            {jumlah > 1 ? (
                <div className="flex flex-col items-center gap-2">
                    <div className="flex gap-2" aria-hidden="true">
                        {Array.from({ length: jumlah }, (_, indeks) => (
                            <span
                                key={indeks}
                                className={`h-2 w-10 rounded-full ${indeks < terkumpul ? 'bg-sukses' : 'bg-garis'}`}
                            />
                        ))}
                    </div>
                    <p className="text-keterangan text-teks-sekunder">
                        Foto {Math.min(terkumpul + 1, jumlah)} dari {jumlah}
                    </p>
                </div>
            ) : null}
            <Tombol
                varian="sekunder"
                onClick={() => {
                    Hentikan();
                    saatBatal();
                }}
            >
                Batal
            </Tombol>
        </div>
    );
}

/** Pindai QR layar outlet dengan kamera belakang lewat `BarcodeDetector` bawaan browser. */
function PemindaiQr({ saatTerbaca, saatBatal }: { saatTerbaca: (kode: string) => void; saatBatal: () => void }) {
    const video = useRef<HTMLVideoElement>(null);
    const [pesan, AturPesan] = useState('Arahkan kamera ke QR di layar outlet.');

    useEffect(() => {
        let berhenti = false;
        let aliran: MediaStream | null = null;
        const pendeteksi = AmbilPendeteksiQr();

        const Jalankan = async () => {
            if (!pendeteksi) {
                AturPesan('Browser ini tidak bisa memindai QR. Ketik 6 angka di layar outlet.');

                return;
            }

            try {
                aliran = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                    audio: false,
                });
            } catch {
                AturPesan('Kamera tidak bisa dibuka. Ketik 6 angka di layar outlet.');

                return;
            }

            if (!video.current || berhenti) {
                aliran.getTracks().forEach((trek) => trek.stop());

                return;
            }

            video.current.srcObject = aliran;
            await video.current.play().catch(() => undefined);

            while (!berhenti && video.current) {
                const hasil = await pendeteksi.detect(video.current).catch(() => []);
                const kode = hasil.map((h) => NormalkanKodeQr(h.rawValue)).find(CekKodeQrLengkap);

                if (kode) {
                    saatTerbaca(kode);

                    return;
                }

                await new Promise((selesaiTunggu) => setTimeout(selesaiTunggu, 300));
            }
        };

        void Jalankan();

        return () => {
            berhenti = true;
            aliran?.getTracks().forEach((trek) => trek.stop());
        };
        // Pemindaian dimulai sekali per tampilan.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <div className="flex flex-col items-center gap-3">
            <div className="relative aspect-square w-full max-w-72 overflow-hidden rounded-panel border-4 border-brand bg-permukaan-redup">
                <video
                    ref={video}
                    className="size-full object-cover"
                    playsInline
                    muted
                    aria-label="Pratinjau kamera belakang"
                />
            </div>
            <p className="text-center text-isi text-teks-utama" aria-live="assertive">
                {pesan}
            </p>
            <Tombol varian="sekunder" onClick={saatBatal}>
                Ketik kode saja
            </Tombol>
        </div>
    );
}

/** Lokasi GPS terbaik dalam [BATAS_TUNGGU_GPS_MS]; selesai lebih cepat begitu akurasinya cukup. */
function AmbilLokasi(): Promise<Lokasi> {
    return new Promise((berhasil, gagal) => {
        if (!('geolocation' in navigator)) {
            gagal(new Error('HP ini tidak mendukung lokasi. Pakai browser lain (Chrome atau Safari).'));

            return;
        }

        let terbaik: GeolocationPosition | null = null;
        const Selesai = () => {
            navigator.geolocation.clearWatch(pantau);
            clearTimeout(batas);

            if (terbaik) {
                berhasil({
                    Lintang: KeTeksKoordinat(terbaik.coords.latitude),
                    Bujur: KeTeksKoordinat(terbaik.coords.longitude),
                    AkurasiMeter: KeAkurasiMeter(terbaik.coords.accuracy),
                });
            } else {
                gagal(new Error('Lokasi tidak ditemukan. Nyalakan GPS, lalu coba lagi di dekat pintu atau jendela.'));
            }
        };
        const pantau = navigator.geolocation.watchPosition(
            (posisi) => {
                if (!terbaik || posisi.coords.accuracy < terbaik.coords.accuracy) {
                    terbaik = posisi;
                }

                if (posisi.coords.accuracy <= AKURASI_CUKUP_METER) {
                    Selesai();
                }
            },
            (kesalahan) => {
                if (kesalahan.code === kesalahan.PERMISSION_DENIED) {
                    navigator.geolocation.clearWatch(pantau);
                    clearTimeout(batas);
                    gagal(
                        new Error(
                            'Izin lokasi ditolak. Izinkan lokasi untuk situs ini di pengaturan browser, lalu coba lagi.',
                        ),
                    );
                }
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: BATAS_TUNGGU_GPS_MS },
        );
        const batas = setTimeout(Selesai, BATAS_TUNGGU_GPS_MS);
    });
}

function useStatusDaring(): boolean {
    const [daring, AturDaring] = useState(() => navigator.onLine);

    useEffect(() => {
        const Daring = () => AturDaring(true);
        const Luring = () => AturDaring(false);
        window.addEventListener('online', Daring);
        window.addEventListener('offline', Luring);

        return () => {
            window.removeEventListener('online', Daring);
            window.removeEventListener('offline', Luring);
        };
    }, []);

    return daring;
}

/** Waktu sekarang yang berdetak tiap detik (jam hidup & lama bekerja). */
function useSekarang(): Date {
    const [sekarang, AturSekarang] = useState(() => new Date());

    useEffect(() => {
        const penghitung = setInterval(() => AturSekarang(new Date()), 1000);

        return () => clearInterval(penghitung);
    }, []);

    return sekarang;
}
