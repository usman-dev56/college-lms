import CollegeLogo from '@/Components/CollegeLogo';
import { BRANDING } from '@/branding';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useEffect, useRef, useState } from 'react';

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
    icon: string;
}

interface NavSection {
    section: string;
    items: NavItem[];
}

const navItems: Record<User['role'], NavSection[]> = {
    admin: [
        {
            section: 'Overview',
            items: [
                {
                    label: 'Dashboard',
                    routeName: 'admin.dashboard',
                    routePattern: 'admin/dashboard',
                    icon: 'home',
                },
            ],
        },
        {
            section: 'Academic',
            items: [
                {
                    label: 'Academic Sessions',
                    routeName: 'admin.academic-sessions.index',
                    routePattern: 'admin/academic-sessions*',
                    icon: 'calendar',
                },
                {
                    label: 'Student Batches',
                    routeName: 'admin.student-batches.index',
                    routePattern: 'admin/student-batches*',
                    icon: 'layers',
                },
                {
                    label: 'Periods',
                    routeName: 'admin.periods.index',
                    routePattern: 'admin/periods*',
                    icon: 'clock',
                },
                {
                    label: 'Streams',
                    routeName: 'admin.streams.index',
                    routePattern: 'admin/streams*',
                    icon: 'layers',
                },
                {
                    label: 'Subjects',
                    routeName: 'admin.subjects.index',
                    routePattern: 'admin/subjects*',
                    icon: 'book',
                },
                {
                    label: 'Classes',
                    routeName: 'admin.classes.index',
                    routePattern: 'admin/classes*',
                    icon: 'users',
                },
            ],
        },
        {
            section: 'People',
            items: [
                {
                    label: 'Teachers',
                    routeName: 'admin.teachers.index',
                    routePattern: 'admin/teachers*',
                    icon: 'user-tie',
                },
                {
                    label: 'Students',
                    routeName: 'admin.students.index',
                    routePattern: 'admin/students*',
                    icon: 'graduation',
                },
            ],
        },
        {
            section: 'Attendance',
            items: [
                {
                    label: 'Attendance',
                    routeName: 'admin.attendance.index',
                    routePattern: 'admin/attendance',
                    icon: 'check',
                },
                {
                    label: 'Defaulters',
                    routeName: 'admin.attendance.defaulters',
                    routePattern: 'admin/attendance-defaulters*',
                    icon: 'alert',
                },
                {
                    label: 'Reports',
                    routeName: 'admin.reports.attendance.daily',
                    routePattern: 'admin/reports*',
                    icon: 'chart',
                },
            ],
        },
        {
            section: 'Admissions',
            items: [
                {
                    label: 'Admissions',
                    routeName: 'admin.admissions.index',
                    routePattern: 'admin/admissions*',
                    icon: 'clipboard',
                },
            ],
        },
    ],
    teacher: [
        {
            section: 'Overview',
            items: [
                {
                    label: 'Dashboard',
                    routeName: 'teacher.dashboard',
                    routePattern: 'teacher/dashboard',
                    icon: 'home',
                },
            ],
        },
        {
            section: 'Teaching',
            items: [
                {
                    label: 'Timetable',
                    routeName: 'teacher.timetable',
                    routePattern: 'teacher/timetable',
                    icon: 'calendar',
                },
                {
                    label: 'Attendance',
                    routeName: 'teacher.attendance.index',
                    routePattern: 'teacher/attendance*',
                    icon: 'check',
                },
            ],
        },
    ],
    student: [
        {
            section: 'Overview',
            items: [
                {
                    label: 'Dashboard',
                    routeName: 'student.dashboard',
                    routePattern: 'student/dashboard',
                    icon: 'home',
                },
            ],
        },
        {
            section: 'My Account',
            items: [
                {
                    label: 'My Profile',
                    routeName: 'student.profile',
                    routePattern: 'student/profile',
                    icon: 'user',
                },
                {
                    label: 'Timetable',
                    routeName: 'student.timetable',
                    routePattern: 'student/timetable',
                    icon: 'calendar',
                },
                {
                    label: 'Attendance',
                    routeName: 'student.attendance',
                    routePattern: 'student/attendance',
                    icon: 'check',
                },
            ],
        },
    ],
};

/**
 * Whether a route pattern matches the page currently open.
 *
 * The current path is read from the browser URL via Inertia's page props,
 * not from Ziggy's route().current(), because the nav patterns are URL
 * prefixes ("admin/periods*") and Ziggy's matcher works on route names.
 *
 * Exact patterns (no trailing "*") match only the exact path. Prefix
 * patterns (ending in "*") match the prefix alone or the prefix followed
 * by a slash, so "admin/attendance" does not light up while the user is
 * on "admin/attendance-defaulters".
 */
function isActive(pattern: string, currentPath: string): boolean {
    if (!pattern.endsWith('*')) {
        return currentPath === pattern;
    }

    const prefix = pattern.slice(0, -1);

    return currentPath === prefix || currentPath.startsWith(prefix + '/');
}

const NAV_ICON_PATHS: Record<string, string> = {
    home: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    calendar:
        'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    clock: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    layers:
        'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    book: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    users:
        'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    'user-tie': 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    graduation:
        'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',
    check: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    alert:
        'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    chart:
        'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    clipboard:
        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
    user: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
};

function NavIcon({ name, className = 'h-4 w-4' }: { name: string; className?: string }) {
    return (
        <svg
            className={className}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.75}
                d={NAV_ICON_PATHS[name] ?? NAV_ICON_PATHS.home}
            />
        </svg>
    );
}

function SidebarNav({
    sections,
    currentPath,
    collapsed = false,
    className = '',
    onNavigate,
}: {
    sections: NavSection[];
    currentPath: string;
    collapsed?: boolean;
    className?: string;
    onNavigate?: () => void;
}) {
    return (
        <nav
            className={
                (collapsed ? 'space-y-2 px-2 py-4 ' : 'space-y-4 px-3 py-4 ') + className
            }
        >
            {sections.map((group, index) => (
                <div key={group.section}>
                    {collapsed ? (
                        index > 0 && <div className="mx-2 mb-1 border-t border-gray-100" />
                    ) : (
                        <p className="mb-1 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                            {group.section}
                        </p>
                    )}
                    <div className="space-y-0.5">
                        {group.items.map((item) => {
                            const active = isActive(item.routePattern, currentPath);

                            return (
                                <Link
                                    key={item.routeName}
                                    href={route(item.routeName)}
                                    title={collapsed ? item.label : undefined}
                                    onClick={onNavigate}
                                    aria-current={active ? 'page' : undefined}
                                    className={
                                        'group flex items-center gap-3 rounded-md border-l-4 px-3 py-2 text-sm transition-all duration-150 ' +
                                        (collapsed ? 'justify-center px-2 ' : '') +
                                        (active
                                            ? 'border-gold bg-gold/10 font-semibold text-navy shadow-sm'
                                            : 'border-transparent font-medium text-gray-700 hover:border-gold/40 hover:bg-surface hover:text-navy' +
                                              (collapsed ? '' : ' hover:translate-x-0.5'))
                                    }
                                >
                                    <span
                                        className={
                                            'shrink-0 transition-colors ' +
                                            (active
                                                ? 'text-gold'
                                                : 'text-gray-400 group-hover:text-navy')
                                        }
                                    >
                                        <NavIcon name={item.icon} />
                                    </span>
                                    {!collapsed && <span className="truncate">{item.label}</span>}
                                    {active && !collapsed && (
                                        <span className="ml-auto h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            ))}
        </nav>
    );
}

export default function AuthenticatedLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const page = usePage();
    const user = page.props.auth.user as User;

    // The current URL path without the query string, used for active-state
    // matching. Inertia exposes the current URL on the page object.
    const currentPath = page.url.split('?')[0].replace(/^\//, '');

    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);

    // The desktop sidebar scrolls independently of the page. Because
    // Inertia re-mounts the layout on every navigation, the sidebar's
    // scrollTop would otherwise reset to 0 each time the user clicks a
    // link. Persisting it in sessionStorage keeps the rail where the user
    // left it, and using sessionStorage rather than localStorage means
    // the position is forgotten when the tab closes.
    const sidebarRef = useRef<HTMLElement | null>(null);

    useEffect(() => {
        const el = sidebarRef.current;
        if (!el) return;

        const saved = sessionStorage.getItem('sidebar-scroll-top');
        if (saved !== null) {
            el.scrollTop = parseInt(saved, 10);
        }

        const handleScroll = () => {
            sessionStorage.setItem('sidebar-scroll-top', String(el.scrollTop));
        };

        el.addEventListener('scroll', handleScroll);

        return () => {
            el.removeEventListener('scroll', handleScroll);
        };
    }, []);

    const sections = navItems[user.role] ?? [];

    return (
        <div className="flex h-screen flex-col overflow-hidden bg-surface">
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
                            <CollegeLogo variant="icon" size={40} />
                            <div className="hidden sm:block">
                                <div className="font-serif text-base font-semibold leading-tight">
                                    {BRANDING.name}
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
                <aside
                    ref={sidebarRef}
                    className={
                        'hidden shrink-0 overflow-y-auto border-r border-gray-200 bg-white transition-[width] duration-200 ease-in-out lg:block ' +
                        (collapsed ? 'w-16' : 'w-64')
                    }
                >
                    <div className="flex items-center justify-end px-3 pt-3">
                        <button
                            type="button"
                            onClick={() => setCollapsed((v) => !v)}
                            className="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-surface hover:text-navy focus:outline-none focus:ring-2 focus:ring-gold/40"
                            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                            aria-expanded={!collapsed}
                            title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        >
                            <svg
                                className="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d={
                                        collapsed
                                            ? 'M13 5l7 7-7 7M6 5l7 7-7 7'
                                            : 'M11 19l-7-7 7-7M18 19l-7-7 7-7'
                                    }
                                />
                            </svg>
                        </button>
                    </div>
                    <SidebarNav sections={sections} currentPath={currentPath} collapsed={collapsed} />
                </aside>

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
                            <SidebarNav
                                sections={sections}
                                currentPath={currentPath}
                                className="flex-1 overflow-y-auto"
                                onNavigate={() => setSidebarOpen(false)}
                            />
                        </aside>
                    </>
                )}

                <main className="flex min-w-0 flex-1 flex-col overflow-hidden">
                    {header && (
                        <div className="shrink-0 border-b border-gray-200 bg-white">
                            <div className="flex min-h-[4rem] items-center px-4 py-3 sm:px-6 lg:px-8">
                                <div className="w-full">{header}</div>
                            </div>
                        </div>
                    )}

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