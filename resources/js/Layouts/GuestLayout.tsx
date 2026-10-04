import CollegeLogo from '@/Components/CollegeLogo';
import { BRANDING } from '@/branding';
import { Head, Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

interface GuestLayoutProps extends PropsWithChildren {
    title?: string;
}

export default function GuestLayout({ children, title }: GuestLayoutProps) {
    return (
        <>
            <Head title={title} />

            <div className="flex min-h-screen flex-col bg-surface lg:flex-row">
                {/* Left panel: branding */}
                <div className="relative flex flex-col justify-between bg-navy px-8 py-10 text-white lg:w-3/5 lg:px-16 lg:py-16">
                    <div>
                        <div className="flex items-center gap-3">
                            <CollegeLogo variant="icon" size={48} />
                            <span className="text-sm uppercase tracking-widest text-gold-light">
                                Est. {BRANDING.established}
                            </span>
                        </div>
                    </div>

                    <div className="mt-10 lg:mt-0">
                        <h1 className="font-serif text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                            {BRANDING.name}
                        </h1>
                        <div className="mt-4 h-1 w-24 bg-gold" />
                        <p className="mt-6 max-w-md font-serif text-lg text-gold lg:text-xl">
                            {BRANDING.tagline}
                        </p>
                        <p className="mt-4 max-w-md text-sm text-gray-300">
                            Learning Management System for students, teachers,
                            and administration.
                        </p>
                    </div>

                    <div className="mt-10 hidden text-xs text-gray-400 lg:block">
                        &copy; {new Date().getFullYear()} {BRANDING.name}. All
                        rights reserved.
                    </div>
                </div>

                {/* Right panel: form */}
                <div className="flex flex-1 items-center justify-center px-6 py-12 lg:px-12">
                    <div className="w-full max-w-md">
                        <div className="mb-8 flex items-center gap-3 lg:hidden">
                            <CollegeLogo variant="icon" size={44} />
                            <div>
                                <h1 className="font-serif text-xl font-bold text-navy">
                                    {BRANDING.name}
                                </h1>
                                <p className="mt-0.5 font-serif text-xs text-gold-dark">
                                    {BRANDING.tagline}
                                </p>
                            </div>
                        </div>

                        <div className="rounded-lg bg-surface-card p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                            {children}
                        </div>

                        <p className="mt-6 text-center text-xs text-gray-500">
                            &copy; {new Date().getFullYear()} {BRANDING.name}
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}