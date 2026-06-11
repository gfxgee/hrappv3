import { Head, usePage } from '@inertiajs/react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

gsap.registerPlugin(ScrollTrigger);

type Feature = {
    title: string;
    description: string;
    accent: string;
    icon: ReactNode;
};

const features: Feature[] = [
    {
        title: 'Attendance & DTR',
        description:
            'Web clock in/out plus a clean daily time record with late, undertime, and overtime built in.',
        accent: 'bg-[#099FDD]/10 text-[#099FDD]',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z"
            />
        ),
    },
    {
        title: 'Leave & Overtime',
        description:
            'File requests, track remaining credits, and route approvals to the right people.',
        accent: 'bg-[#E94E1B]/10 text-[#E94E1B]',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z"
            />
        ),
    },
    {
        title: 'Team Calendar',
        description:
            "See who's away across every department and keep holidays in view.",
        accent: 'bg-[#238B7F]/10 text-[#238B7F]',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
            />
        ),
    },
    {
        title: 'Praise Wall',
        description:
            'Recognize teammates with badges, react and comment, and crown winners every cycle.',
        accent: 'bg-[#D60B52]/10 text-[#D60B52]',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M20.8 5.6a5.5 5.5 0 0 0-7.8 0L12 6.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 22l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"
            />
        ),
    },
    {
        title: 'Biometric Import',
        description:
            'Bring fingerprint punches in from your device, trim duplicates, and commit clean records.',
        accent: 'bg-[#F99F29]/15 text-[#b46e0a]',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M7.864 4.243A7.5 7.5 0 0 1 19.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 0 0 4.5 10.5a7.464 7.464 0 0 1-1.15 3.993m1.989 3.559A11.209 11.209 0 0 0 8.25 10.5a3.75 3.75 0 1 1 7.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 0 1-3.6 9.75"
            />
        ),
    },
    {
        title: 'Roles & Settings',
        description:
            'Company rules — lunch breaks, working days, windows — configurable by HR, not by code.',
        accent: 'bg-[#271A3D]/10 text-[#271A3D]',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"
            />
        ),
    },
];

const marqueeItems = [
    '⏱️ One-click clock in',
    '🗓️ Leave in seconds',
    '📊 Live HR insights',
    '✨ Built-in recognition',
    '🖐️ Biometric import',
    '🏖️ Team calendar',
    '🏅 Praise cycles',
    '⚙️ HR-owned settings',
];

const insightBullets = [
    'Live headcount, absences, and new hires',
    'Approvals waiting more than 48 hours, flagged automatically',
    'Leave trends and overtime by department',
];

const everydayBullets = [
    'Clock in and out in one click, from anywhere',
    'Leave credits, requests, and overtime — always current',
    "Praise you've received and what's coming up next",
];

function CheckIcon({ className }: { className: string }) {
    return (
        <span
            className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full ${className}`}
        >
            <svg
                viewBox="0 0 20 20"
                fill="currentColor"
                className="h-3.5 w-3.5"
            >
                <path
                    fillRule="evenodd"
                    d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                    clipRule="evenodd"
                />
            </svg>
        </span>
    );
}

export default function Welcome() {
    const { auth } = usePage().props;
    const year = new Date().getFullYear();
    const appUrl = auth.user ? '/admin' : '/admin/login';
    const scope = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const ctx = gsap.context(() => {
            // Hero entrance.
            gsap.timeline({ defaults: { ease: 'power3.out' } })
                .from('[data-hero-badge]', {
                    y: 24,
                    autoAlpha: 0,
                    duration: 0.6,
                })
                .from(
                    '[data-hero-title]',
                    { y: 36, autoAlpha: 0, duration: 0.8 },
                    '-=0.3',
                )
                .from(
                    '[data-hero-sub]',
                    { y: 24, autoAlpha: 0, duration: 0.6 },
                    '-=0.45',
                )
                .from(
                    '[data-hero-cta]',
                    { y: 18, autoAlpha: 0, duration: 0.5 },
                    '-=0.35',
                )
                .from(
                    '[data-hero-shot]',
                    { y: 90, autoAlpha: 0, scale: 0.95, duration: 1 },
                    '-=0.3',
                );

            // Slow-drifting gradient blobs.
            gsap.to('[data-blob]', {
                y: 36,
                x: 24,
                repeat: -1,
                yoyo: true,
                duration: 7,
                ease: 'sine.inOut',
                stagger: 1.4,
            });

            // Endless marquee of feature pills.
            gsap.to('[data-marquee]', {
                xPercent: -50,
                repeat: -1,
                duration: 24,
                ease: 'none',
            });

            // Generic scroll reveals.
            gsap.utils.toArray<HTMLElement>('[data-reveal]').forEach((el) => {
                gsap.from(el, {
                    y: 56,
                    autoAlpha: 0,
                    duration: 0.9,
                    ease: 'power3.out',
                    scrollTrigger: {
                        trigger: el,
                        start: 'top 90%',
                        once: true,
                    },
                });
            });

            // Screenshots drift gently against the scroll.
            gsap.utils.toArray<HTMLElement>('[data-parallax]').forEach((el) => {
                gsap.to(el, {
                    y: -48,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: el,
                        start: 'top bottom',
                        end: 'bottom top',
                        scrub: 1,
                    },
                });
            });
        }, scope);

        // The large screenshots finish loading after ScrollTrigger has measured
        // the page, which leaves every trigger at a stale position (sections
        // then sit hidden forever). Re-measure once the window and each image
        // have loaded.
        const refresh = () => ScrollTrigger.refresh();
        window.addEventListener('load', refresh);

        const images = Array.from(scope.current?.querySelectorAll('img') ?? []);
        images.forEach((img) => {
            if (!img.complete) {
                img.addEventListener('load', refresh, { once: true });
            }
        });

        return () => {
            window.removeEventListener('load', refresh);
            images.forEach((img) => img.removeEventListener('load', refresh));
            ctx.revert();
        };
    }, []);

    return (
        <>
            <Head title="Digitalfeet.HR — People-first HR, in one calm place" />

            <div
                ref={scope}
                className="min-h-screen scroll-smooth bg-white font-sans text-[#271A3D]"
            >
                {/* ───────────────────────── Nav (white) ───────────────────────── */}
                <header className="sticky top-0 z-40 border-b border-[#271A3D]/8 bg-white/85 backdrop-blur">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-6 px-6 py-4">
                        <a
                            href="#top"
                            className="font-serif text-xl font-bold tracking-tight"
                        >
                            Digitalfeet
                            <span className="text-[#F99F29]">.HR</span>
                        </a>

                        <nav className="hidden items-center gap-8 text-sm font-medium text-[#271A3D]/70 md:flex">
                            <a
                                href="#insights"
                                className="transition hover:text-[#271A3D]"
                            >
                                Insights
                            </a>
                            <a
                                href="#everyday"
                                className="transition hover:text-[#271A3D]"
                            >
                                Everyday
                            </a>
                            <a
                                href="#features"
                                className="transition hover:text-[#271A3D]"
                            >
                                Features
                            </a>
                        </nav>

                        <a
                            href={appUrl}
                            className="inline-flex items-center gap-2 rounded-xl bg-[#271A3D] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#271A3D]/90"
                        >
                            {auth.user ? 'Open app' : 'Log in'}
                            <span aria-hidden>→</span>
                        </a>
                    </div>
                </header>

                <main id="top">
                    {/* ─────────────────── 1. Hero (purple) ─────────────────── */}
                    <section className="relative overflow-hidden bg-[#271A3D] text-white">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-0"
                        >
                            <div
                                data-blob
                                className="absolute -top-40 -left-32 h-96 w-96 rounded-full bg-[#099FDD]/20 blur-3xl"
                            />
                            <div
                                data-blob
                                className="absolute top-24 -right-32 h-96 w-96 rounded-full bg-[#F99F29]/20 blur-3xl"
                            />
                            <div
                                data-blob
                                className="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-[#D60B52]/15 blur-3xl"
                            />
                        </div>

                        <div className="relative mx-auto flex w-full max-w-6xl flex-col items-center px-6 pt-20 pb-24 text-center sm:pt-28">
                            <span
                                data-hero-badge
                                className="inline-flex items-center gap-2 rounded-full border border-[#F99F29]/40 bg-[#F99F29]/15 px-4 py-1.5 text-xs font-semibold text-[#F99F29]"
                            >
                                🌱 People-first HR for Digital Feet
                            </span>

                            <h1
                                data-hero-title
                                className="mt-6 max-w-3xl text-4xl font-extrabold tracking-tight sm:text-6xl"
                            >
                                HR that walks
                                <span className="block bg-gradient-to-r from-[#F99F29] to-[#099FDD] bg-clip-text text-transparent">
                                    in step with your team.
                                </span>
                            </h1>

                            <p
                                data-hero-sub
                                className="mt-5 max-w-xl text-base text-white/70 sm:text-lg"
                            >
                                Attendance, leave, overtime, and recognition —
                                everything your people do in a day, in one calm
                                place. No spreadsheets, no chasing.
                            </p>

                            <div
                                data-hero-cta
                                className="mt-8 flex flex-wrap items-center justify-center gap-3"
                            >
                                <a
                                    href={appUrl}
                                    className="inline-flex items-center gap-2 rounded-xl bg-[#F99F29] px-7 py-3.5 text-sm font-bold text-[#271A3D] shadow-lg shadow-black/25 transition hover:-translate-y-0.5 hover:bg-[#F99F29]/90"
                                >
                                    {auth.user
                                        ? 'Open the app'
                                        : 'Log in to continue'}
                                    <span aria-hidden>→</span>
                                </a>
                                <a
                                    href="#insights"
                                    className="inline-flex items-center gap-2 rounded-xl border border-white/25 px-7 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10"
                                >
                                    See what's inside
                                </a>
                            </div>

                            <div
                                data-hero-shot
                                className="relative mt-16 w-full"
                            >
                                <div
                                    aria-hidden
                                    className="absolute inset-x-8 -top-6 h-full rounded-3xl bg-gradient-to-r from-[#F99F29]/30 via-[#D60B52]/20 to-[#099FDD]/30 blur-2xl"
                                />
                                <div className="relative mx-auto max-w-5xl overflow-hidden rounded-2xl border border-white/15 bg-white shadow-2xl shadow-black/40">
                                    <div className="flex items-center gap-1.5 border-b border-[#271A3D]/8 bg-[#F5F3F9] px-4 py-3">
                                        <span className="h-2.5 w-2.5 rounded-full bg-[#E94E1B]/60" />
                                        <span className="h-2.5 w-2.5 rounded-full bg-[#F99F29]/70" />
                                        <span className="h-2.5 w-2.5 rounded-full bg-[#238B7F]/60" />
                                    </div>
                                    <img
                                        src="/images/home/ss2.png"
                                        alt="The Digitalfeet.HR employee dashboard"
                                        className="w-full"
                                        width={1901}
                                        height={941}
                                        loading="eager"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* ─────────────── 2. Insights (white) ─────────────── */}
                    <section
                        id="insights"
                        className="scroll-mt-20 overflow-hidden bg-white"
                    >
                        {/* Marquee strip */}
                        <div className="border-b border-[#271A3D]/8 py-5">
                            <div
                                data-marquee
                                className="flex w-max gap-10 text-sm font-semibold whitespace-nowrap text-[#271A3D]/60"
                            >
                                {[...marqueeItems, ...marqueeItems].map(
                                    (item, index) => (
                                        <span key={`${item}-${index}`}>
                                            {item}
                                        </span>
                                    ),
                                )}
                            </div>
                        </div>

                        <div className="mx-auto grid w-full max-w-6xl items-center gap-10 px-6 py-20 lg:grid-cols-2 lg:gap-16 lg:py-28">
                            <div data-reveal>
                                <span className="text-xs font-bold tracking-widest text-[#099FDD] uppercase">
                                    For HR & managers
                                </span>
                                <h2 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">
                                    See the whole company at a glance
                                </h2>
                                <p className="mt-4 text-base text-[#271A3D]/70">
                                    The HR Overview turns the day-to-day into
                                    decisions: who is in, what is pending, and
                                    where the hours go.
                                </p>

                                <ul className="mt-6 space-y-3">
                                    {insightBullets.map((bullet) => (
                                        <li
                                            key={bullet}
                                            className="flex items-start gap-3 text-sm font-medium"
                                        >
                                            <CheckIcon className="bg-[#238B7F]/15 text-[#238B7F]" />
                                            {bullet}
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div data-reveal>
                                <div
                                    data-parallax
                                    className="overflow-hidden rounded-2xl border border-[#271A3D]/10 shadow-xl shadow-[#271A3D]/15"
                                >
                                    <img
                                        src="/images/home/ss1.png"
                                        alt="HR Overview dashboard with stats, charts, and pending approvals"
                                        className="w-full"
                                        width={1898}
                                        height={941}
                                        loading="lazy"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* ─────────────── 3. Everyday (purple) ─────────────── */}
                    <section
                        id="everyday"
                        className="relative scroll-mt-20 overflow-hidden bg-[#271A3D] text-white"
                    >
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-0"
                        >
                            <div
                                data-blob
                                className="absolute -top-24 right-0 h-80 w-80 rounded-full bg-[#099FDD]/15 blur-3xl"
                            />
                            <div
                                data-blob
                                className="absolute bottom-0 -left-24 h-80 w-80 rounded-full bg-[#F99F29]/15 blur-3xl"
                            />
                        </div>

                        <div className="relative mx-auto grid w-full max-w-6xl items-center gap-10 px-6 py-20 lg:grid-cols-2 lg:gap-16 lg:py-28">
                            <div data-reveal className="lg:order-2">
                                <span className="text-xs font-bold tracking-widest text-[#F99F29] uppercase">
                                    For every employee
                                </span>
                                <h2 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">
                                    Your day, organized from the first clock-in
                                </h2>
                                <p className="mt-4 text-base text-white/70">
                                    One home for your time, your requests, and
                                    your team — no spreadsheets, no asking
                                    around.
                                </p>

                                <ul className="mt-6 space-y-3">
                                    {everydayBullets.map((bullet) => (
                                        <li
                                            key={bullet}
                                            className="flex items-start gap-3 text-sm font-medium"
                                        >
                                            <CheckIcon className="bg-[#F99F29]/20 text-[#F99F29]" />
                                            {bullet}
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div data-reveal className="lg:order-1">
                                <div
                                    data-parallax
                                    className="overflow-hidden rounded-2xl border border-white/15 shadow-2xl shadow-black/40"
                                >
                                    <img
                                        src="/images/home/ss2.png"
                                        alt="Employee dashboard with time tracking, leave credits, and quick links"
                                        className="w-full"
                                        width={1901}
                                        height={941}
                                        loading="lazy"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* ─────────────── 4. Feature grid (white) ─────────────── */}
                    <section id="features" className="scroll-mt-20 bg-white">
                        <div className="mx-auto w-full max-w-6xl px-6 py-20 lg:py-28">
                            <div
                                data-reveal
                                className="mx-auto max-w-2xl text-center"
                            >
                                <span className="text-xs font-bold tracking-widest text-[#099FDD] uppercase">
                                    All in one place
                                </span>
                                <h2 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">
                                    Everything a workday needs
                                </h2>
                                <p className="mt-4 text-base text-[#271A3D]/70">
                                    Six tools, one login — built around how your
                                    team actually works.
                                </p>
                            </div>

                            {/* No scroll reveal here — the cards stay always visible. */}
                            <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                {features.map((feature) => (
                                    <div
                                        key={feature.title}
                                        className="group rounded-2xl border border-[#271A3D]/10 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-[#271A3D]/10"
                                    >
                                        <span
                                            className={`flex h-11 w-11 items-center justify-center rounded-xl transition group-hover:scale-110 ${feature.accent}`}
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                strokeWidth={1.7}
                                                stroke="currentColor"
                                                className="h-6 w-6"
                                            >
                                                {feature.icon}
                                            </svg>
                                        </span>
                                        <h3 className="mt-4 text-base font-bold">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-1.5 text-sm text-[#271A3D]/60">
                                            {feature.description}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* ─────────────── 5. Final CTA (purple) ─────────────── */}
                    <section className="relative overflow-hidden bg-[#271A3D] text-white">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-0"
                        >
                            <div
                                data-blob
                                className="absolute -top-32 left-1/4 h-80 w-80 rounded-full bg-[#F99F29]/15 blur-3xl"
                            />
                            <div
                                data-blob
                                className="absolute -right-24 bottom-0 h-80 w-80 rounded-full bg-[#099FDD]/15 blur-3xl"
                            />
                        </div>

                        <div
                            data-reveal
                            className="relative mx-auto flex w-full max-w-6xl flex-col items-center gap-6 px-6 py-20 text-center lg:py-24"
                        >
                            <h2 className="max-w-2xl text-3xl font-extrabold tracking-tight sm:text-4xl">
                                Let's get started on the right foot. 👣
                            </h2>
                            <p className="max-w-xl text-base text-white/70">
                                Your attendance, leave, and recognition are
                                already waiting inside.
                            </p>
                            <a
                                href={appUrl}
                                className="inline-flex items-center gap-2 rounded-xl bg-[#F99F29] px-8 py-4 text-sm font-bold text-[#271A3D] shadow-lg shadow-black/25 transition hover:-translate-y-0.5 hover:bg-[#F99F29]/90"
                            >
                                {auth.user
                                    ? 'Open the app'
                                    : 'Log in to your workspace'}
                                <span aria-hidden>→</span>
                            </a>
                        </div>
                    </section>
                </main>

                {/* ─────────────── Footer (white) ─────────────── */}
                <footer className="border-t border-[#271A3D]/8 bg-white">
                    <div className="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-6 py-10 sm:flex-row">
                        <span className="font-serif text-lg font-bold tracking-tight">
                            Digitalfeet
                            <span className="text-[#F99F29]">.HR</span>
                        </span>

                        <nav className="flex items-center gap-6 text-sm text-[#271A3D]/60">
                            <a
                                href="#insights"
                                className="transition hover:text-[#271A3D]"
                            >
                                Insights
                            </a>
                            <a
                                href="#everyday"
                                className="transition hover:text-[#271A3D]"
                            >
                                Everyday
                            </a>
                            <a
                                href="#features"
                                className="transition hover:text-[#271A3D]"
                            >
                                Features
                            </a>
                            <a
                                href={appUrl}
                                className="transition hover:text-[#271A3D]"
                            >
                                Log in
                            </a>
                        </nav>

                        <p className="text-xs text-[#271A3D]/50">
                            © {year} Digitalfeet.HR — All rights reserved.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
