import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Teacher {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    subjects: string[];
    assignments_count: number;
}

interface SubjectOption {
    id: number;
    name: string;
    code: string | null;
    grade_level: number;
}

interface ClassOption {
    id: number;
    display_name: string;
    grade_level: number;
    stream_name: string | null;
    section: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type TeachersPageProps = {
    teachers: {
        data: Teacher[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
        current_page: number;
        last_page: number;
    };
    subjects: SubjectOption[];
    classes: ClassOption[];
    filters: {
        search: string;
        subject_id: number | null;
        class_id: number | null;
        status: string;
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

export default function Index() {
    const { teachers, subjects, classes, filters, flash } =
        usePage<PageProps<TeachersPageProps>>().props;

    const [search, setSearch] = useState(filters.search);

    // Keep the box in step when the filters change from elsewhere (the Clear
    // link, or the back button).
    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    const apply = (next: Record<string, string | number>) => {
        router.get(
            route('admin.teachers.index'),
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

    const handleDelete = (teacher: Teacher) => {
        if (
            !window.confirm(
                `Delete teacher "${teacher.name}"? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(route('admin.teachers.destroy', teacher.id), {
            preserveScroll: true,
        });
    };

    const hasFilters =
        filters.search !== '' ||
        filters.subject_id !== null ||
        filters.class_id !== null ||
        filters.status !== 'all';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Teachers
                    </h2>
                    <Link
                        href={route('admin.teachers.create')}
                        className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Teacher
                    </Link>
                </div>
            }
        >
            <Head title="Teachers" />

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
                            htmlFor="teacher-search"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Search
                        </label>
                        <input
                            id="teacher-search"
                            type="search"
                            value={search}
                            placeholder="Search name or email"
                            className="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy"
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <div className="w-full sm:w-56">
                        <label
                            htmlFor="subject-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Subject
                        </label>
                        <select
                            id="subject-filter"
                            value={filters.subject_id ?? 'all'}
                            className={selectClass}
                            onChange={(e) =>
                                apply({
                                    subject_id:
                                        e.target.value === 'all'
                                            ? ''
                                            : e.target.value,
                                })
                            }
                        >
                            <option value="all">All subjects</option>
                            {subjects.map((subject) => (
                                <option key={subject.id} value={subject.id}>
                                    {subject.name} (G
                                    {subject.grade_level})
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="w-full sm:w-64">
                        <label
                            htmlFor="class-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Class
                        </label>
                        <select
                            id="class-filter"
                            value={filters.class_id ?? 'all'}
                            className={selectClass}
                            onChange={(e) =>
                                apply({
                                    class_id:
                                        e.target.value === 'all'
                                            ? ''
                                            : e.target.value,
                                })
                            }
                        >
                            <option value="all">All classes</option>
                            {classes.map((classOption) => (
                                <option
                                    key={classOption.id}
                                    value={classOption.id}
                                >
                                    {classOption.display_name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="w-full sm:w-40">
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
                            onChange={(e) => apply({ status: e.target.value })}
                        >
                            <option value="all">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    {hasFilters && (
                        <Link
                            href={route('admin.teachers.index')}
                            className="flex h-10 items-center text-sm font-medium text-gray-600 hover:text-navy"
                        >
                            Clear
                        </Link>
                    )}

                    <p className="ml-auto flex h-10 items-center text-sm text-gray-500">
                        Showing {teachers.data.length} of {teachers.total}{' '}
                        teachers
                    </p>
                </div>

                {teachers.data.length === 0 ? (
                    <div className="rounded-lg bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-200">
                        <p className="text-sm text-gray-600">
                            {teachers.total === 0 && !hasFilters
                                ? 'No teachers yet. Create the first one to get started.'
                                : 'No teachers match the selected filters.'}
                        </p>
                        <Link
                            href={route('admin.teachers.create')}
                            className="mt-4 inline-block rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                        >
                            New Teacher
                        </Link>
                    </div>
                ) : (
                    <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        <div className="min-h-0 flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>Name</th>
                                        <th className={thClass}>Email</th>
                                        <th className={thClass}>Phone</th>
                                        <th className={thClass}>
                                            Subjects
                                        </th>
                                        <th className={thClass}>
                                            Classes
                                        </th>
                                        <th className={thClass}>
                                            Status
                                        </th>
                                        <th
                                            className={`${thClass} text-right`}
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {teachers.data.map((teacher) => (
                                        <tr
                                            key={teacher.id}
                                            className="hover:bg-surface"
                                        >
                                            <td
                                                className={`${tdClass} font-medium text-navy`}
                                            >
                                                {teacher.name}
                                            </td>
                                            <td className={tdClass}>
                                                {teacher.email}
                                            </td>
                                            <td className={tdClass}>
                                                {teacher.phone ?? '—'}
                                            </td>
                                            <td
                                                className={`${tdClass} max-w-xs truncate`}
                                                title={teacher.subjects.join(
                                                    ', ',
                                                )}
                                            >
                                                {teacher.subjects.length > 0
                                                    ? teacher.subjects.join(', ')
                                                    : '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {teacher.assignments_count}
                                            </td>
                                            <td className={tdClass}>
                                                {teacher.is_active ? (
                                                    <span className="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                                        Inactive
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                className={`${tdClass} text-right`}
                                            >
                                                <div className="flex items-center justify-end gap-3">
                                                    <Link
                                                        href={route(
                                                            'admin.teachers.show',
                                                            teacher.id,
                                                        )}
                                                        className="font-medium text-navy hover:text-gold"
                                                    >
                                                        View
                                                    </Link>
                                                    <Link
                                                        href={route(
                                                            'admin.teachers.edit',
                                                            teacher.id,
                                                        )}
                                                        className="font-medium text-navy hover:text-gold"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(
                                                                teacher,
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

                        {teachers.last_page > 1 && (
                            <nav className="shrink-0 flex items-center justify-between border-t border-gray-200 px-6 py-3">
                                <p className="text-sm text-gray-500">
                                    Page {teachers.current_page} of{' '}
                                    {teachers.last_page}
                                </p>
                                <div className="flex gap-2">
                                    {teachers.links.map((link, index) => {
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
                )}
            </div>
        </AuthenticatedLayout>
    );
}

