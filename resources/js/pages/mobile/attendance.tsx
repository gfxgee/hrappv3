import { Head, usePage } from '@inertiajs/react';
import { ChevronRight, Download, Wrench } from 'lucide-react';
import dtrPdf from '@/actions/App/Http/Controllers/DtrPdfController';
import { CorrectionSheet } from '@/components/mobile/correction-sheet';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';
import type { ClockSnapshot, DtrRow } from '@/types/mobile';

type Props = {
    weekLabel: string;
    today: ClockSnapshot;
    rows: DtrRow[];
    totals: { hours: string; present: number; leave: number; absent: number };
    correctionTypes: Record<string, string>;
};

export default function MobileAttendance({
    weekLabel,
    today,
    rows,
    totals,
    correctionTypes,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="My attendance" />

            <header className="rounded-b-[1.5rem] bg-purple-950 px-6 pt-6 pb-6 text-white">
                <h1 className="text-xl font-bold">My attendance</h1>
                <p className="mt-0.5 text-[12.5px] text-purple-200">
                    Week of {weekLabel}
                </p>
            </header>

            <div className="p-4">
                <section className="rounded-2xl bg-white p-4 shadow-sm">
                    <div className="mb-3.5 flex items-center justify-between">
                        <b className="text-[15px] text-purple-950">Today</b>
                        <span className="text-[12.5px] text-gray-800">
                            {today.status === 'in_progress'
                                ? 'In progress'
                                : today.status === 'done'
                                  ? 'Complete'
                                  : 'Not started'}
                        </span>
                    </div>
                    <div className="grid grid-cols-3 gap-2 text-center">
                        {[
                            ['Clock in', today.clock_in_at ?? '—'],
                            ['Clock out', today.clock_out_at ?? '—'],
                            ['Hours', today.elapsed_human ?? '—'],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <span className="block text-[11px] text-gray-800">
                                    {label}
                                </span>
                                <span
                                    className={cn(
                                        'mt-0.5 block text-lg font-extrabold tabular-nums',
                                        label === 'Hours'
                                            ? 'text-status-complete'
                                            : 'text-purple-950',
                                    )}
                                >
                                    {value}
                                </span>
                            </div>
                        ))}
                    </div>
                </section>

                <h2 className="mt-6 mb-2.5 ml-1.5 text-[11px] font-bold tracking-widest text-gray-800 uppercase">
                    This week
                </h2>
                <div className="flex flex-col gap-1.5">
                    {rows.map((row) => {
                        const present = row.status === 'Present';

                        return (
                            <div
                                key={row.date}
                                className="flex items-center gap-3 rounded-xl bg-white px-3.5 py-3 shadow-sm"
                            >
                                <span className="w-11 text-[13px] font-bold text-purple-950">
                                    {row.day}
                                </span>
                                <span className="flex-1 text-[12.5px] text-gray-800 tabular-nums">
                                    {present
                                        ? `${row.time_in ?? '—'} → ${row.time_out ?? 'in progress'}`
                                        : row.status}
                                </span>
                                <span
                                    className={cn(
                                        'text-[13.5px] font-extrabold tabular-nums',
                                        present
                                            ? 'text-purple-950'
                                            : 'text-gray-400',
                                    )}
                                >
                                    {present ? row.hours : '—'}
                                </span>
                            </div>
                        );
                    })}
                </div>

                <p className="mt-3 ml-1.5 text-xs text-gray-800">
                    {totals.hours} worked · {totals.present} present ·{' '}
                    {totals.leave} leave · {totals.absent} absent
                </p>

                <CorrectionSheet
                    types={correctionTypes}
                    trigger={
                        <button
                            type="button"
                            className="mt-5 flex w-full items-center gap-3 rounded-3xl bg-white px-5 py-4 text-left text-purple-950 shadow-sm active:scale-[0.99]"
                        >
                            <span className="flex size-10 items-center justify-center rounded-xl bg-yellow-50">
                                <Wrench className="size-5 text-yellow-600" />
                            </span>
                            <span>
                                <span className="block text-[16.5px] font-bold">
                                    Report a correction
                                </span>
                                <span className="block text-xs text-gray-800">
                                    Missed or wrong punch
                                </span>
                            </span>
                            <ChevronRight className="ml-auto size-5 text-gray-400" />
                        </button>
                    }
                />

                <a
                    href={dtrPdf.url({ query: { employee: auth.user.id } })}
                    target="_blank"
                    rel="noopener"
                    className="mt-3 flex w-full items-center justify-center gap-2 rounded-3xl bg-purple-950 py-4 text-base font-bold text-white active:scale-[0.99]"
                >
                    <Download className="size-5" />
                    Download DTR (PDF)
                </a>
            </div>
        </>
    );
}
