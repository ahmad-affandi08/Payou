/** Penanda di pratinjau editor untuk blok yang isinya belum lengkap (D-63); tidak pernah ada di situs publik. */
export default function BagianBelumLengkap({ label }: { label: string }) {
    return (
        <section className="px-4 py-10 sm:px-6">
            <div className="mx-auto max-w-3xl rounded-panel border-2 border-dashed border-garis-input bg-permukaan p-8 text-center">
                <p className="text-subjudul font-semibold text-teks-utama">{label} belum lengkap</p>
                <p className="mt-1 text-isi text-teks-sekunder">
                    Lengkapi isian yang ditandai merah di panel kiri, lalu blok ini akan tampil di sini.
                </p>
            </div>
        </section>
    );
}
