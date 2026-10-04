import { router, usePage } from '@inertiajs/react';
import { MapPinIcon, MonitorIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import Panel from '@/Komponen/Kelola/Panel';
import DialogKonfirmasi from '@/Komponen/Tindakan/DialogKonfirmasi';
import { Button } from '@/Komponen/Ui/button';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';

export type LokasiAbsensiOutletData = {
    Lintang: string | null;
    Bujur: string | null;
    RadiusMeter: number;
    /** Tautan layar QR absensi (tablet/monitor outlet); null = belum dibuat. */
    TautanLayar?: string | null;
    WajibQr?: boolean;
};

type Props = { alamatOutlet: string; data: LokasiAbsensiOutletData; bolehKelola: boolean };

/**
 * F-18 bagian 4 (D-37): titik lokasi & radius absensi dari HP. Karyawan hanya bisa absen web bila berada dalam radius
 * titik ini. Titik bisa diambil dari lokasi perangkat pengelola saat berdiri di outlet ("Pakai lokasi saya sekarang")
 * atau disalin dari Google Maps. Layar QR berganti (tautan untuk tablet/monitor outlet) menjadi bukti hadir kedua dan
 * bisa diwajibkan.
 */
export default function LokasiAbsensiOutlet({ alamatOutlet, data, bolehKelola }: Props) {
    const { props } = usePage<{ errors: Record<string, string> }>();
    const [lintang, AturLintang] = useState(data.Lintang ?? '');
    const [bujur, AturBujur] = useState(data.Bujur ?? '');
    const [radius, AturRadius] = useState(String(data.RadiusMeter));
    const [memproses, AturMemproses] = useState(false);
    const [mencari, AturMencari] = useState(false);
    const [galatLokasi, AturGalatLokasi] = useState<string | null>(null);
    const ada = data.Lintang !== null && data.Bujur !== null;

    const Kirim = (isi: { Lintang: string | null; Bujur: string | null }) => {
        AturMemproses(true);
        router.post(
            `${alamatOutlet}/lokasi-absensi`,
            { ...isi, RadiusAbsensiMeter: Number(radius) },
            { preserveScroll: true, onFinish: () => AturMemproses(false) },
        );
    };

    const Simpan = (e: FormEvent) => {
        e.preventDefault();
        Kirim({ Lintang: lintang.trim() || null, Bujur: bujur.trim() || null });
    };

    const PakaiLokasiSaya = () => {
        AturGalatLokasi(null);

        if (!('geolocation' in navigator)) {
            AturGalatLokasi('Peramban ini tidak mendukung lokasi. Salin titik dari Google Maps.');

            return;
        }

        AturMencari(true);
        navigator.geolocation.getCurrentPosition(
            (posisi) => {
                AturMencari(false);
                AturLintang(posisi.coords.latitude.toFixed(7));
                AturBujur(posisi.coords.longitude.toFixed(7));
            },
            () => {
                AturMencari(false);
                AturGalatLokasi(
                    'Lokasi tidak didapat. Izinkan lokasi untuk situs ini, atau salin titik dari Google Maps.',
                );
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    };

    return (
        <Panel
            judul="Lokasi absensi dari HP"
            keterangan="Karyawan hanya bisa absen dari HP pribadi bila berada dalam radius titik ini."
        >
            {ada ? (
                <p className="flex flex-wrap items-center gap-2 text-isi text-teks-utama">
                    <MapPinIcon aria-hidden="true" className="size-4 shrink-0 text-teks-sekunder" />
                    <span className="font-mono tabular-nums">
                        {data.Lintang}, {data.Bujur}
                    </span>
                    <span>| radius {data.RadiusMeter} m |</span>
                    <a
                        className="text-brand underline underline-offset-2"
                        href={`https://www.google.com/maps?q=${data.Lintang ?? ''},${data.Bujur ?? ''}`}
                        target="_blank"
                        rel="noreferrer"
                    >
                        Lihat di peta
                    </a>
                </p>
            ) : (
                <p className="text-isi text-teks-sekunder">
                    Belum ada titik lokasi. Karyawan belum bisa absen dari HP di outlet ini.
                </p>
            )}
            {bolehKelola ? (
                <form noValidate className="grid gap-3 sm:grid-cols-3" onSubmit={Simpan}>
                    <BidangTeks
                        label="Lintang"
                        nilai={lintang}
                        saatBerubah={AturLintang}
                        keterangan="Misal -7.5560000"
                        inputMode="decimal"
                        galat={props.errors.Lintang}
                    />
                    <BidangTeks
                        label="Bujur"
                        nilai={bujur}
                        saatBerubah={AturBujur}
                        keterangan="Misal 110.8310000"
                        inputMode="decimal"
                        galat={props.errors.Bujur}
                    />
                    <BidangTeks
                        label="Radius (meter)"
                        nilai={radius}
                        saatBerubah={AturRadius}
                        keterangan="20–1.000 m. Bawaan 100 m."
                        inputMode="numeric"
                        galat={props.errors.RadiusAbsensiMeter}
                        required
                    />
                    {galatLokasi ? (
                        <div className="sm:col-span-3">
                            <Pemberitahuan jenis="peringatan">{galatLokasi}</Pemberitahuan>
                        </div>
                    ) : null}
                    <div className="flex flex-wrap gap-2 sm:col-span-3">
                        <Tombol type="submit" memproses={memproses}>
                            Simpan lokasi absensi
                        </Tombol>
                        <Tombol varian="sekunder" memproses={mencari} onClick={PakaiLokasiSaya}>
                            Pakai lokasi saya sekarang
                        </Tombol>
                        {ada ? (
                            <Tombol
                                varian="bahaya"
                                disabled={memproses}
                                onClick={() => Kirim({ Lintang: null, Bujur: null })}
                            >
                                Hapus titik lokasi
                            </Tombol>
                        ) : null}
                    </div>
                </form>
            ) : null}
            <LayarQrAbsensi alamatOutlet={alamatOutlet} data={data} bolehKelola={bolehKelola} />
        </Panel>
    );
}

function LayarQrAbsensi({ alamatOutlet, data, bolehKelola }: Props) {
    const [memproses, AturMemproses] = useState<string | null>(null);
    const [konfirmasi, AturKonfirmasi] = useState<'buat-ulang' | 'cabut' | null>(null);
    const [tersalin, AturTersalin] = useState(false);
    const tautan = data.TautanLayar ?? null;
    const BuatOpsi = (aksi: string) => {
        AturMemproses(aksi);

        return {
            preserveScroll: true,
            onSuccess: () => AturKonfirmasi(null),
            onFinish: () => AturMemproses(null),
        };
    };

    const Salin = async (teks: string) => {
        try {
            await navigator.clipboard.writeText(teks);
            AturTersalin(true);
        } catch {
            AturTersalin(false);
        }
    };

    return (
        <section className="flex flex-col gap-3 border-t border-garis pt-4" aria-labelledby="judul-layar-qr-absensi">
            <h3
                id="judul-layar-qr-absensi"
                className="flex items-center gap-2 text-label font-semibold text-teks-utama"
            >
                <MonitorIcon aria-hidden="true" className="size-4 text-teks-sekunder" />
                Layar QR absensi
            </h3>
            <p className="text-keterangan text-teks-sekunder">
                Buka tautan ini di tablet atau monitor yang terpasang di outlet. Layar menampilkan QR dan 6 angka yang
                berganti tiap 30 detik; karyawan memindainya saat absen dari HP sebagai bukti benar-benar di outlet.
            </p>
            {tautan ? (
                <>
                    <p className="break-all rounded-panel border border-garis bg-permukaan-redup px-3 py-2 font-mono text-keterangan text-teks-utama">
                        {tautan}
                    </p>
                    <p className="text-keterangan text-teks-sekunder">
                        Rahasiakan: siapa pun yang membuka tautan ini bisa melihat kode yang berlaku.
                    </p>
                </>
            ) : (
                <p className="text-isi text-teks-sekunder">Belum ada layar QR untuk outlet ini.</p>
            )}
            {bolehKelola ? (
                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap gap-2">
                        {tautan ? (
                            <>
                                <Tombol varian="sekunder" onClick={() => void Salin(tautan)}>
                                    {tersalin ? 'Tersalin' : 'Salin tautan layar'}
                                </Tombol>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-8 px-4 text-label font-semibold pointer-coarse:h-11"
                                >
                                    <a href={tautan} target="_blank" rel="noreferrer">
                                        Buka layar
                                    </a>
                                </Button>
                                <Tombol
                                    varian="sekunder"
                                    disabled={memproses !== null}
                                    onClick={() => AturKonfirmasi('buat-ulang')}
                                >
                                    Buat ulang tautan layar
                                </Tombol>
                                <Tombol
                                    varian="bahaya"
                                    disabled={memproses !== null}
                                    onClick={() => AturKonfirmasi('cabut')}
                                >
                                    Cabut layar
                                </Tombol>
                            </>
                        ) : (
                            <Tombol
                                memproses={memproses === 'buat'}
                                onClick={() => router.post(`${alamatOutlet}/layar-absensi`, {}, BuatOpsi('buat'))}
                            >
                                Buat tautan layar QR
                            </Tombol>
                        )}
                    </div>
                    {tautan ? (
                        <KotakCentang
                            label="Wajibkan pindai QR untuk absen dari HP di outlet ini"
                            nilai={data.WajibQr === true}
                            saatBerubah={(wajib) =>
                                router.post(`${alamatOutlet}/wajib-qr-absensi`, { Wajib: wajib }, BuatOpsi('wajib'))
                            }
                        />
                    ) : null}
                </div>
            ) : null}
            {konfirmasi ? (
                <DialogKonfirmasi
                    judul={konfirmasi === 'cabut' ? 'Cabut layar QR absensi?' : 'Buat ulang tautan layar?'}
                    labelAksi={konfirmasi === 'cabut' ? 'Cabut layar' : 'Buat ulang tautan layar'}
                    memproses={memproses === konfirmasi}
                    saatBatal={() => AturKonfirmasi(null)}
                    saatKonfirmasi={() =>
                        konfirmasi === 'cabut'
                            ? router.delete(`${alamatOutlet}/layar-absensi`, BuatOpsi('cabut'))
                            : router.post(`${alamatOutlet}/layar-absensi`, {}, BuatOpsi('buat-ulang'))
                    }
                >
                    <p>
                        {konfirmasi === 'cabut'
                            ? 'Layar di outlet berhenti menampilkan kode, dan absen dari HP di outlet ini tidak lagi meminta QR.'
                            : 'Layar lama berhenti menampilkan kode. Buka tautan baru di tablet atau monitor outlet.'}
                    </p>
                </DialogKonfirmasi>
            ) : null}
        </section>
    );
}
