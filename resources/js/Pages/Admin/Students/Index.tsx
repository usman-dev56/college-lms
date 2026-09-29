import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Student {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    roll_number: string;
    batch_id: number;
    batch_name: string | null;
    gender: string | null;
    status: string;
    is_active: boolean;
    // Always null until enrollment lands in 3.4. The key is in place so the
    // table does not have to change shape when it does.
    enrolled_class: string | null;
}

interface BatchOption {
    id: number;
    name: string;
    is_active: boolean;
    current_grade: number | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type StudentsPageProps = {
    students: {
        data: Student[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
        current_page: number;
        last_page: number;
    };
    batches: BatchOption[];
    filters: {
        search: string;
        batch_id: number | null;
        status: string;
        gender: string;
    };
    flash?: {
        success?: string;
        error?: string;
    };
};

const thClass =
    'px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-6 py-4 text-sm text-gray-700';

const selectClass =
    'block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

// Filtering happens on the server, so the URL stays shareable and the page
// size does not fight the browser history.
const DEBOUNCE_MS = 300;

// The status colours live in one place so a status looks the same in the list
// badge as it does on the student's own page.
const statusStyles: Record<string, string> = {
    active: 'bg-green-100 text-green-800',
    graduated: 'bg-blue-100 text-blue-800',
    withdrawn: 'bg-gray-100 text-gray-600',
    suspended: 'bg-amber-100 text-amber-800',
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    graduated: 'Graduated',
    withdrawn: 'Withdrawn',
    suspended: 'Suspended',
};

/** "Male" from "male", so the table never shows a raw column value. */
const titleCase = (value: string): string =>
    value.charAt(0).toUpperCase() + value.slice(1);

export default function Index() {
    const { students, batches, filters, flash } =
        usePage<PageProps<StudentsPageProps>>().props;

    const [search, setSearch] = useState(filters.search);

    // Keep the box in step when the filters change from elsewhere (the Clear
    // link, or the back button).
    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    const apply = (next: Record<string, string | number>) => {
        router.get(
            route('admin.students.index'),
            { ...filters, ...next, page: 1 },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    // The text box filters as you type; the drop-downs filter on change.
    useEffect(() => {
        if (search === filters.search) {
            return;
        }

        const timer = setTimeout(() => {
            apply({ search });
        }, DEBOUNCE_MS);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const handleDelete = (student: Student) => {
        if (
            !window.confirm(
                `Delete student "${student.name ?? student.roll_number}"? ` +
                    'Their login is closed at the same time.',
            )
        ) {
            return;
        }

        router.delete(route('admin.students.destroy', student.id), {
            preserveScroll: true,
        });
    };

    const hasFilters =
        filters.search !== '' ||
        filters.batch_id !== null ||
        filters.status !== 'all' ||
        filters.gender !== 'all';

    const range =
        students.from !== null && students.to !== null
            ? `${students.from}-${students.to}`
            : '0';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Students
                    </h2>
                    <Link
                        href={route('admin.students.create')}
                        className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Student
                    </Link>
                </div>
            }
        >
            <Head title="Students" />

            {flash?.success && (
                <div className="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">
                    {flash.error}
                </div>
            )}

            <div className="flex h-full min-h-0 flex-col gap-4">
                {/* Filter bar */}
                <div className="shrink-0 flex flex-wrap items-end gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
                    <div className="w-full sm:w-64">
                        <label
                            htmlFor="student-search"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Search
                        </label>
                        <input
                            id="student-search"
                            type="search"
                            value={search}
                            placeholder="Name, email, roll no or CNIC"
                            className="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy"
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <div className="w-full sm:w-48">
                        <label
                            htmlFor="batch-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Batch
                        </label>
                        <select
                            id="batch-filter"
                            value={filters.batch_id ?? 'all'}
                            className={selectClass}
                            onChange={(e) =>
                                apply({
                                    batch_id:
                                        e.target.value === 'all'
                                            ? ''
                                            : e.target.value,
                                })
                            }
                        >
                            <option value="all">All batches</option>
                            {batches.map((batch) => (
                                <option key={batch.id} value={batch.id}>
                                    {batch.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="w-full sm:w-44">
                        <label
                            htmlFor="status-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Status
                        </label>
                        <select
                            id="status-filter"
                            value={filters.status}
                            className={selectClass}
                            onChange={(e) =>
                                apply({ status: e.target.value })
                            }
                        >
                            <option value="all">All statuses</option>
                            {Object.entries(statusLabels).map(
                                ([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>
                    </div>

                    <div className="w-full sm:w-40">
                        <label
                            htmlFor="gender-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Gender
                        </label>
                        <select
                            id="gender-filter"
                            value={filters.gender}
                            className={selectClass}
                            onChange={(e) =>
                                apply({ gender: e.target.value })
                            }
                        >
                            <option value="all">All</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    {hasFilters && (
                        <Link
                            href={route('admin.students.index')}
                            className="flex h-10 items-center text-sm font-medium text-gray-600 hover:text-navy"
                        >
                            Clear
                        </Link>
                    )}

                    <p className="ml-auto flex h-10 items-center text-sm text-gray-500">
                        Showing {range} of {students.total}
                    </p>
                </div>

                {/* Table */}
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    {students.data.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 p-12 text-center">
                            <p className="text-sm font-medium text-gray-700">
                                {hasFilters
                                    ? 'No students match the selected filters.'
                                    : 'No students yet. Create the first one to get started.'}
                            </p>
                            {!hasFilters && (
                                <Link
                                    href={route('admin.students.create')}
                                    className="text-sm font-semibold text-navy hover:text-gold"
                                >
                                    New Student
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="min-h-0 flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>Roll #</th>
                                        <th className={thClass}>Name</th>
                                        <th className={thClass}>Email</th>
                                        <th className={thClass}>Phone</th>
                                        <th className={thClass}>Batch</th>
                                        <th className={thClass}>Gender</th>
                                        <th className={thClass}>Status</th>
                                        <th
                                            className={`${thClass} text-right`}
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {students.data.map((student) => (
                                        <tr
                                            key={student.id}
                                            className="hover:bg-surface"
                                        >
                                            <td
                                                className={`${tdClass} font-semibold text-navy`}
                                            >
                                                {student.roll_number}
                                            </td>
                                            <td
                                                className={`${tdClass} font-medium text-gray-900`}
                                            >
                                                {student.name ?? (
                                                    <span className="text-gray-500">
                                                        Unknown Student
                                                    </span>
                                                )}
                                                {!student.is_active && (
                                                    <span className="ml-2 text-xs text-gray-500">
                                                        (inactive)
                                                    </span>
                                                )}
                                            </td>
                                            <td className={tdClass}>
                                                {student.email ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {student.phone ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {student.batch_name ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {student.gender
                                                    ? titleCase(student.gender)
                                                    : '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                <span
                                                    className={
                                                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ' +
                                                        (statusStyles[
                                                            student.status
                                                        ] ??
                                                            'bg-gray-100 text-gray-600')
                                                    }
                                                >
                                                    {statusLabels[
                                                        student.status
                                                    ] ?? student.status}
                                                </span>
                                            </td>
                                            <td
                                                className={`${tdClass} text-right`}
                                            >
                                                <div className="flex items-center justify-end gap-3">
                                                    <Link
                                                        href={route(
                                                            'admin.students.show',
                                                            student.id,
                                                        )}
                                                        className="font-medium text-navy hover:text-gold"
                                                    >
                                                        View
                                                    </Link>
                                                    <Link
                                                        href={route(
                                                            'admin.students.edit',
                                                            student.id,
                                                        )}
                                                        className="font-medium text-navy hover:text-gold"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(
                                                                student,
                                                            )
                                                        }
                                                        className="font-medium text-red-600 hover:text-red-800"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {students.last_page > 1 && (
                        <nav className="shrink-0 flex items-center justify-between border-t border-gray-200 px-6 py-3">
                            <p className="text-sm text-gray-500">
                                Page {students.current_page} of{' '}
                                {students.last_page}
                            </p>
                            <div className="flex gap-2">
                                {students.links.map((link, index) => {
                                    // The paginator wraps the cursor in
                                    // "Previous"/"Next" labels plus "..."
                                    // gaps; the gaps are not links.
                                    if (link.url === null) {
                                        return (
                                            <span
                                                key={`gap-${index}`}
                                                className="flex h-8 items-center px-2 text-sm text-gray-400"
                                            >
                                                {link.label}
                                            </span>
                                        );
                                    }

                                    return (
                                        <Link
                                            key={`${link.label}-${index}`}
                                            href={link.url}
                                            preserveScroll
                                            className={
                                                link.active
                                                    ? 'flex h-8 items-center rounded-md bg-navy px-3 text-sm font-semibold text-white'
                                                    : 'flex h-8 items-center rounded-md border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-surface'
                                            }
                                        >
                                            {link.label
                                                .replace(
                                                    /&laquo;|&raquo;/g,
                                                    '',
                                                )
                                                .trim()}
                                        </Link>
                                    );
                                })}
                            </div>
                        </nav>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
