import { Head, Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import AlertController from '@/actions/App/Http/Controllers/Mobile/AlertController';
import { cn } from '@/lib/utils';
import type { Alert } from '@/types/mobile';

type Props = {
    alerts: Alert[];
    unread: number;
};

export default function MobileAlerts({ alerts, unread }: Props) {
    return (
        <>
            <Head title="Alerts" />

            <header className="flex items-start justify-between rounded-b-[1.5rem] bg-purple-950 px-6 pt-6 pb-6 text-white">
                <div>
                    <h1 className="text-xl font-bold">Alerts</h1>
                    <p className="mt-0.5 text-[12.5px] text-purple-200">
                        Approvals and announcements
                    </p>
                </div>
                {unread > 0 && (
                    <Link
                        href={AlertController.markAllRead.url()}
                        method="post"
                        as="button"
                        preserveScroll
                        className="mt-1 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-white active:scale-95"
                    >
                        Mark all read
                    </Link>
                )}
            </header>

            <div className="p-4">
                {alerts.length === 0 ? (
                    <div className="rounded-2xl bg-white p-8 text-center shadow-sm">
                        <Bell className="mx-auto mb-2 size-8 text-gray-400" />
                        <p className="text-sm text-gray-800">
                            You're all caught up.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {alerts.map((alert) => (
                            <div
                                key={alert.id}
                                className={cn(
                                    'relative rounded-2xl p-4 shadow-sm',
                                    alert.read
                                        ? 'bg-white'
                                        : 'bg-white ring-1 ring-status-complete/30',
                                )}
                            >
                                {!alert.read && (
                                    <span className="absolute top-4 right-4 size-2 rounded-full bg-status-complete" />
                                )}
                                <div className="pr-5 text-sm font-bold text-purple-950">
                                    {alert.title}
                                </div>
                                {alert.body && (
                                    <div className="mt-1 text-[12.5px] leading-relaxed text-gray-800">
                                        {alert.body}
                                    </div>
                                )}
                                <div className="mt-1.5 text-[11px] text-gray-600">
                                    {alert.time}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
