import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type Status = 'present' | 'absent' | 'late' | 'leave';

interface RecordRow {
    id: number;
    attendance_date: string;
    student: {
        id: number | null;
        name: string | null;
        roll_number: string | null;
    };
    class: string | null;
    subject: { name: string | null; code: string | null };
    period: { number: number | null; label: string | null };
    status: Status;
    marked_by: string | null;
    marked_at: string | null;
    class_subject_id: number;
    period_id: number;
    audit_count: number;
}

type AttendanceIndexPageProps = {
    records: {
        data: RecordRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    counts: {
        present: number;
        absent: number;
        late: number;
        leave: number;
        total: number;
    };
    classes: { id: number; display_name: string }[];
    subjects: { id: number; label: string }[];
    periods: { id: number; label: string }[];
    teachers: Record<string, string>;
    filters: {
        class_id: number | null;
        date: string | null;
        class_subject_id: number | null;
        period_id: number | null;
        teacher_id: number | null;
        status: string;
        search: string;
    };
    flash?: { success?: string; error?: string };
};

/**
 * The four marks, with the colours the Attendance model publishes for the
 * rest of the application. One list, so a badge here cannot disagree with
 * one on the student's page.
 */
const STATUS_COLORS: Record<Status, string> = {
    present: 'bg-green-100 text-green-800',
    absent: 'bg-red-100 text-red-800',
    late: 'bg-amber-100 text-amber-800',
    leave: 'bg-blue-100 text-blue-800',
};

const STATUS_OPTIONS: { value: Status; label: string }[] = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'late', label: 'Late' },
    { value: 'leave', label: 'Leave' },
];

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const selectClass =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-navy focus:ring-navy';

const inputClass =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-navy focus:ring-navy';
export default function AttendanceIndex() {
    const {
        records,
        counts,
        classes,
        subjects,
        periods,
        teachers,
        filters,
        flash,
    } = usePage<PageProps<AttendanceIndexPageProps>>().props;

    const [selected, setSelected] = useState<number[]>([]);
    const [showBulk, setShowBulk] = useState(false);
    const [bulkStatus, setBulkStatus] = useState<Status>('present');
    const [bulkReason, setBulkReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [bulkError, setBulkError] = useState('');

    const rows = records.data ?? [];

    // The selection is meaningless across pages or filters, so it is dropped
    // whenever the result set changes rather than silently carrying ids that
    // are no longer on screen.
    useEffect(() => {
        setSelected([]);
    }, [records.current_page, filters]);

    const selectedRows = useMemo(
        () => rows.filter((row) => selected.includes(row.id)),
        [rows, selected],
    );

    /*
        A bulk edit writes one status to many records, and the server refuses
        any id outside the named scope. Checking here means the admin is told
        why before they fill in a reason, instead of after.
    */
    const scope = selectedRows.length
        ? {
              class_subject_id: selectedRows[0].class_subject_id,
              period_id: selectedRows[0].period_id,
              attendance_date: selectedRows[0].attendance_date,
          }
        : null;

    const mixedScope =
        scope !== null &&
        selectedRows.some(
            (row) =>
                row.class_subject_id !== scope.class_subject_id ||
                row.period_id !== scope.period_id ||
                row.attendance_date !== scope.attendance_date,
        );

    const applyFilters = (next: Record<string, string>) => {
        /*
            Rebuilt from scratch rather than merged into `filters`: the
            normalised filter object holds numbers and nulls, which the query
            string cannot carry. Starting empty means a cleared field really
            clears instead of sending "all".
        */
        const query: Record<string, string> = {};

        if (filters.class_id) query.class_id = String(filters.class_id);
        if (filters.date) query.date = filters.date;
        if (filters.class_subject_id)
            query.class_subject_id = String(filters.class_subject_id);
        if (filters.period_id) query.period_id = String(filters.period_id);
        if (filters.teacher_id) query.teacher_id = String(filters.teacher_id);
        if (filters.status !== 'all') query.status = filters.status;
        if (filters.search) query.search = filters.search;

        (Object.keys(next) as (keyof typeof next)[]).forEach((key) => {
            if (next[key]) {
                query[key] = next[key];
            } else {
                delete query[key];
            }
        });

        router.get(route('admin.attendance.index'), query, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const toggle = (id: number) => {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    const openBulk = () => {
        if (mixedScope) {
            setBulkError(
                'Bulk edit requires all selected rows to be from the same class, period, and date.',
            );
            return;
        }

        setBulkError('');
        setShowBulk(true);
    };

    const submitBulk = () => {
        if (!scope || mixedScope) {
            return;
        }

        setProcessing(true);

        router.post(
            route('admin.attendance.bulk-update'),
            {
                ...scope,
                reason: bulkReason,
                updates: selectedRows.map((row) => ({
                    attendance_id: row.id,
                    status: bulkStatus,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowBulk(false);
                    setSelected([]);
                    setBulkReason('');
                },
                onFinish: () => setProcessing(false),
            },
        );
    };
return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Attendance Records
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Review and correct submitted registers
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Attendance Records" />

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

            <div className="flex flex-col gap-4">
                <FilterBar
                    filters={filters}
                    classes={classes}
                    subjects={subjects}
                    periods={periods}
                    teachers={teachers}
                    onApply={applyFilters}
                />

                {/* Counts for the current filter, not the current page */}
                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                        Total {counts.total}
                    </span>
                    {(Object.keys(STATUS_COLORS) as Status[]).map((s) => (
                        <span
                            key={s}
                            className={`rounded-full px-3 py-1 text-xs font-semibold ${STATUS_COLORS[s]}`}
                        >
                            {STATUS_OPTIONS.find((o) => o.value === s)?.label}{' '}
                            {counts[s]}
                        </span>
                    ))}
                </div>
<div className="flex flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-surface">
                                <tr>
                                    <th className={thClass}>
                                        <input
                                            type="checkbox"
                                            aria-label="Select all on this page"
                                            className="h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy"
                                            checked={
                                                rows.length > 0 &&
                                                selected.length === rows.length
                                            }
                                            onChange={() =>
                                                setSelected(
                                                    selected.length ===
                                                        rows.length
                                                        ? []
                                                        : rows.map(
                                                              (r) => r.id,
                                                          ),
                                                )
                                            }
                                        />
                                    </th>
                                    <th className={thClass}>Date</th>
                                    <th className={thClass}>Student</th>
                                    <th className={thClass}>Class</th>
                                    <th className={thClass}>Subject</th>
                                    <th className={thClass}>Period</th>
                                    <th className={thClass}>Status</th>
                                    <th className={thClass}>Marked By</th>
                                    <th className={thClass}>Marked At</th>
                                    <th className={thClass}>Edits</th>
                                    <th className={thClass}>Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {rows.map((row) => (
                                    <RecordRowView
                                        key={row.id}
                                        row={row}
                                        checked={selected.includes(row.id)}
                                        onToggle={() => toggle(row.id)}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {rows.length === 0 && (
                        <p className="p-12 text-center text-sm text-gray-600">
                            No attendance records match the selected filters.
                        </p>
                    )}
{/* Pagination */}
                    {records.last_page > 1 && (
                        <div className="flex flex-wrap items-center gap-1 border-t border-gray-200 bg-surface px-4 py-3">
                            {records.links.map((link, i) =>
                                link.url === null ? (
                                    <span
                                        key={`sep-${i}`}
                                        className="px-2 text-sm text-gray-400"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <Link
                                        key={`link-${i}`}
                                        href={link.url}
                                        className={
                                            'rounded px-3 py-1 text-sm font-medium ' +
                                            (link.active
                                                ? 'bg-navy text-white'
                                                : 'text-gray-700 hover:bg-white')
                                        }
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </div>
                    )}
                </div>

                {selected.length > 0 && (
                    <div className="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-lg border-l-4 border-navy bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <p className="text-sm text-gray-700">
                            {selected.length} record
                            {selected.length === 1 ? '' : 's'} selected
                            {mixedScope && (
                                <span className="ml-2 text-red-600">
                                    — selection spans more than one period.
                                </span>
                            )}
                        </p>
                        <button
                            type="button"
                            onClick={openBulk}
                            className="rounded-md bg-navy px-4 py-2 text-sm font-semibold text-white hover:bg-navy-dark"
                        >
                            Bulk Update
                        </button>
                    </div>
                )}

                {bulkError && (
                    <div className="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">
                        {bulkError}
                    </div>
                )}
            </div>
<Modal show={showBulk} onClose={() => setShowBulk(false)}>
                <h2 className="text-lg font-medium text-gray-900">
                    Bulk Update Attendance
                </h2>
                <p className="mt-2 text-sm text-gray-600">
                    {selected.length} record
                    {selected.length === 1 ? '' : 's'} will be set to the status
                    below. Every change is written to the audit trail.
                </p>

                <div className="mt-4">
                    <label
                        htmlFor="bulk_status"
                        className="block text-sm font-medium text-gray-700"
                    >
                        New Status
                    </label>
                    <select
                        id="bulk_status"
                        className={`mt-1 w-full ${selectClass}`}
                        value={bulkStatus}
                        onChange={(e) =>
                            setBulkStatus(e.target.value as Status)
                        }
                    >
                        {STATUS_OPTIONS.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="mt-4">
                    <label
                        htmlFor="bulk_reason"
                        className="block text-sm font-medium text-gray-700"
                    >
                        Reason (required)
                    </label>
                    <textarea
                        id="bulk_reason"
                        rows={3}
                        className={`mt-1 w-full ${inputClass}`}
                        value={bulkReason}
                        onChange={(e) => setBulkReason(e.target.value)}
                    />
                    {bulkReason.trim() === '' && (
                        <p className="mt-2 text-sm text-red-600">
                            Give a reason for the correction.
                        </p>
                    )}
                </div>

                <div className="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={() => setShowBulk(false)}
                        className="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-navy"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={submitBulk}
                        disabled={bulkReason.trim() === '' || processing}
                        className="rounded-md bg-navy px-6 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark disabled:cursor-not-allowed disabled:bg-gray-300"
                    >
                        {processing ? 'Saving...' : 'Confirm'}
                    </button>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
/**
 * One line of the register.
 *
 * Split out so the table body stays readable: the column list is long enough
 * that inlining it would bury the one thing the eye is looking for, which is
 * the status badge.
 */
function RecordRowView({
    row,
    checked,
    onToggle,
}: {
    row: RecordRow;
    checked: boolean;
    onToggle: () => void;
}) {
    const td = 'whitespace-nowrap px-4 py-2 text-sm text-gray-700';

    return (
        <tr className="hover:bg-surface">
            <td className="px-4 py-2">
                <input
                    type="checkbox"
                    aria-label={`Select ${row.student.name ?? 'record'}`}
                    className="h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy"
                    checked={checked}
                    onChange={onToggle}
                />
            </td>
            <td className={td}>{row.attendance_date}</td>
            <td className="whitespace-nowrap px-4 py-2 text-sm">
                <span className="font-medium text-gray-900">
                    {row.student.name ?? '—'}
                </span>
                <span className="ml-2 text-xs text-gray-500">
                    {row.student.roll_number ?? '—'}
                </span>
            </td>
            <td className={td}>{row.class ?? '—'}</td>
            <td className={td}>{row.subject.name ?? '—'}</td>
            <td className={td}>{row.period.label ?? '—'}</td>
            <td className="whitespace-nowrap px-4 py-2">
                <span
                    className={`rounded-full px-3 py-1 text-xs font-semibold ${STATUS_COLORS[row.status]}`}
                >
                    {row.status}
                </span>
            </td>
            <td className={td}>{row.marked_by ?? '—'}</td>
            <td className={td}>{row.marked_at ?? '—'}</td>
            <td className="whitespace-nowrap px-4 py-2">
                {/*
                    Red once a record has been touched: an edit is the reason an
                    admin should look twice before trusting a mark.
                */}
                <span
                    className={
                        'rounded-full px-3 py-1 text-xs font-semibold ' +
                        (row.audit_count > 0
                            ? 'bg-red-100 text-red-800'
                            : 'bg-gray-100 text-gray-500')
                    }
                >
                    {row.audit_count}
                </span>
            </td>
            <td className="whitespace-nowrap px-4 py-2">
                <Link
                    href={route('admin.attendance.show', row.id)}
                    className="text-sm font-medium text-navy hover:text-gold"
                >
                    View
                </Link>
            </td>
        </tr>
    );
}
/**
 * The filter bar.
 *
 * Every control is server-side: changing one navigates with the query, so a
 * filtered register can be linked to, bookmarked and reloaded. The search box
 * submits on Enter rather than on every keystroke, because a request per
 * letter against fourteen thousand records is not a polite thing to do.
 */
function FilterBar({
    filters,
    classes,
    subjects,
    periods,
    teachers,
    onApply,
}: {
    filters: AttendanceIndexPageProps['filters'];
    classes: { id: number; display_name: string }[];
    subjects: { id: number; label: string }[];
    periods: { id: number; label: string }[];
    teachers: Record<string, string>;
    onApply: (next: Record<string, string>) => void;
}) {
    const label =
        'block text-xs font-semibold uppercase tracking-wider text-gray-500';

    return (
        <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label htmlFor="class_id" className={label}>
                        Class
                    </label>
                    <select
                        id="class_id"
                        className={`mt-1 w-full ${selectClass}`}
                        value={filters.class_id ?? ''}
                        onChange={(e) =>
                            onApply({ class_id: e.target.value })
                        }
                    >
                        <option value="">All classes</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.display_name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label htmlFor="date" className={label}>
                        Date
                    </label>
                    <input
                        id="date"
                        type="date"
                        className={`mt-1 w-full ${inputClass}`}
                        value={filters.date ?? ''}
                        onChange={(e) => onApply({ date: e.target.value })}
                    />
                </div>

                <div>
                    <label htmlFor="class_subject_id" className={label}>
                        Subject / Class
                    </label>
                    <select
                        id="class_subject_id"
                        className={`mt-1 w-full ${selectClass}`}
                        value={filters.class_subject_id ?? ''}
                        onChange={(e) =>
                            onApply({ class_subject_id: e.target.value })
                        }
                    >
                        <option value="">All subjects</option>
                        {subjects.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label htmlFor="period_id" className={label}>
                        Period
                    </label>
                    <select
                        id="period_id"
                        className={`mt-1 w-full ${selectClass}`}
                        value={filters.period_id ?? ''}
                        onChange={(e) =>
                            onApply({ period_id: e.target.value })
                        }
                    >
                        <option value="">All periods</option>
                        {periods.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.label}
                            </option>
                        ))}
                    </select>
                </div>
<div>
                    <label htmlFor="teacher_id" className={label}>
                        Marked By
                    </label>
                    <select
                        id="teacher_id"
                        className={`mt-1 w-full ${selectClass}`}
                        value={filters.teacher_id ?? ''}
                        onChange={(e) =>
                            onApply({ teacher_id: e.target.value })
                        }
                    >
                        <option value="">All teachers</option>
                        {Object.entries(teachers).map(([id, name]) => (
                            <option key={id} value={id}>
                                {name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label htmlFor="status" className={label}>
                        Status
                    </label>
                    <select
                        id="status"
                        className={`mt-1 w-full ${selectClass}`}
                        value={filters.status}
                        onChange={(e) => onApply({ status: e.target.value })}
                    >
                        <option value="all">All statuses</option>
                        {STATUS_OPTIONS.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="sm:col-span-2">
                    <label htmlFor="search" className={label}>
                        Search
                    </label>
                    <input
                        id="search"
                        type="search"
                        placeholder="Student name or roll number"
                        className={`mt-1 w-full ${inputClass}`}
                        defaultValue={filters.search}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                onApply({
                                    search: (e.target as HTMLInputElement)
                                        .value,
                                });
                            }
                        }}
                    />
                </div>
            </div>
        </div>
    );
}