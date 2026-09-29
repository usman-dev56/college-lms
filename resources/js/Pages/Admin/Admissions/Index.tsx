import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Admission {
    id: number;
    application_number: string;
    applicant_name: string;
    father_name: string | null;
    cnic_bform: string;
    phone: string | null;
    stream_name: string | null;
    batch_name: string | null;
    status: string;
    merit_percentage: number | null;
    merit_rank: number | null;
    created_at: string | null;
}

interface BatchOption {
    id: number;
    name: string;
}

interface StreamOption {
    id: number;
    name: string;
    code: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

type AdmissionsPageProps = {
    admissions: {
        data: Admission[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
        current_page: number;
        last_page: number;
    };
    batches: BatchOption[];
    streams: StreamOption[];
    filters: {
        search: string;
        batch_id: number | null;
        stream_id: number | null;
        status: string;
    };
    counts: Record<string, number>;
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

/**
 * The statuses, in the order the office works through them.
 *
 * The labels and colours live here rather than being repeated per row, so a
 * status looks identical in the counts row, the filter and the table.
 */
const statuses = [
    { value: 'pending', label: 'Pending', badge: 'bg-amber-100 text-amber-800' },
    { value: 'reviewed', label: 'Reviewed', badge: 'bg-blue-100 text-blue-800' },
    { value: 'accepted', label: 'Accepted', badge: 'bg-green-100 text-green-800' },
    { value: 'rejected', label: 'Rejected', badge: 'bg-red-100 text-red-800' },
    { value: 'enrolled', label: 'Enrolled', badge: 'bg-purple-100 text-purple-800' },
] as const;

const statusBadge = (status: string): string =>
    statuses.find((s) => s.value === status)?.badge ?? 'bg-gray-100 text-gray-600';

const statusLabel = (status: string): string =>
    statuses.find((s) => s.value === status)?.label ?? status;

export default function Index() {
    const { admissions, batches, streams, filters, counts, flash } =
        usePage<PageProps<AdmissionsPageProps>>().props;

    const [search, setSearch] = useState(filters.search);

    // Keep the box in step when the filters change from elsewhere (the Clear
    // link, the counts row, or the back button).
    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    const apply = (next: Record<string, string | number>) => {
        router.get(
            route('admin.admissions.index'),
            { ...filters, ...next, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // The text box filters as you type; everything else filters on change.
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

    // A count badge toggles its own filter: clicking the active one clears
    // it, so the counts row doubles as the quickest way back to everything.
    const toggleStatus = (status: string) => {
        apply({ status: filters.status === status ? 'all' : status });
    };

    const hasFilters =
        filters.search !== '' ||
        filters.batch_id !== null ||
        filters.stream_id !== null ||
        filters.status !== 'all';

    const range =
        admissions.from !== null && admissions.to !== null

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Admissions
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Applications received through the public form.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Admissions" />

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
                {/*
                    Counts row. These are counts over every application, not
                    over the filtered list, so they keep showing the backlog
                    while a filter is applied - which is the whole point of
                    having them.
                */}
                <div className="shrink-0 flex flex-wrap gap-3">
                    {statuses.map((status) => {
                        const active = filters.status === status.value;

                        return (
                            <button
                                key={status.value}
                                type="button"
                                onClick={() => toggleStatus(status.value)}
                                aria-pressed={active}
                                className={
                                    'flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold shadow-sm ring-1 transition ' +
                                    status.badge +
                                    ' ' +
                                    (active
                                        ? 'ring-2 ring-navy'
                                        : 'opacity-80 hover:opacity-100')
                                }
                            >
                                {status.label}
                                <span className="rounded-full bg-white/70 px-2 py-0.5 text-xs font-bold">
                                    {counts[status.value] ?? 0}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Filter bar */}
                <div className="shrink-0 flex flex-wrap items-end gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
                    <div className="w-full sm:w-64">
                        <label
                            htmlFor="admission-search"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Search
                        </label>
                        <input
                            id="admission-search"
                            type="search"
                            value={search}
                            placeholder="Name, application no or CNIC"
                            className="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy"
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <div className="w-full sm:w-44">
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

                    <div className="w-full sm:w-48">
                        <label
                            htmlFor="stream-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Stream
                        </label>
                        <select
                            id="stream-filter"
                            value={filters.stream_id ?? 'all'}
                            className={selectClass}
                            onChange={(e) =>
                                apply({
                                    stream_id:
                                        e.target.value === 'all'
                                            ? ''
                                            : e.target.value,
                                })
                            }
                        >
                            <option value="all">All streams</option>
                            {streams.map((stream) => (
                                <option key={stream.id} value={stream.id}>
                                    {stream.name}
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
                            onChange={(e) =>
                                apply({ status: e.target.value })
                            }
                        >
                            <option value="all">All statuses</option>
                            {statuses.map((status) => (
                                <option
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {hasFilters && (
                        <Link
                            href={route('admin.admissions.index')}
                            className="flex h-10 items-center text-sm font-medium text-gray-600 hover:text-navy"
                        >
                            Clear
                        </Link>
                    )}

                    <p className="ml-auto flex h-10 items-center text-sm text-gray-500">
                        Showing {range} of {admissions.total} applications
                    </p>
                </div>

                {/* Table */}
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    {admissions.data.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 p-12 text-center">
                            <p className="text-sm font-medium text-gray-700">
                                {hasFilters
                                    ? 'No applications match the selected filters.'
                                    : 'No applications yet.'}
                            </p>
                            {!hasFilters && (
                                <p className="text-xs text-gray-500">
                                    Applications appear here as they arrive
                                    through the public form.
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="min-h-0 flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>
                                            Application #
                                        </th>
                                        <th className={thClass}>Applicant</th>
                                        <th className={thClass}>
                                            Father Name
                                        </th>
                                        <th className={thClass}>
                                            CNIC / B-Form
                                        </th>
                                        <th className={thClass}>Stream</th>
                                        <th className={thClass}>Batch</th>
                                        <th className={thClass}>Merit %</th>
                                        <th className={thClass}>Status</th>
                                        <th className={thClass}>Applied</th>
                                        <th
                                            className={`${thClass} text-right`}
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {admissions.data.map((admission) => (
                                        <tr
                                            key={admission.id}
                                            className="hover:bg-surface"
                                        >
                                            <td
                                                className={`${tdClass} font-mono text-xs font-semibold text-navy`}
                                            >
                                                {
                                                    admission.application_number
                                                }
                                            </td>
                                            <td
                                                className={`${tdClass} font-medium text-gray-900`}
                                            >
                                                {admission.applicant_name}
                                            </td>
                                            <td className={tdClass}>
                                                {admission.father_name ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {admission.cnic_bform}
                                            </td>
                                            <td className={tdClass}>
                                                {admission.stream_name ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {admission.batch_name ?? '—'}
                                            </td>
                                            <td className={`${tdClass} font-medium`}>
                                                {admission.merit_percentage !==
                                                null
                                                    ? `${admission.merit_percentage.toFixed(2)}%`
                                                    : '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                <span
                                                    className={
                                                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ' +
                                                        statusBadge(
                                                            admission.status,
                                                        )
                                                    }
                                                >
                                                    {statusLabel(
                                                        admission.status,
                                                    )}
                                                </span>
                                            </td>
                                            <td className={tdClass}>
                                                {admission.created_at ?? '—'}
                                            </td>
                                            <td
                                                className={`${tdClass} text-right`}
                                            >
                                                <Link
                                                    href={route(
                                                        'admin.admissions.show',
                                                        admission.id,
                                                    )}
                                                    className="font-medium text-navy hover:text-gold"
                                                >
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {admissions.last_page > 1 && (
                        <nav className="shrink-0 flex items-center justify-between border-t border-gray-200 px-6 py-3">
                            <p className="text-sm text-gray-500">
                                Page {admissions.current_page} of{' '}
                                {admissions.last_page}
                            </p>
                            <div className="flex gap-2">
                                {admissions.links.map((link, index) => {
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
