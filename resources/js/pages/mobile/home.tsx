import { Form, Head, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import PunchController from '@/actions/App/Http/Controllers/Mobile/PunchController';
import { LeaveSheet } from '@/components/mobile/leave-sheet';
import { OvertimeSheet } from '@/components/mobile/overtime-sheet';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';
import type { ClockSnapshot, LeaveBalance, RecentLeave } from '@/types/mobile';

type Props = {
    greeting: string;
    today: string;
    clock: ClockSnapshot;
    balances: LeaveBalance[];
    recent: RecentLeave[];
};

function statusPill(status: string): string {
    switch (status) {
        case 'approved':
        case 'verified':
            return 'bg-status-complete/10 text-status-complete';
        case 'rejected':
            return 'bg-red-50 text-red-600';
        case 'cancelled':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-yellow-50 text-yellow-700';
    }
}

function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

/** Live wall-clock time, or elapsed shift time when clocked in. */
function useLiveTime(clock: ClockSnapshot): string {
    // Seeded once (lazy) then advanced only from the interval callback, so the
    // render stays pure and there is no synchronous setState in the effect.
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(id);
    }, []);

    if (clock.status === 'in_progress' && clock.clock_in_iso) {
        const seconds = Math.max(
            0,
            Math.floor((now - Date.parse(clock.clock_in_iso)) / 1000),
        );

        return `${Math.floor(seconds / 3600)}h ${String(Math.floor((seconds % 3600) / 60)).padStart(2, '0')}m`;
    }

    return new Date(now).toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function MobileHome({
    greeting,
    today,
    clock,
    balances,
    recent,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const user = auth.user;
    const time = useLiveTime(clock);

    const clockedIn = clock.status === 'in_progress';
    const done = clock.status === 'done';

    return (
        <>
            <Head title="Home" />

            <header className="rounded-b-[1.75rem] bg-purple-950 px-6 pt-6 pb-11 text-center text-white">
                <div className="flex items-center justify-between text-[11px] font-semibold tracking-widest text-purple-200 uppercase">
                    <span>{today.split(',')[0]}</span>
                    <span>{today.split(',').slice(1).join(',').trim()}</span>
                </div>
                <div className="mx-auto -mb-1 flex size-14 items-center justify-center overflow-hidden rounded-full border-[3px] border-yellow-400 bg-purple-900 text-sm font-bold">
                    {user.avatar ? (
                        <img
                            src={user.avatar}
                            alt=""
                            className="size-full object-cover"
                        />
                    ) : (
                        initials(user.name)
                    )}
                </div>
                <p className="mt-5 text-xl font-semibold">{greeting}</p>
            </header>

            <div className="-mt-6 px-4 pb-6">
                <section className="rounded-3xl bg-white p-5 text-center shadow-xl shadow-purple-950/10">
                    <p className="text-[11px] font-bold tracking-widest text-gray-800 uppercase">
                        {clockedIn ? 'Time worked today' : 'Right now'}
                    </p>
                    <p className="my-1 text-5xl font-extrabold tracking-tight text-purple-950 tabular-nums">
                        {time}
                    </p>
                    <p className="mb-4 min-h-5 text-sm text-gray-800">
                        {clockedIn && (
                            <span className="mr-1.5 inline-block size-2 rounded-full bg-status-complete align-middle" />
                        )}
                        {done
                            ? `Clocked out at ${clock.clock_out_at}`
                            : clockedIn
                              ? `Clocked in at ${clock.clock_in_at}`
                              : "You haven't clocked in yet today"}
                    </p>

                    {done ? (
                        <div className="w-full rounded-2xl bg-beige-100 py-4 text-base font-bold text-gray-800">
                            Shift complete — see you tomorrow
                        </div>
                    ) : (
                        <Form
                            action={PunchController.store.url()}
                            method="post"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className={cn(
                                        'w-full rounded-2xl py-4 text-base font-bold active:scale-[0.98] disabled:opacity-60',
                                        clockedIn
                                            ? 'border-2 border-red-500 bg-white text-red-600'
                                            : 'bg-yellow-400 text-yellow-950',
                                    )}
                                >
                                    {clockedIn ? 'Clock out' : 'Clock in'}
                                </button>
                            )}
                        </Form>
                    )}
                </section>

                <h2 className="mt-6 mb-2.5 ml-1.5 text-[11px] font-bold tracking-widest text-gray-800 uppercase">
                    Your leave balance
                </h2>
                <div className="-mx-1 flex [scrollbar-width:none] gap-2.5 overflow-x-auto px-1 pb-1.5">
                    {balances.map((balance) => (
                        <div
                            key={balance.type}
                            className="min-w-[104px] shrink-0 rounded-2xl bg-white p-3.5 shadow-sm"
                        >
                            <div className="text-base">{balance.icon}</div>
                            <div className="mt-1.5 text-[22px] font-extrabold tracking-tight text-purple-950 tabular-nums">
                                {balance.tracked ? balance.remaining : '—'}
                            </div>
                            <div className="text-[11.5px] text-gray-800">
                                {balance.label.replace(/ Leave$/, '')}
                            </div>
                        </div>
                    ))}
                </div>

                <h2 className="mt-6 mb-2.5 ml-1.5 text-[11px] font-bold tracking-widest text-gray-800 uppercase">
                    File a request
                </h2>
                <LeaveSheet
                    balances={balances}
                    trigger={
                        <button
                            type="button"
                            className="flex w-full items-center gap-3 rounded-3xl bg-purple-950 px-5 py-4 text-left text-white shadow-lg shadow-purple-950/15 active:scale-[0.99]"
                        >
                            <span className="flex size-10 items-center justify-center rounded-xl bg-yellow-400/20 text-xl">
                                🗓️
                            </span>
                            <span>
                                <span className="block text-[16.5px] font-bold">
                                    File a leave
                                </span>
                                <span className="block text-xs text-purple-200">
                                    2 taps — pick a type, then confirm
                                </span>
                            </span>
                            <ChevronRight className="ml-auto size-5 text-purple-300" />
                        </button>
                    }
                />
                <OvertimeSheet
                    trigger={
                        <button
                            type="button"
                            className="mt-3 flex w-full items-center gap-3 rounded-3xl bg-white px-5 py-4 text-left text-purple-950 shadow-sm active:scale-[0.99]"
                        >
                            <span className="flex size-10 items-center justify-center rounded-xl bg-yellow-50 text-xl">
                                ⏱️
                            </span>
                            <span>
                                <span className="block text-[16.5px] font-bold">
                                    Log overtime
                                </span>
                                <span className="block text-xs text-gray-800">
                                    Date, hours, done
                                </span>
                            </span>
                            <ChevronRight className="ml-auto size-5 text-gray-400" />
                        </button>
                    }
                />

                <h2 className="mt-6 mb-2.5 ml-1.5 text-[11px] font-bold tracking-widest text-gray-800 uppercase">
                    Recent requests
                </h2>
                {recent.length === 0 ? (
                    <p className="rounded-2xl bg-white p-4 text-center text-sm text-gray-800 shadow-sm">
                        Nothing filed yet.
                    </p>
                ) : (
                    <div className="flex flex-col gap-2">
                        {recent.map((leave) => (
                            <div
                                key={leave.id}
                                className="flex items-center gap-3 rounded-2xl bg-white p-3.5 shadow-sm"
                            >
                                <span className="text-xl">{leave.icon}</span>
                                <div className="min-w-0 flex-1">
                                    <div className="text-sm font-bold text-purple-950">
                                        {leave.label}
                                    </div>
                                    <div className="text-xs text-gray-800">
                                        {leave.dates}
                                    </div>
                                </div>
                                <span
                                    className={cn(
                                        'rounded-full px-2.5 py-1 text-[10.5px] font-bold',
                                        statusPill(leave.status),
                                    )}
                                >
                                    {leave.status_label}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
