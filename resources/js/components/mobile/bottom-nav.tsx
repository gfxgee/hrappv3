import { Link, usePage } from '@inertiajs/react';
import { Bell, Clock, Home, Users } from 'lucide-react';
import AlertController from '@/actions/App/Http/Controllers/Mobile/AlertController';
import AttendanceController from '@/actions/App/Http/Controllers/Mobile/AttendanceController';
import HomeController from '@/actions/App/Http/Controllers/Mobile/HomeController';
import TeamController from '@/actions/App/Http/Controllers/Mobile/TeamController';
import { cn } from '@/lib/utils';

const items = [
    { label: 'Home', icon: Home, href: HomeController.index() },
    { label: 'Attendance', icon: Clock, href: AttendanceController.index() },
    { label: 'Team', icon: Users, href: TeamController.index() },
    { label: 'Alerts', icon: Bell, href: AlertController.index() },
] as const;

export function BottomNav({ unread = 0 }: { unread?: number }) {
    const { url } = usePage();

    return (
        <nav className="flex shrink-0 border-t border-black/5 bg-white">
            {items.map(({ label, icon: Icon, href }) => {
                const active = url.startsWith(href.url);

                return (
                    <Link
                        key={label}
                        href={href}
                        prefetch
                        className={cn(
                            'relative flex flex-1 flex-col items-center gap-1 pt-2.5 pb-[calc(0.625rem+env(safe-area-inset-bottom))] text-[11px] font-medium transition-colors',
                            active ? 'text-purple-950' : 'text-gray-800',
                        )}
                    >
                        <Icon
                            className={cn(
                                'size-5',
                                active
                                    ? 'stroke-[2.25]'
                                    : 'stroke-2 opacity-70',
                            )}
                            aria-hidden
                        />
                        {label}
                        {label === 'Alerts' && unread > 0 && (
                            <span className="absolute top-1 right-[calc(50%-22px)] flex h-4 min-w-4 items-center justify-center rounded-full bg-yellow-500 px-1 text-[10px] font-bold text-white">
                                {unread > 9 ? '9+' : unread}
                            </span>
                        )}
                    </Link>
                );
            })}
        </nav>
    );
}
