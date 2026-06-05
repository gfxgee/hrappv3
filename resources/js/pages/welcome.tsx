import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Feature = {
    title: string;
    description: string;
    icon: ReactNode;
    accent: string;
};

const features: Feature[] = [
    {
        title: 'Attendance',
        description: 'Clock in and out from the web, with a clear daily record for every employee.',
        accent: 'bg-[#099FDD]/10 text-[#099FDD]',
        icon: (
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z" />
        ),
    },
    {
        title: 'Leave & Overtime',
        description: 'File leave and overtime requests, track remaining credits, and route them for approval.',
        accent: 'bg-[#E94E1B]/10 text-[#E94E1B]',
        icon: (
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z" />
        ),
    },
    {
        title: 'Team Calendar',
        description: "See who's away across every department, filter by team, and keep holidays in view.",
        accent: 'bg-[#238B7F]/10 text-[#238B7F]',
        icon: (
            <path strokeLinecap="round" strokeLinejoin="round" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
        ),
    },
    {
        title: 'Praise Wall',
        description: 'Recognize teammates with badges, react and comment, and crown winners each cycle.',
        accent: 'bg-[#D60B52]/10 text-[#D60B52]',
        icon: (
            <path strokeLinecap="round" strokeLinejoin="round" d="M20.8 5.6a5.5 5.5 0 0 0-7.8 0L12 6.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 22l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z" />
        ),
    },
];

export default function Welcome() {
    const { auth } = usePage().props;
    const year = new Date().getFullYear();

    return (
        <>
            <Head title="Digitalfeet.HR" />

            <div className="relative flex min-h-screen flex-col overflow-hidden bg-white text-[#271A3D] dark:bg-[#271A3D] dark:text-white">
                {/* Decorative floating gradients */}
                <div aria-hidden className="pointer-events-none absolute inset-0 overflow-hidden">
                    <div className="absolute -top-32 -left-24 h-96 w-96 animate-pulse rounded-full bg-[#271A3D]/15 blur-3xl dark:bg-[#099FDD]/20" />
                    <div className="absolute -right-24 top-40 h-96 w-96 animate-pulse rounded-full bg-[#F99F29]/30 blur-3xl [animation-delay:1s]" />
                    <div className="absolute bottom-0 left-1/3 h-80 w-80 animate-pulse rounded-full bg-[#099FDD]/25 blur-3xl [animation-delay:2s]" />
                </div>

                {/* Header: logo + login only */}
                <header className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
                    <span className="animate-in fade-in slide-in-from-left-4 font-serif text-xl font-bold tracking-tight duration-700 fill-mode-both">
                        Digitalfeet<span className="text-[#F99F29]">.HR</span>
                    </span>

                    <a
                        href={auth.user ? '/admin' : '/admin/login'}
                        className="animate-in fade-in slide-in-from-right-4 inline-flex items-center rounded-lg bg-[#271A3D] px-5 py-2 text-sm font-semibold text-white shadow-sm transition duration-700 fill-mode-both hover:bg-[#271A3D]/90 dark:bg-[#F99F29] dark:text-[#271A3D] dark:hover:bg-[#F99F29]/90"
                    >
                        {auth.user ? 'Open app' : 'Log in'}
                    </a>
                </header>

                {/* Hero */}
                <main className="relative z-10 mx-auto flex w-full max-w-6xl flex-1 flex-col items-center px-6 pt-12 text-center sm:pt-20">
                    <span
                        className="animate-in fade-in zoom-in-95 inline-flex items-center gap-2 rounded-full border border-[#F99F29]/40 bg-[#F99F29]/10 px-4 py-1.5 text-xs font-semibold text-[#271A3D] duration-700 fill-mode-both dark:text-[#F99F29]"
                        style={{ animationDelay: '100ms' }}
                    >
                        🌱 People-first HR
                    </span>

                    <h1
                        className="animate-in fade-in slide-in-from-bottom-4 mt-6 max-w-3xl text-4xl font-extrabold tracking-tight duration-700 fill-mode-both sm:text-6xl"
                        style={{ animationDelay: '200ms' }}
                    >
                        Everything your team needs,
                        <span className="block bg-gradient-to-r from-[#271A3D] to-[#099FDD] bg-clip-text text-transparent dark:from-[#F99F29] dark:to-[#099FDD]">
                            in one calm place.
                        </span>
                    </h1>

                    <p
                        className="animate-in fade-in slide-in-from-bottom-4 mt-5 max-w-xl text-base text-[#271A3D]/70 duration-700 fill-mode-both dark:text-white/70 sm:text-lg"
                        style={{ animationDelay: '350ms' }}
                    >
                        Attendance, leave, overtime, and recognition — Digitalfeet.HR keeps your people and
                        their day-to-day in sync, without the spreadsheets.
                    </p>

                    <div
                        className="animate-in fade-in slide-in-from-bottom-4 mt-8 flex flex-wrap items-center justify-center gap-3 duration-700 fill-mode-both"
                        style={{ animationDelay: '500ms' }}
                    >
                        <a
                            href={auth.user ? '/admin' : '/admin/login'}
                            className="inline-flex items-center gap-2 rounded-xl bg-[#271A3D] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#271A3D]/90 dark:bg-[#F99F29] dark:text-[#271A3D] dark:hover:bg-[#F99F29]/90"
                        >
                            {auth.user ? 'Open the app' : 'Log in to continue'}
                            <span aria-hidden>→</span>
                        </a>
                    </div>

                    {/* Feature cards */}
                    <div className="mt-16 grid w-full gap-5 pb-20 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature, index) => (
                            <div
                                key={feature.title}
                                className="animate-in fade-in slide-in-from-bottom-6 group rounded-2xl border border-[#271A3D]/10 bg-white/70 p-5 text-left shadow-sm backdrop-blur transition duration-700 fill-mode-both hover:-translate-y-1 hover:shadow-md dark:border-white/10 dark:bg-white/5"
                                style={{ animationDelay: `${650 + index * 120}ms` }}
                            >
                                <span className={`flex h-11 w-11 items-center justify-center rounded-xl ${feature.accent}`}>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" strokeWidth={1.7} stroke="currentColor" className="h-6 w-6">
                                        {feature.icon}
                                    </svg>
                                </span>
                                <h3 className="mt-4 text-base font-bold">{feature.title}</h3>
                                <p className="mt-1 text-sm text-[#271A3D]/60 dark:text-white/60">{feature.description}</p>
                            </div>
                        ))}
                    </div>
                </main>

                {/* Simple footer */}
                <footer className="relative z-10 border-t border-[#271A3D]/10 py-6 text-center text-xs text-[#271A3D]/50 dark:border-white/10 dark:text-white/50">
                    © {year} Digitalfeet.HR — All rights reserved.
                </footer>
            </div>
        </>
    );
}
