import CollegeLogo from '@/Components/CollegeLogo';
import { BRANDING } from '@/branding';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

/** Anchor targets for the nav. Each section carries scroll-mt to clear the sticky header. */
const NAV_LINKS = [
    { label: 'Home', href: '#home' },
    { label: 'About', href: '#about' },
    { label: 'Programs', href: '#programs' },
    { label: 'Contact', href: '#contact' },
] as const;

const PROGRAMS = [
    {
        title: 'Pre-Medical',
        description: 'Biology, Physics, Chemistry — for future doctors',
        icon: 'M4.5 3.75h15M9 3.75v3m6-3v3M6 6.75h12v13.5H6zM12 10.5v6m-3-3h6',
    },
    {
        title: 'Pre-Engineering',
        description: 'Physics, Chemistry, Mathematics — for future engineers',
        icon: 'M14.7 6.3a4 4 0 01-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 015.4-5.4l-2.6 2.6',
    },
    {
        title: 'ICS',
        description: 'Computer Science, Physics, Mathematics — for future developers',
        icon: 'M9 17.25L4.5 12 9 6.75M15 6.75L19.5 12 15 17.25',
    },
    {
        title: 'Commerce',
        description: 'Accounting, Economics, Commerce — for future business leaders',
        icon: 'M3.75 20.25h16.5M5.25 20.25V9.75m4.5 10.5V9.75m5 10.5V9.75m4.5 10.5V9.75M3 9.75h18L12 3.75 3 9.75z',
    },
    {
        title: 'Humanities',
        description: 'Languages, Social Sciences, Arts — for future thinkers',
        icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
    },
] as const;

const FEATURES = [
    {
        title: 'Experienced Faculty',
        description: 'Board-qualified teachers with decades of experience',
        icon: 'M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 007.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5',
    },
    {
        title: 'Modern Labs',
        description: 'Fully equipped science and computer labs',
        icon: 'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0-.597.237-1.17.659-1.591L19.8 14.5M14.25 3.104c.251.023.501.05.75.082M19.8 14.5l-1.57.393A9.065 9.065 0 0112 15.393a9.065 9.065 0 00-6.23-.693L5 14.5m14.8 0l.474 1.386a4.5 4.5 0 00-1.036 3.159M9.75 21h4.5',
    },
    {
        title: 'Board Excellence',
        description: 'Consistent top results in BISE examinations',
        icon: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0',
    },
    {
        title: 'Digital Learning',
        description: 'Smart classrooms and digital resources',
        icon: 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21l.621-.621A3 3 0 016 18.75V17.25m6-12V15a3 3 0 01-3 3m0 0V5.25M15 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
    },
] as const;

const STATS = [
    { value: '1200+', label: 'Students' },
    { value: '45+', label: 'Faculty' },
    { value: '5', label: 'Programs' },
    { value: '98%', label: 'Success Rate' },
] as const;

/** One stroked outline icon, so every card's glyph reads as the same set. */
function LineIcon({ d, className }: { d: string; className: string }) {
    return (
        <svg
            className={className}
            fill="none"
            stroke="currentColor"
            strokeWidth={1.5}
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d={d} />
        </svg>
    );
}

/**
 * The public marketing page.
 *
 * Deliberately self-contained: it uses neither GuestLayout nor
 * AuthenticatedLayout, because those are app chrome for people who already
 * have an account. This page has to sell the college to someone who does not.
 */
export default function Landing() {
    // Ziggy always knows about admissions.apply in this app, but the fallback
    // keeps a future route rename from turning the marketing page into a 500.
    const applyUrl = (() => {
        try {
            return route('admissions.apply');
        } catch {
            return route('login');
        }
    })();

    return (
        <>
            <Head title={`${BRANDING.name} — ${BRANDING.tagline}`} />

            {/*
                Anchor targets need to clear the sticky nav, and the scroll has
                to be smooth. Scoped to the wrapper rather than :root so the
                effect never leaks into the authenticated app screens, and
                disabled for visitors who ask for reduced motion.
            */}
            <style>{`
                .landing-root { scroll-behavior: smooth; }
                @media (prefers-reduced-motion: reduce) {
                    .landing-root { scroll-behavior: auto; }
                }
            `}</style>

            <div className="landing-root bg-white">
                {/* 1. Sticky navigation */}
                <header className="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur">
                    <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                        <a href="#home" className="flex items-center gap-3">
                            <CollegeLogo variant="icon" size={40} />
                            <span className="font-serif text-lg font-bold text-navy">
                                {BRANDING.name}
                            </span>
                        </a>

                        <nav className="hidden items-center gap-8 md:flex">
                            {NAV_LINKS.map((link) => (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    className="text-sm font-medium text-gray-700 transition hover:text-navy"
                                >
                                    {link.label}
                                </a>
                            ))}
                        </nav>

                        <Link
                            href={route('login')}
                            className="rounded-md bg-navy px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-navy-light"
                        >
                            Login
                        </Link>
                    </div>
                </header>

                {/* 2. Hero */}
                <section
                    id="home"
                    className="scroll-mt-24 bg-gradient-to-br from-navy to-navy-light"
                >
                    <div className="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6 lg:px-8 lg:py-24">
                        <h1 className="font-serif text-4xl font-bold leading-tight text-white sm:text-5xl lg:text-6xl">
                            {BRANDING.name}
                        </h1>
                        <div className="mx-auto mt-6 h-1 w-24 bg-gold" />
                        <p className="mt-6 font-serif text-xl text-gold sm:text-2xl">
                            {BRANDING.tagline}
                        </p>
                        <p className="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-gray-200 sm:text-lg">
                            Intermediate Programs for Grades 11 &amp; 12 --- offering
                            Pre-Medical, Pre-Engineering, ICS, Commerce, and
                            Humanities.
                        </p>
                        <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                            <Link
                                href={route('login')}
                                className="w-full rounded-md bg-gold px-6 py-3 text-base font-semibold text-navy shadow-sm transition hover:bg-gold-light sm:w-auto"
                            >
                                 Login to access the LMS Portal
                            </Link>
                            <Link
                                href={applyUrl}
                                className="w-full rounded-md border border-white px-6 py-3 text-base font-semibold text-white transition hover:bg-white/10 sm:w-auto"
                            >
                                Apply for Admission
                            </Link>
                        </div>
                    </div>
                </section>

                {/* 3. Programs */}
                <section
                    id="programs"
                    className="scroll-mt-24 bg-white py-16 lg:py-20"
                >
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <h2 className="text-center font-serif text-3xl font-bold text-navy">
                            Our Programs
                        </h2>
                        <div className="mx-auto mt-4 h-1 w-16 bg-gold" />
                        <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {PROGRAMS.map((program) => (
                                <div
                                    key={program.title}
                                    className="rounded-lg border border-gray-200 bg-white p-6 ring-1 ring-gray-100 transition hover:-translate-y-1 hover:shadow-lg hover:ring-gold/30"
                                >
                                    <LineIcon
                                        d={program.icon}
                                        className="h-7 w-7 text-gold"
                                    />
                                    <h3 className="mt-4 font-serif text-lg font-bold text-navy">
                                        {program.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed text-gray-600">
                                        {program.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* 4. Why choose us */}
                <section
                    id="about"
                    className="scroll-mt-24 bg-surface py-16 lg:py-20"
                >
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <h2 className="text-center font-serif text-3xl font-bold text-navy">
                            Why Choose Us
                        </h2>
                        <div className="mx-auto mt-4 h-1 w-16 bg-gold" />
                        <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {FEATURES.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                                >
                                    <LineIcon
                                        d={feature.icon}
                                        className="h-7 w-7 text-navy"
                                    />
                                    <h3 className="mt-4 text-base font-semibold text-navy">
                                        {feature.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed text-gray-600">
                                        {feature.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* 5. Stats */}
                <section className="bg-navy py-14">
                    <div className="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 sm:px-6 lg:grid-cols-4 lg:px-8">
                        {STATS.map((stat) => (
                            <div key={stat.label} className="text-center">
                                <div className="font-serif text-3xl font-bold text-gold sm:text-4xl">
                                    {stat.value}
                                </div>
                                <div className="mt-2 text-sm uppercase tracking-wider text-gray-300">
                                    {stat.label}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {/* 6. Admissions call to action */}
                <section className="bg-gold py-16 text-center">
                    <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                        <h2 className="font-serif text-3xl font-bold text-navy">
                            Admissions Open for 2026-2028
                        </h2>
                        <p className="mt-3 text-base text-navy-dark">
                            Apply now to secure your seat.
                        </p>
                        <Link
                            href={applyUrl}
                            className="mt-8 inline-block rounded-md bg-navy px-8 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-navy-dark"
                        >
                            Apply Now
                        </Link>
                    </div>
                </section>
{/* 7. Footer */}
                <footer
                    id="contact"
                    className="scroll-mt-24 bg-navy-dark text-gray-300"
                >
                    <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 gap-10 md:grid-cols-3">
                            <div>
                                <div className="flex items-center gap-3">
                                    <CollegeLogo variant="icon" size={40} />
                                    <span className="font-serif text-lg font-bold text-white">
                                        {BRANDING.name}
                                    </span>
                                </div>
                                <p className="mt-4 font-serif text-sm text-gold">
                                    {BRANDING.tagline}
                                </p>
                            </div>

                            <div>
                                <h3 className="text-sm font-semibold uppercase tracking-wider text-white">
                                    Contact
                                </h3>
                                <address className="mt-4 space-y-2 text-sm not-italic">
                                    <p>{BRANDING.address}</p>
                                    <p>
                                        <a
                                            href={`tel:${BRANDING.phone.replace(/\s/g, '')}`}
                                            className="transition hover:text-gold"
                                        >
                                            {BRANDING.phone}
                                        </a>
                                    </p>
                                    <p>
                                        <a
                                            href={`mailto:${BRANDING.email}`}
                                            className="transition hover:text-gold"
                                        >
                                            {BRANDING.email}
                                        </a>
                                    </p>
                                </address>
                            </div>

                            <div>
                                <h3 className="text-sm font-semibold uppercase tracking-wider text-white">
                                    Quick Links
                                </h3>
                                <ul className="mt-4 space-y-2 text-sm">
                                    <li>
                                        <Link
                                            href={route('login')}
                                            className="transition hover:text-gold"
                                        >
                                            Login
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={applyUrl}
                                            className="transition hover:text-gold"
                                        >
                                            Apply for Admission
                                        </Link>
                                    </li>
                                    <li>
                                        <a
                                            href={`mailto:${BRANDING.email}`}
                                            className="transition hover:text-gold"
                                        >
                                            Contact
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div className="mt-10 border-t border-white/10 pt-6 text-center text-xs text-gray-400">
                            &copy; {BRANDING.established} {BRANDING.name}. All
                            rights reserved.
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}