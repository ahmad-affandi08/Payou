import { Check, Circle } from 'lucide-react';

import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { RingkasIsiLaundry, type StatusLaundryPublik } from '@/Tipe/Laundry';

/**
 * Laundry (§9.9): status proses cucian di halaman lacak publik (struk digital dari QR label/nota). Setiap tahap
 * berteks + ikon (tidak hanya warna).
 */
export default function StatusLacakLaundry({ Laundry }: { Laundry: StatusLaundryPublik }) {
    const dibatalkan = Laundry.Status === 'Dibatalkan';
    return (
        <section
            aria-label="Status cucian"
            className="flex flex-col gap-3 rounded-kontrol border border-garis bg-permukaan p-4"
        >
            <div>
                <h2 className="text-isi font-semibold text-teks-utama">Status cucian: {Laundry.LabelStatus}</h2>
                <p className="text-keterangan text-teks-sekunder">
                    {Laundry.JenisLayanan} | {RingkasIsiLaundry(Laundry)}
                    {Laundry.Parfum ? ` | parfum ${Laundry.Parfum}` : ''}
                </p>
                {Laundry.DiambilPada ? (
                    <p className="text-keterangan text-teks-sekunder">
                        Diambil {FormatTanggalWaktu(Laundry.DiambilPada)}
                    </p>
                ) : Laundry.SiapPada ? (
                    <p className="text-keterangan text-teks-sekunder">
                        Siap sejak {FormatTanggalWaktu(Laundry.SiapPada)}
                    </p>
                ) : dibatalkan ? null : (
                    <p className="text-keterangan text-teks-sekunder">
                        Perkiraan selesai {FormatTanggalWaktu(Laundry.EstimasiSelesaiPada)}
                    </p>
                )}
            </div>
            {dibatalkan ? (
                <p role="status" className="text-keterangan font-semibold text-bahaya">
                    Transaksi cucian ini dibatalkan.
                </p>
            ) : (
                <ol className="flex flex-col gap-1">
                    {Laundry.Tahap.map((t) => (
                        <li
                            key={t.Status}
                            className={`flex items-center gap-2 text-keterangan ${t.Selesai ? 'text-teks-utama' : 'text-teks-sekunder'}`}
                        >
                            {t.Selesai ? (
                                <Check aria-hidden className="size-4 text-sukses" />
                            ) : (
                                <Circle aria-hidden className="size-4" />
                            )}
                            <span>{t.Label}</span>
                            <span className="sr-only">{t.Selesai ? '(sudah)' : '(belum)'}</span>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
