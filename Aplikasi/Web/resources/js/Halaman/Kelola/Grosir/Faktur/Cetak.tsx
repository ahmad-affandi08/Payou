import BingkaiCetakGrosir, {
    AlamatCetak,
    AlamatPembeliCetak,
    AlamatPenjualCetak,
    BarisKepalaCetak,
    RingkasanCetak,
} from '@/Komponen/Grosir/BingkaiCetakGrosir';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { FormatPersen, FormatRupiah } from '@/Pustaka/Format';
import { FormatJumlahStok } from '@/Pustaka/FormatPersediaan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { PropsCetakFaktur } from '@/Tipe/Grosir';

/**
 * Cetak faktur penjualan grosir (F-12, §9.7, BR-12.4). Ini **tagihan komersial**, bukan Faktur Pajak: nomor Faktur
 * Pajak dicantumkan bila sudah didapat dari e-Faktur/Coretax, tetapi dokumen pajaknya sendiri terbit dari sana.
 *
 * Tiap baris menyebut surat jalan asalnya, karena faktur gabungan memang menagih beberapa penyerahan sekaligus dan
 * bagian pembelian pembeli mencocokkannya dengan surat jalan yang mereka tanda tangani.
 */
export default function HalamanCetakFakturGrosir({ Faktur, SuratJalan, Baris, Usaha }: PropsCetakFaktur) {
    return (
        <BingkaiCetakGrosir
            judulDokumen="Faktur Penjualan"
            judulTab={`Faktur ${Faktur.Nomor}`}
            nomor={Faktur.Nomor}
            usaha={Usaha}
            dibatalkan={Faktur.Status === 'Dibatalkan'}
            alasanBatal={Faktur.AlasanBatal}
            kepala={
                <>
                    <BarisKepalaCetak label="Tanggal">{FormatTanggal(Faktur.Tanggal)}</BarisKepalaCetak>
                    <BarisKepalaCetak label="Jatuh tempo">{FormatTanggal(Faktur.JatuhTempo)}</BarisKepalaCetak>
                    {Faktur.NomorFakturPajak ? (
                        <BarisKepalaCetak label="Faktur Pajak">{Faktur.NomorFakturPajak}</BarisKepalaCetak>
                    ) : null}
                </>
            }
            tandaTangan={[{ label: 'Diterbitkan oleh' }, { label: 'Diterima oleh' }]}
        >
            <section className="grid gap-4 sm:grid-cols-2">
                <AlamatPembeliCetak judul="Ditagihkan kepada" pelanggan={Faktur.Pelanggan} />
                <div className="flex flex-col gap-4">
                    <AlamatPenjualCetak outlet={Faktur.Outlet} />
                    <AlamatCetak judul="Termin">
                        <p>{Faktur.TerminHari === 0 ? 'Tunai' : `${String(Faktur.TerminHari)} hari`}</p>
                        <p className="text-teks-sekunder">Periode penyerahan {Faktur.PeriodePenyerahan}</p>
                    </AlamatCetak>
                </div>
            </section>

            <Table className="text-left">
                <TableHeader>
                    <TableRow>
                        <TableHead scope="col">Barang</TableHead>
                        <TableHead scope="col">Surat jalan</TableHead>
                        <TableHead scope="col" className="text-right">
                            Jumlah
                        </TableHead>
                        <TableHead scope="col" className="text-right">
                            Harga
                        </TableHead>
                        <TableHead scope="col" className="text-right">
                            Diskon
                        </TableHead>
                        <TableHead scope="col" className="text-right">
                            Subtotal
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {Baris.map((b) => (
                        <TableRow key={b.Kunci}>
                            <TableCell className="whitespace-normal">
                                {b.NamaProduk}
                                {b.Sku ? <span className="block font-mono text-keterangan">{b.Sku}</span> : null}
                            </TableCell>
                            <TableCell className="font-mono text-keterangan">{b.NomorSuratJalan}</TableCell>
                            <TableCell className="text-right tabular-nums">
                                {FormatJumlahStok(b.Jumlah, b.SimbolSatuan)}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">{FormatRupiah(b.Harga)}</TableCell>
                            <TableCell className="text-right tabular-nums">{FormatRupiah(b.Diskon)}</TableCell>
                            <TableCell className="text-right tabular-nums">{FormatRupiah(b.Subtotal)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            <RingkasanCetak
                baris={[
                    { label: 'Subtotal', nilai: Faktur.Subtotal },
                    { label: 'Diskon', nilai: Faktur.Diskon },
                    { label: 'Dasar pengenaan pajak', nilai: Faktur.DasarPengenaanPajak },
                    {
                        label: Faktur.TarifPpn === null ? 'PPN' : `PPN ${FormatPersen(Faktur.TarifPpn)}`,
                        nilai: Faktur.Pajak,
                    },
                ]}
                labelTotal="Jumlah tagihan"
                total={Faktur.Total}
            />

            <section>
                <h2 className="text-label font-semibold text-teks-sekunder">Penyerahan yang ditagihkan</h2>
                <ul className="flex flex-col gap-0.5">
                    {SuratJalan.map((s) => (
                        <li key={s.Nomor}>
                            <span className="font-mono">{s.Nomor}</span> | {FormatTanggal(s.Tanggal)} |{' '}
                            <span className="tabular-nums">{FormatRupiah(s.Total)}</span>
                        </li>
                    ))}
                </ul>
            </section>

            {Faktur.Catatan ? (
                <AlamatCetak judul="Catatan">
                    <p className="whitespace-pre-line">{Faktur.Catatan}</p>
                </AlamatCetak>
            ) : null}
        </BingkaiCetakGrosir>
    );
}
