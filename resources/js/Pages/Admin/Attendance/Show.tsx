import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

type Status = 'present' | 'absent' | 'late' | 'leave';

interface AuditEntry {
    id: number;
    edited_by_name: string | null;
    old_status: string;
    new_status: string;
    reason: string | null;
    created_at: string | null;
}

type AttendanceShowPageProps = {
    record: {
        id: number;
        attendance_date: string;
        student: {
            id: number | null;
            name: string | null;
            roll_number: string | null;
            batch_name: string | null;
        };
        class: string | null;
        subject: { name: string | null; code: string | null };
        period: {
            number: number | null;
            label: string | null;
            start_time: string | null;
            end_time: string | null;
        };
        status: Status;
        marked_by: { id: number | null; name: string | null };
        marked_at: string | null;
        notes: string | null;
        audits: AuditEntry[];
    };
    flash?: { success?: string; error?: string };
};

const STATUS_COLORS: Record<string, string> = {
    present: 'bg-green-100 text-green-800',
    absent: 'bg-red-100 text-red-800',
    late: 'bg-amber-100 text-amber-800',
    leave: 'bg-blue-100 text-blue-800',
    deleted: 'bg-gray-100 text-gray-600',
};

const STATUS_OPTIONS: { value: Status; label: string }[] = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'late', label: 'Late' },
    { value: 'leave', label: 'Leave' },
];

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

const selectClass =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-navy focus:ring-navy';

/** A titled card. */
function Card({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className={cardClass}>
            <h3 className="font-serif text-base font-semibold text-navy">
                {title}
            </h3>
            <div className="mt-4">{children}</div>
        </div>
    );
}

/** One label and value. A missing value shows a dash, never a gap. */
function Row({ label, value }: { label: string; value: ReactNode }) {
    const empty = value === null || value === undefined || value === '';

    return (
        <div className="flex flex-wrap justify-between gap-2">
            <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                {label}
            </dt>
            <dd className="text-sm text-gray-800">
                {empty ? <span className="text-gray-400">—</span> : value}
            </dd>
        </div>
    );
}
export default function AttendanceShow() {
    const { record, flash } =
        usePage<PageProps<AttendanceShowPageProps>>().props;

    const [status, setStatus] = useState<Status>(record.status);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    // Disabled until something actually changes: an unchanged status is
    // refused by the controller, and offering a button that always fails
    // teaches the office nothing except to click harder.
    const unchanged = status === record.status;

    const submit = () => {
        setProcessing(true);

        router.patch(
            route('admin.attendance.update', record.id),
            { status, reason },
            {
                preserveScroll: true,
                onSuccess: () => setReason(''),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const remove = () => {
        const why = window.prompt(
            'Deleting this record removes it from the register. Give a reason:',
        );

        if (why === null || why.trim() === '') {
            return;
        }

        setProcessing(true);

        /*
            The two-argument form this Inertia build exposes. The reason goes
            in as `data` alongside the options because router.delete() takes
            no separate body argument, and the server requires a reason on
            every deletion.
        */
        router.delete(route('admin.attendance.destroy', record.id), {
            data: { reason: why },
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Attendance Record
                            {record.student.name
                                ? ` — ${record.student.name}`
                                : ''}
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            {record.class ?? 'Class'} ·{' '}
                            {record.subject.name ?? 'Subject'} ·{' '}
                            {record.period.label ?? 'Period'} ·{' '}
                            {record.attendance_date}
                        </p>
                    </div>

                    <Link
                        href={route('admin.attendance.index')}
                        className="shrink-0 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-surface"
                    >
                        Back
                    </Link>
                </div>
            }
        >
            <Head title="Attendance Record" />

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

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {/* Left: the record and the forms */}
                <div className="flex flex-col gap-4">
                    <Card title="Student and Context">
                        <dl className="space-y-3">
                            <Row
                                label="Student"
                                value={record.student.name}
                            />
                            <Row
                                label="Roll Number"
                                value={record.student.roll_number}
                            />
                            <Row
                                label="Batch"
                                value={record.student.batch_name}
                            />
                            <Row label="Class" value={record.class} />
                            <Row
                                label="Subject"
                                value={
                                    record.subject.code
                                        ? `${record.subject.name} (${record.subject.code})`
                                        : record.subject.name
                                }
                            />
                            <Row
                                label="Period"
                                value={
                                    record.period.start_time &&
                                    record.period.end_time
                                        ? `${record.period.label} (${record.period.start_time}–${record.period.end_time})`
                                        : record.period.label
                                }
                            />
                            <Row
                                label="Date"
                                value={record.attendance_date}
                            />
                        </dl>
                    </Card>

                    <Card title="Current Status">
                        <span
                            className={`rounded-full px-4 py-2 text-lg font-semibold ${STATUS_COLORS[record.status] ?? 'bg-gray-100 text-gray-800'}`}
                        >
                            {record.status}
                        </span>
                        <dl className="mt-4 space-y-3">
                            <Row
                                label="Marked By"
                                value={record.marked_by.name}
                            />
                            <Row label="Marked At" value={record.marked_at} />
                        </dl>
                    </Card>
<Card title="Correct This Record">
                        <div>
                            <label
                                htmlFor="status"
                                className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                            >
                                New Status
                            </label>
                            <select
                                id="status"
                                className={`mt-1 w-full ${selectClass}`}
                                value={status}
                                onChange={(e) =>
                                    setStatus(e.target.value as Status)
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
                                htmlFor="reason"
                                className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                            >
                                Reason (required)
                            </label>
                            <textarea
                                id="reason"
                                rows={3}
                                className={`mt-1 w-full ${selectClass}`}
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                placeholder="Why is this being changed?"
                            />
                            {reason.trim() === '' && !unchanged && (
                                <p className="mt-2 text-sm text-red-600">
                                    Give a reason for the correction. It is
                                    written to the permanent audit trail.
                                </p>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={submit}
                            disabled={
                                unchanged ||
                                reason.trim() === '' ||
                                processing
                            }
                            className="mt-4 w-full rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark disabled:cursor-not-allowed disabled:bg-gray-300"
                        >
                            {processing ? 'Saving...' : 'Update Status'}
                        </button>
                    </Card>

                    <div className={`${cardClass} border-l-4 border-red-400`}>
                        <h3 className="font-serif text-base font-semibold text-red-700">
                            Danger Zone
                        </h3>
                        <p className="mt-2 text-sm text-gray-600">
                            Removing a record takes it off the register. The
                            record is kept on file and the removal is written
                            to the audit trail.
                        </p>
                        <button
                            type="button"
                            onClick={remove}
                            disabled={processing}
                            className="mt-4 w-full rounded-md bg-red-600 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                        >
                            Delete Record
                        </button>
                    </div>
                </div>
{/* Right: the trail */}
                <div className="flex flex-col gap-4">
                    <Card title="Audit History">
                        {record.audits.length === 0 ? (
                            <p className="text-sm text-gray-600">
                                No edits to this record yet.
                            </p>
                        ) : (
                            <ol className="space-y-4">
                                {record.audits.map((audit) => (
                                    <li
                                        key={audit.id}
                                        className="border-l-2 border-gray-200 pl-4"
                                    >
                                        <p className="text-sm text-gray-800">
                                            Changed from{' '}
                                            <span
                                                className={`rounded px-2 py-0.5 text-xs font-semibold ${STATUS_COLORS[audit.old_status] ?? 'bg-gray-100 text-gray-800'}`}
                                            >
                                                {audit.old_status}
                                            </span>{' '}
                                            to{' '}
                                            <span
                                                className={`rounded px-2 py-0.5 text-xs font-semibold ${STATUS_COLORS[audit.new_status] ?? 'bg-gray-100 text-gray-800'}`}
                                            >
                                                {audit.new_status}
                                            </span>
                                        </p>
                                        <p className="mt-1 text-xs text-gray-500">
                                            by {audit.edited_by_name ?? '—'}
                                            {audit.created_at
                                                ? ` on ${audit.created_at}`
                                                : ''}
                                        </p>
                                        {audit.reason && (
                                            <p className="mt-1 text-sm italic text-gray-600">
                                                {audit.reason}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}