import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, LogOut, Mail } from 'lucide-react';
import { logout } from '@/routes';
import type { Auth } from '@/types';

type Props = {
    name: string;
    email: string;
    department: string | null;
};

function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function MobileAccount({ name, email, department }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Account" />

            <header className="rounded-b-[1.75rem] bg-purple-950 px-6 pt-8 pb-8 text-center text-white">
                <div className="mx-auto flex size-20 items-center justify-center overflow-hidden rounded-full border-[3px] border-yellow-400 bg-purple-900 text-xl font-bold">
                    {auth.user.avatar ? (
                        <img src={auth.user.avatar} alt="" className="size-full object-cover" />
                    ) : (
                        initials(name)
                    )}
                </div>
                <p className="mt-4 text-xl font-semibold">{name}</p>
                <p className="text-[13px] text-purple-200">{email}</p>
            </header>

            <div className="p-4">
                <div className="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div className="flex items-center gap-3 px-4 py-3.5">
                        <Building2 className="size-5 text-yellow-600" aria-hidden />
                        <div>
                            <div className="text-[11px] tracking-wide text-gray-800 uppercase">Department</div>
                            <div className="text-sm font-semibold text-purple-950">{department ?? 'Not assigned'}</div>
                        </div>
                    </div>
                    <div className="border-t border-black/5" />
                    <div className="flex items-center gap-3 px-4 py-3.5">
                        <Mail className="size-5 text-yellow-600" aria-hidden />
                        <div className="min-w-0">
                            <div className="text-[11px] tracking-wide text-gray-800 uppercase">Email</div>
                            <div className="truncate text-sm font-semibold text-purple-950">{email}</div>
                        </div>
                    </div>
                </div>

                <Link
                    href={logout()}
                    as="button"
                    onClick={() => router.flushAll()}
                    className="mt-4 flex w-full items-center justify-center gap-2 rounded-3xl border-2 border-red-500 bg-white py-4 text-base font-bold text-red-600 active:scale-[0.99]"
                >
                    <LogOut className="size-5" />
                    Log out
                </Link>
            </div>
        </>
    );
}
