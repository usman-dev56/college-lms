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
                            <div className="flex h-12 w-12 items-center justify-center rounded-md border-2 border-gold bg-navy-dark">
                                <span className="font-serif text-xl font-bold text-gold">
                                    GC
                                </span>
                            </div>
                            <span className="text-sm uppercase tracking-widest text-gold-light">
                                Est. Punjab, Pakistan
                            </span>
                        </div>
                    </div>

                    <div className="mt-10 lg:mt-0">
                        <h1 className="font-serif text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">
                            Government College
                            <br />
                            <span className="text-gold">Chiniot</span>
                        </h1>
                        <div className="mt-4 h-1 w-24 bg-gold" />
                        <p className="mt-6 max-w-md text-base text-gray-200 lg:text-lg">
                            Intermediate College
                        </p>
                        <p className="mt-2 max-w-md text-sm text-gray-300">
                            Learning Management System for students, teachers,
                            and administration.
                        </p>
                    </div>

                    <div className="mt-10 hidden text-xs text-gray-400 lg:block">
                        &copy; {new Date().getFullYear()} Government College
                        Chiniot. All rights reserved.
                    </div>
                </div>

                {/* Right panel: form */}
                <div className="flex flex-1 items-center justify-center px-6 py-12 lg:px-12">
                    <div className="w-full max-w-md">
                        <div className="mb-8 lg:hidden">
                            <h1 className="font-serif text-2xl font-bold text-navy">
                                Government College Chiniot
                            </h1>
                            <div className="mt-2 h-1 w-16 bg-gold" />
                            <p className="mt-3 text-sm text-gray-600">
                                Intermediate College
                            </p>
                        </div>

                        <div className="rounded-lg bg-surface-card p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                            {children}
                        </div>

                        <p className="mt-6 text-center text-xs text-gray-500">
                            &copy; {new Date().getFullYear()} Government College
                            Chiniot
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}