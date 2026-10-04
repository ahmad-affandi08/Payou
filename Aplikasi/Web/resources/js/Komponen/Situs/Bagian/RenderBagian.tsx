import type { BagianSitus } from '@/Tipe/Situs';

import BagianFormulirProspek from './BagianFormulirProspek';
import BagianHarga from './BagianHarga';
import BagianHero from './BagianHero';
import { BagianKeunggulan, BagianLogoMitra, BagianSektor, BagianStatistik, BagianTestimoni } from './BagianKartu';
import {
    BagianCta,
    BagianFaq,
    BagianGambarTeks,
    BagianKontak,
    BagianTeksBebas,
    BagianUnduhAplikasi,
    BagianVideo,
} from './BagianLain';
import type { LatarBagian } from './KepalaBagian';

/** Blok yang mengatur latarnya sendiri dan tidak ikut irama terang/gelap. */
const LATAR_SENDIRI = new Set<BagianSitus['Jenis']>(['Hero', 'Statistik', 'Cta']);

/** Setelah sebanyak ini bagian terang berturut-turut, satu bagian dibuat gelap sebagai jeda baca. */
const JEDA_GELAP_SETIAP = 4;

/**
 * Blok yang tidak pantas dibalik menjadi gelap (isi panjang atau berisi formulir/kartu putih). D-39: FAQ ikut,
 * karena hampir selalu tepat di atas CTA berlatar merek; FAQ Navy + CTA biru membuat dua balok gelap menempel.
 */
const SELALU_TERANG = new Set<BagianSitus['Jenis']>(['Harga', 'TeksBebas', 'FormulirProspek', 'Video', 'Faq']);

export type IramaBagian = { latar: LatarBagian; garisAtas: boolean };

/** Latar yang dirender sendiri oleh blok, dipakai agar bagian sesudahnya tahu harus mulai terang atau gelap. */
function LatarSendiri(bagian: BagianSitus): LatarBagian {
    if (bagian.Jenis === 'Hero') {
        return bagian.Latar === 'Navy' ? 'navy' : bagian.Latar === 'Merek' ? 'merek' : 'permukaan';
    }

    return 'merek';
}

/**
 * Irama latar tiap blok (D-25).
 *
 * Sebelumnya bagian hanya berselang-seling token `Latar`/`Permukaan`, padahal kedua nada itu hanya beda
 * beberapa persen sehingga batas bagian praktis tidak terlihat dan halaman melebur. Sekarang:
 *
 * 1. bagian terang tetap berselang-seling, dan **setiap bagian terang yang mengikuti bagian terang lain
 *    diberi garis 1px** sehingga batasnya selalu terlihat tanpa menambah warna;
 * 2. setiap {@link JEDA_GELAP_SETIAP} bagian terang berturut-turut, satu bagian dibuat **gelap** (Navy)
 *    sebagai jeda baca — meniru blok full-bleed Square tanpa gradien;
 * 3. bagian pertama setelah blok berlatar sendiri menyesuaikan diri: setelah blok **gelap** ia mulai putih,
 *    setelah hero **terang** ia mulai `Latar`, supaya tidak ada dua permukaan putih yang menempel.
 *
 * Dua blok sejenis yang berdampingan otomatis berbeda latar karena selang-seling; bentuknya dibedakan
 * lewat `TataLetak` pada blok Keunggulan.
 */
export function HitungIrama(bagian: BagianSitus[]): IramaBagian[] {
    const hasil: IramaBagian[] = [];
    let terangBerturut = 0;
    let berikutnyaPermukaan = true;
    // null = bagian sebelumnya gelap atau mengatur latarnya sendiri, jadi tidak perlu garis pemisah.
    let terangSebelumnya: LatarBagian | null = null;

    const Putus = (latar: LatarBagian): void => {
        terangBerturut = 0;
        // Setelah blok gelap mata butuh permukaan putih; setelah hero terang mulai dari Latar agar berbeda.
        berikutnyaPermukaan = latar !== 'permukaan';
        terangSebelumnya = latar === 'permukaan' ? latar : null;
        hasil.push({ latar, garisAtas: false });
    };

    for (const b of bagian) {
        if (LATAR_SENDIRI.has(b.Jenis)) {
            Putus(LatarSendiri(b));

            continue;
        }

        if (terangBerturut >= JEDA_GELAP_SETIAP && !SELALU_TERANG.has(b.Jenis)) {
            Putus('navy');

            continue;
        }

        const latar: LatarBagian = berikutnyaPermukaan ? 'permukaan' : 'latar';
        berikutnyaPermukaan = !berikutnyaPermukaan;
        terangBerturut += 1;
        hasil.push({ latar, garisAtas: terangSebelumnya !== null });
        terangSebelumnya = latar;
    }

    return hasil;
}

/** Render daftar blok halaman situs (D-21). Blok Hero pertama memakai `<h1>`. */
export default function RenderBagian({ bagian }: { bagian: BagianSitus[] }) {
    const irama = HitungIrama(bagian);

    return (
        <>
            {bagian.map((b, i) => {
                const kunci = `${b.Jenis}-${i}`;
                const { latar, garisAtas } = irama[i] ?? { latar: 'permukaan' as LatarBagian, garisAtas: false };

                switch (b.Jenis) {
                    case 'Hero':
                        return <BagianHero key={kunci} bagian={b} utama={i === 0} />;
                    case 'Keunggulan':
                        return <BagianKeunggulan key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'Sektor':
                        return <BagianSektor key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'GambarTeks':
                        return <BagianGambarTeks key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'Statistik':
                        return <BagianStatistik key={kunci} bagian={b} />;
                    case 'Testimoni':
                        return <BagianTestimoni key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'Harga':
                        return <BagianHarga key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'Faq':
                        return <BagianFaq key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'Cta':
                        return <BagianCta key={kunci} bagian={b} />;
                    case 'TeksBebas':
                        return <BagianTeksBebas key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'LogoMitra':
                        return <BagianLogoMitra key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'Video':
                        return <BagianVideo key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'UnduhAplikasi':
                        return <BagianUnduhAplikasi key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'Kontak':
                        return <BagianKontak key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    case 'FormulirProspek':
                        return <BagianFormulirProspek key={kunci} bagian={b} latar={latar} garisAtas={garisAtas} />;
                    default:
                        return null;
                }
            })}
        </>
    );
}
