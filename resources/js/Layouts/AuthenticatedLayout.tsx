import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'teacher' | 'student';
}

interface NavItem {
    label: string;
    routeName: string;
    routePattern: string;
}

const navItems: Record<User['role'], NavItem[]> = {
    admin: [
        {
            label: 'Dashboard',
            routeName: 'admin.dashboard',
            routePattern: 'admin/dashboard',
        },
        {
            label: 'Academic Sessions',
            routeName: 'admin.academic-sessions.index',
            routePattern: 'admin/academic-sessions*',
        },
        {
            label: 'Student Batches',
            routeName: 'admin.student-batches.index',
            routePattern: 'admin/student-batches*',
        },
        {
            label: 'Periods',
            routeName: 'admin.periods.index',
            routePattern: 'admin/periods*',
        },
        {
            label: 'Streams',
            routeName: 'admin.streams.index',
            routePattern: 'admin/streams*',
        },
        {
            label: 'Subjects',
            routeName: 'admin.subjects.index',
            routePattern: 'admin/subjects*',
        },
        {
            label: 'Classes',
            routeName: 'admin.classes.index',
            routePattern: 'admin/classes*',
        },
        {
            label: 'Teachers',
            routeName: 'admin.teachers.index',
            routePattern: 'admin/teachers*',
        },
        {
            label: 'Students',
            routeName: 'admin.students.index',
            routePattern: 'admin/students*',
        },
        {
            label: 'Attendance',
            routeName: 'admin.attendance.index',
            routePattern: 'admin/attendance*',
        },
        {
            label: 'Defaulters',
            routeName: 'admin.attendance.defaulters',
            routePattern: 'admin/attendance-defaulters*',
        },
        {
            label: 'Admissions',
            routeName: 'admin.admissions.index',
            routePattern: 'admin/admissions*',
        },
    ],
    teacher: [
        {
            label: 'Dashboard',
            routeName: 'teacher.dashboard',
            routePattern: 'teacher/dashboard',
        },
        {
            label: 'Timetable',
            routeName: 'teacher.timetable',
            routePattern: 'teacher/timetable',
        },
        {
            label: 'Attendance',
            routeName: 'teacher.attendance.index',
            routePattern: 'teacher/attendance*',
        },
    ],
    student: [
        {
            label: 'Dashboard',
            routeName: 'student.dashboard',
            routePattern: 'student/dashboard',
        },
        {
            label: 'My Profile',
            routeName: 'student.profile',
            routePattern: 'student/profile',
        },
        {
            label: 'Timetable',
            routeName: 'student.timetable',
            routePattern: 'student/timetable',
        },
        {
            label: 'Attendance',
            routeName: 'student.attendance',
            routePattern: 'student/attendance',
        },
    ],
};

export default function AuthenticatedLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user as User;
    const currentRoute = route().current() ?? '';

    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);

    const items = navItems[user.role] ?? [];

    const isActive = (pattern: string) => {
        return route().current(pattern) || currentRoute.startsWith(pattern.replace('*', ''));
    };

    return (
        <div className="flex h-screen flex-col overflow-hidden bg-surface">
            {/* Top header — held in place by the flex column, not by sticky */}
            <header className="z-30 shrink-0 border-b border-gray-200 bg-navy text-white">
                <div className="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen((v) => !v)}
                            className="rounded-md p-2 hover:bg-navy-dark focus:outline-none lg:hidden"
                            aria-label="Toggle navigation"
                        >
                            <svg
                                className="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>

                        <Link href="/" className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-md border-2 border-gold bg-navy-dark">
                                <span className="font-serif text-lg font-bold text-gold">
                                    GC
                                </span>
                            </div>
                            <div className="hidden sm:block">
                                <div className="font-serif text-base font-semibold leading-tight">
                                    Government College Chiniot
                                </div>
                                <div className="text-xs text-gold-light">
                                    Learning Management System
                                </div>
                            </div>
                        </Link>
                    </div>

                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => setUserMenuOpen((v) => !v)}
                            className="flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-navy-dark focus:outline-none"
                        >
                            <span className="hidden sm:inline">{user.name}</span>
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gold text-sm font-semibold text-navy">
                                {user.name.charAt(0).toUpperCase()}
                            </span>
                            <svg
                                className="hidden h-4 w-4 sm:block"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fillRule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clipRule="evenodd"
                                />
                            </svg>
                        </button>

                        {userMenuOpen && (
                            <>
                                <div
                                    className="fixed inset-0 z-10"
                                    onClick={() => setUserMenuOpen(false)}
                                />
                                <div className="absolute right-0 z-20 mt-2 w-56 rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                    <div className="border-b border-gray-100 px-4 py-2">
                                        <p className="text-sm font-medium text-gray-900">
                                            {user.name}
                                        </p>
                                        <p className="truncate text-xs text-gray-500">
                                            {user.email}
                                        </p>
                                    </div>
                                    <Link
                                        href={route('profile.edit')}
                                        className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        onClick={() => setUserMenuOpen(false)}
                                    >
                                        Profile
                                    </Link>
                                    <Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                        className="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                    >
                                        Log Out
                                    </Link>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <div className="flex flex-1 overflow-hidden">
                {/* Sidebar — desktop. The flex row pins it beside the content,
                    so it needs no offset and scrolls only if it overflows. */}
                <aside className="hidden w-64 shrink-0 overflow-y-auto border-r border-gray-200 bg-white lg:block">
                    <nav className="space-y-1 px-3 py-4">
                        {items.map((item) => (
                            <Link
                                key={item.routeName}
                                href={route(item.routeName)}
                                className={
                                    'block rounded-md border-l-4 px-4 py-2 text-sm font-medium transition ' +
                                    (isActive(item.routePattern)
                                        ? 'border-gold bg-surface text-navy'
                                        : 'border-transparent text-gray-700 hover:bg-surface hover:text-navy')
                                }
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                </aside>

                {/* Sidebar — mobile drawer */}
                {sidebarOpen && (
                    <>
                        <div
                            className="fixed inset-0 z-30 bg-black/40 lg:hidden"
                            onClick={() => setSidebarOpen(false)}
                        />
                        <aside className="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-gray-200 bg-white lg:hidden">
                            <div className="flex h-16 items-center justify-between border-b border-gray-200 px-4">
                                <span className="font-serif text-sm font-semibold text-navy">
                                    Menu
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setSidebarOpen(false)}
                                    className="rounded-md p-1 text-gray-500 hover:bg-gray-100"
                                    aria-label="Close menu"
                                >
                                    <svg
                                        className="h-6 w-6"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                                {items.map((item) => (
                                    <Link
                                        key={item.routeName}
                                        href={route(item.routeName)}
                                        className={
                                            'block rounded-md border-l-4 px-4 py-2 text-sm font-medium transition ' +
                                            (isActive(item.routePattern)
                                                ? 'border-gold bg-surface text-navy'
                                                : 'border-transparent text-gray-700 hover:bg-surface hover:text-navy')
                                        }
                                        onClick={() => setSidebarOpen(false)}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>
                        </aside>
                    </>
                )}

                {/* Main content */}
                <main className="flex min-w-0 flex-1 flex-col overflow-hidden">
                    {header && (
                        <div className="shrink-0 border-b border-gray-200 bg-white">
                            <div className="flex min-h-[4rem] items-center px-4 py-3 sm:px-6 lg:px-8">
                                <div className="w-full">{header}</div>
                            </div>
                        </div>
                    )}

                    {/* The only scrolling region on the page: the header, page
                        header and sidebar all sit outside it.

                        The inner wrapper is h-full, not min-h-full, and that
                        distinction is what makes the table grid work. A
                        min-height leaves the height property "auto", and a
                        percentage height against an auto-height parent
                        resolves to auto as well. An Index page's body is
                        h-full min-h-0, so with min-h-full the whole chain
                        collapsed to auto: the table card grew to the full
                        height of its table and this wrapper scrolled the page
                        instead. A definite height bounds the table card, which
                        leaves its inner overflow-auto the only scroll region.

                        Content taller than the viewport - the Create, Edit and
                        Show pages - still scrolls here, because an overflowing
                        child extends this element's scrollable overflow. */}
                    <div className="flex-1 overflow-y-auto overflow-x-hidden">
                        <div className="flex h-full flex-col px-4 py-6 sm:px-6 lg:px-8">
                            {children}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
