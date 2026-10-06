import type { CSSProperties } from 'react';
import gambarIkon from '@/Aset/Merek/IkonMerek.webp';
import { cn } from '@/Komponen/Ui/utils';

type PropsTandaMuat = { ukuran?: number; className?: string; label?: string };

/**
 * Tanda muat Payoung (D-58): logo payung di lingkaran putih, dikelilingi cincin warna token tanpa jarak yang berputar.
 * Gaya di `Gaya/Muat.css`; `ukuran` dalam px. Untuk layar penuh dan keadaan memuat panel besar, bukan di dalam tombol
 * (tombol memakai `Spinner`).
 */
export function TandaMuat({ ukuran = 64, className, label = 'Memuat' }: PropsTandaMuat) {
    return (
        <div
            role="status"
            aria-label={label}
            className={cn('tanda-muat', className)}
            style={{ '--u': `${ukuran}px` } as CSSProperties}
        >
            <span className="tanda-muat__isi">
                <img src={gambarIkon} alt="" />
            </span>
        </div>
    );
}
