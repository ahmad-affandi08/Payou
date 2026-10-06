import type { ChangeEvent, ComponentProps } from 'react';
import type { DropdownProps } from 'react-day-picker';
import { id as lokalId } from 'react-day-picker/locale';

import PilihanCari from '@/Komponen/Formulir/PilihanCari';
import { Calendar, CalendarDayButton } from '@/Komponen/Ui/calendar';
import { cn } from '@/Komponen/Ui/utils';

type PropsKalender = ComponentProps<typeof Calendar>;

const namaBulan = new Intl.DateTimeFormat('id-ID', { month: 'long' });

/** Tombol hari: hover & rentang tengah memakai brand lembut, angka tabular. */
function HariKalender({ className, ...sisa }: ComponentProps<typeof CalendarDayButton>) {
    return (
        <CalendarDayButton
            className={cn(
                'text-label tabular-nums hover:bg-brand-lembut data-[range-middle=true]:bg-brand-lembut data-[range-middle=true]:text-teks-utama',
                className,
            )}
            {...sisa}
        />
    );
}

/** Pilihan bulan/tahun di judul kalender: `PilihanCari` (bisa dicari, tidak menutupi pemicunya). */
function DropdownKalender({ options = [], value, onChange, disabled, 'aria-label': labelAria }: DropdownProps) {
    const label = labelAria ?? 'Pilih';

    return (
        <PilihanCari
            label={label}
            aria-label={label}
            nilai={String(value ?? '')}
            opsi={options.filter((o) => !o.disabled).map((o) => ({ Nilai: String(o.value), Label: o.label }))}
            saatBerubah={(nilai) => onChange?.({ target: { value: nilai } } as ChangeEvent<HTMLSelectElement>)}
            disabled={disabled}
            className="h-8 w-auto gap-1 px-2 text-label font-semibold shadow-none pointer-coarse:h-11"
        />
    );
}

/**
 * Kalender Payoung (§17.6): bahasa Indonesia, minggu dimulai Senin, pilihan bulan & tahun untuk lompat jauh
 * (mis. tanggal kedaluwarsa), hari ini ditandai garis bawah, pilihan memakai warna brand, rentang tengah
 * memakai brand lembut. Ukuran sel 32px, ringkas untuk back-office (44px pada layar sentuh) sesuai target sentuh §17.4.4.
 */
export default function Kalender({ className, classNames, components, ...props }: PropsKalender) {
    return (
        <Calendar
            locale={lokalId}
            weekStartsOn={1}
            captionLayout="dropdown"
            startMonth={new Date(2000, 0)}
            endMonth={new Date(new Date().getFullYear() + 15, 11)}
            formatters={{ formatMonthDropdown: (tanggal) => namaBulan.format(tanggal) }}
            className={cn(
                'bg-transparent p-0 [--cell-size:--spacing(8)] pointer-coarse:[--cell-size:--spacing(11)]',
                className,
            )}
            classNames={{
                months: 'relative flex flex-col gap-4 sm:flex-row',
                // Bilah panah melapisi judul: biarkan klik tembus ke pemicu bulan/tahun, kecuali tombol panahnya.
                nav: 'pointer-events-none absolute inset-x-0 top-0 z-[1] flex w-full items-center justify-between gap-1 [&>button]:pointer-events-auto',
                month_caption: 'flex h-(--cell-size) w-full items-center justify-center px-(--cell-size)',
                dropdowns: 'flex h-(--cell-size) w-full items-center justify-center gap-1.5 text-label font-semibold',
                dropdown_root:
                    'relative rounded-kontrol border border-garis-input has-focus:border-brand has-focus:ring-2 has-focus:ring-brand/40',
                caption_label:
                    'flex h-8 items-center gap-1 rounded-kontrol pr-1 pl-2 text-label font-semibold text-teks-utama select-none [&>svg]:size-3.5 [&>svg]:text-teks-sekunder',
                weekday: 'flex-1 text-keterangan font-semibold text-teks-sekunder select-none',
                today: 'font-semibold [&_button]:underline [&_button]:decoration-2 [&_button]:underline-offset-4',
                outside: 'text-teks-sekunder/60 aria-selected:text-teks-sekunder',
                disabled: 'text-teks-sekunder/40 [&_button]:cursor-not-allowed',
                range_start: 'rounded-l-kontrol bg-brand-lembut',
                range_middle: 'rounded-none',
                range_end: 'rounded-r-kontrol bg-brand-lembut',
                ...classNames,
            }}
            components={{
                DayButton: HariKalender,
                Dropdown: DropdownKalender,
                ...components,
            }}
            {...props}
        />
    );
}
