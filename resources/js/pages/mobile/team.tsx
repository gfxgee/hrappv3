import { Head } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { TeamMember } from '@/types/mobile';

type Props = {
    departmentName: string | null;
    today: string;
    members: TeamMember[];
};

const statusPill: Record<TeamMember['status'], string> = {
    in: 'bg-status-complete/10 text-status-complete',
    wfh: 'bg-blue-50 text-blue-700',
    leave: 'bg-yellow-50 text-yellow-700',
    sick: 'bg-red-50 text-red-600',
    out: 'bg-gray-100 text-gray-800',
};

const statusLabel: Record<TeamMember['status'], string> = {
    in: 'In',
    wfh: 'WFH',
    leave: 'Leave',
    sick: 'Sick',
    out: 'Out',
};

export default function MobileTeam({ departmentName, today, members }: Props) {
    return (
        <>
            <Head title="Team today" />

            <header className="rounded-b-[1.5rem] bg-purple-950 px-6 pt-6 pb-6 text-white">
                <h1 className="text-xl font-bold">Team today</h1>
                <p className="mt-0.5 text-[12.5px] text-purple-200">
                    {departmentName ? `${departmentName} · ${today}` : today}
                </p>
            </header>

            <div className="p-4">
                {members.length === 0 ? (
                    <p className="rounded-2xl bg-white p-5 text-center text-sm text-gray-800 shadow-sm">
                        You're not assigned to a department yet, so there's no
                        team to show.
                    </p>
                ) : (
                    <div className="flex flex-col gap-2">
                        {members.map((member) => (
                            <div
                                key={member.id}
                                className="flex items-center gap-3 rounded-2xl bg-white p-3.5 shadow-sm"
                            >
                                <span className="flex size-10 items-center justify-center rounded-full bg-purple-100 text-[13px] font-bold text-purple-950">
                                    {member.initials}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="text-sm font-bold text-purple-950">
                                        {member.name}
                                    </div>
                                    <div className="text-xs text-gray-800">
                                        {member.label}
                                    </div>
                                </div>
                                <span
                                    className={cn(
                                        'rounded-full px-2.5 py-1 text-[10.5px] font-bold',
                                        statusPill[member.status],
                                    )}
                                >
                                    {statusLabel[member.status]}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
