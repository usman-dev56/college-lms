import ReportsTabs from '@/Components/ReportsTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

interface StudentRow {
    student_profile_id: number;
    roll_number: string;
    student_name: string;
    present: number;
    absent: number;
    late: number;
    leave: number;
    total: number;
    percentage: number | null;
    is_below_threshold: boolean;
}

interface SubjectRow {
    class_subject_id: number;
    subject_name: string;
    subject_code: string | null;
    teacher_name: string;
    total: number;
    present: number;
    absent: number;
    late: number;
    leave: number;
    percentage: number | null;
}

interface Report {
    class: {
        id: number;
        display_name: string;
        grade_level: number;
        section: string | null;
        stream_name: string;
    } | null;
    from: string;
    to: string;
    days_count: number;
    students: StudentRow[];
    by_subject: SubjectRow[];
    overall: {
        total: number;
        present: number;
        absent: number;
        late: number;
        leave: number;
        percentage: number | null;
    };
}

type RangeReportPageProps = {
    report: Report | null;
    classes: { id: number; display_name: string }[];
    filters: { class_id: number | null; from: string; to: string };
    flash?: { success?: string; error?: string };
};

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-4 py-3 text-sm text-gray-700';

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

/** The board eligibility threshold, matching AttendanceService::THRESHOLD. */
const THRESHOLD = 75;

function formatPercentage(value: number | null): string {
    return value === null ? '—' : `${value.toFixed(2)}%`;
}

function SummaryCard({
    label,
    value,
    tone = 'text-navy',
}: {
    label: string;
    value: string | number;
    tone?: string;
}) {
    return (
        <div className={cardClass}>
            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                {label}
            </p>
            <p className={`mt-2 font-serif text-2xl font-semibold ${tone}`}>
                {value}
            </p>
        </div>
    );
}


function CardTitle({ children }: { children: ReactNode }) {
    return (
        <div className="shrink-0 border-b border-gray-200 px-4 py-3">
            <h3 className="font-serif text-base font-semibold text-navy">
                {children}
            </h3>
        </div>
    );
}
/** A report that definitely has a class, as the tables below assume. */
type ReportWithClass = Report & { class: NonNullable<Report['class']> };

export default function Range() {
    const { report, classes, filters, flash } =
        usePage<PageProps<RangeReportPageProps>>().props;

    const [classId, setClassId] = useState(filters.class_id?.toString() ?? '');
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const apply = () => {
        // Only a chosen class navigates: applying with nothing selected would
        // bounce straight back with an error.
        if (classId === '') {
            return;
        }

        router.get(
            route('admin.reports.attendance.range'),
            { class_id: classId, from, to },
            { preserveState: true, preserveScroll: true },
        );
    };

    const exportHref = report
        ? route('admin.reports.attendance.range.export', {
              class_id: report.class?.id,
              from: report.from,
              to: report.to,
          })
        : null;

    const hasClass = report !== null && report.class !== null;
return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Attendance Range Report
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Term summary for one class
                        </p>
                    </div>

                    {exportHref && (
                        <a
                            href={exportHref}
                            className="shrink-0 rounded-md bg-navy px-4 py-2 text-sm font-medium text-white hover:bg-navy-dark"
                        >
                            Export CSV
                        </a>
                    )}
                </div>
            }
        >
            <Head title="Attendance Range Report" />

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
                <ReportsTabs active="range" />

                <RangeFilterBar
                    classId={classId}
                    from={from}
                    to={to}
                    classes={classes}
                    onClass={setClassId}
                    onFrom={setFrom}
                    onTo={setTo}
                    onApply={apply}
                />

                {!hasClass ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-lg bg-white p-12 text-center shadow-sm ring-1 ring-gray-200">
                        <p className="text-sm font-medium text-gray-700">
                            Select a class and a date range to see the report.
                        </p>
                    </div>
                ) : (
                    <ReportBody report={report as ReportWithClass} />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
/** The class and date-range pickers. */
function RangeFilterBar({
    classId,
    from,
    to,
    classes,
    onClass,
    onFrom,
    onTo,
    onApply,
}: {
    classId: string;
    from: string;
    to: string;
    classes: { id: number; display_name: string }[];
    onClass: (value: string) => void;
    onFrom: (value: string) => void;
    onTo: (value: string) => void;
    onApply: () => void;
}) {
    const inputClass =
        'mt-1 block h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

    return (
        <div className="shrink-0 flex flex-wrap items-end gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
            <div className="min-w-[13rem] flex-1">
                <label
                    htmlFor="class_id"
                    className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                >
                    Class
                </label>
                <select
                    id="class_id"
                    className={`block w-full ${inputClass}`}
                    value={classId}
                    onChange={(e) => onClass(e.target.value)}
                >
                    <option value="">Select a class…</option>
                    {classes.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.display_name}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <label
                    htmlFor="from"
                    className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                >
                    From
                </label>
                <input
                    id="from"
                    type="date"
                    className={inputClass}
                    value={from}
                    onChange={(e) => onFrom(e.target.value)}
                />
            </div>

            <div>
                <label
                    htmlFor="to"
                    className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                >
                    To
                </label>
                <input
                    id="to"
                    type="date"
                    className={inputClass}
                    value={to}
                    onChange={(e) => onTo(e.target.value)}
                />
            </div>

            <button
                type="button"
                onClick={onApply}
                disabled={classId === ''}
                className="h-10 rounded-md bg-navy px-6 text-sm font-medium text-white hover:bg-navy-dark disabled:cursor-not-allowed disabled:bg-gray-300"
            >
                Apply
            </button>
        </div>
    );
}
/**
 * Everything below the filter, once a class and range are chosen.
 */
function ReportBody({ report }: { report: ReportWithClass }) {
    const percentage = report.overall.percentage;

    // Green at or above the board threshold, red below. Null is grey: an
    // unmarked range is unknown, and colouring it would imply a verdict.
    const belowThreshold = percentage !== null && percentage < THRESHOLD;
    const barColour =
        percentage === null
            ? 'bg-gray-300'
            : belowThreshold
              ? 'bg-red-500'
              : 'bg-green-500';

    return (
        <>
            {/* Summary */}
            <div className="grid shrink-0 grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-7">
                <SummaryCard label="Days in Range" value={report.days_count} />
                <SummaryCard label="Total Records" value={report.overall.total} />
                <SummaryCard
                    label="Present"
                    value={report.overall.present}
                    tone="text-green-700"
                />
                <SummaryCard
                    label="Absent"
                    value={report.overall.absent}
                    tone="text-red-700"
                />
                <SummaryCard
                    label="Late"
                    value={report.overall.late}
                    tone="text-amber-700"
                />
                <SummaryCard
                    label="Leave"
                    value={report.overall.leave}
                    tone="text-blue-700"
                />
                <SummaryCard
                    label="Percentage"
                    value={formatPercentage(percentage)}
                    tone={belowThreshold ? 'text-red-600' : 'text-navy'}
                />
            </div>

            <p className="shrink-0 text-sm text-gray-600">
                {report.class.display_name} · {report.from} to {report.to}
            </p>

            {/* The headline bar */}
            <div className="shrink-0 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div className="flex items-center justify-between">
                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                        Overall Attendance
                    </p>
                    <p
                        className={
                            'font-serif text-xl font-semibold ' +
                            (belowThreshold ? 'text-red-600' : 'text-navy')
                        }
                    >
                        {formatPercentage(percentage)}
                    </p>
                </div>

                {/*
                    The bar is scaled against 100 rather than against the
                    threshold, so its length is the percentage itself and not a
                    redrawn figure: a bar that always filled two thirds would
                    stop meaning anything.
                */}
                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                    <div
                        className={`h-full rounded-full ${barColour}`}
                        style={{
                            width: `${percentage === null ? 0 : percentage}%`,
                        }}
                    />
                </div>

                <p className="mt-2 text-xs text-gray-500">
                    Board eligibility needs {THRESHOLD}%.{' '}
                    {percentage === null
                        ? 'Nothing has been marked in this range yet.'
                        : belowThreshold
                          ? `This class is ${(THRESHOLD - percentage).toFixed(2)}% short of the threshold.`
                          : 'The class is above the eligibility threshold.'}
                </p>
            </div>
{/* Per student */}
            <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                <CardTitle>By Student</CardTitle>
                <div className="min-h-0 flex-1 overflow-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="sticky top-0 z-10 bg-surface">
                            <tr>
                                <th className={thClass}>Roll #</th>
                                <th className={thClass}>Student</th>
                                <th className={thClass}>Present</th>
                                <th className={thClass}>Absent</th>
                                <th className={thClass}>Late</th>
                                <th className={thClass}>Leave</th>
                                <th className={thClass}>Total</th>
                                <th className={thClass}>Percentage</th>
                                <th className={thClass}>Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {report.students.map((student) => (
                                <tr key={student.student_profile_id}>
                                    <td
                                        className={`${tdClass} font-semibold text-navy`}
                                    >
                                        {student.roll_number}
                                    </td>
                                    <td
                                        className={`${tdClass} font-medium text-gray-900`}
                                    >
                                        {student.student_name}
                                    </td>
                                    <td className={tdClass}>
                                        {student.present}
                                    </td>
                                    <td className={tdClass}>
                                        {student.absent}
                                    </td>
                                    <td className={tdClass}>
                                        {student.late}
                                    </td>
                                    <td className={tdClass}>
                                        {student.leave}
                                    </td>
                                    <td className={tdClass}>
                                        {student.total}
                                    </td>
                                    <td
                                        className={
                                            'whitespace-nowrap px-4 py-3 text-sm font-semibold ' +
                                            (student.percentage === null
                                                ? 'text-gray-400'
                                                : student.is_below_threshold
                                                  ? 'text-red-600'
                                                  : 'text-navy')
                                        }
                                    >
                                        {formatPercentage(student.percentage)}
                                    </td>
                                    <td className={tdClass}>
                                        {student.percentage === null ? (
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                                                —
                                            </span>
                                        ) : student.is_below_threshold ? (
                                            <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                                                At Risk
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">
                                                OK
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
{/* Per subject */}
            <div className="flex max-h-[24rem] flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                <CardTitle>By Subject</CardTitle>
                <div className="min-h-0 flex-1 overflow-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="sticky top-0 z-10 bg-surface">
                            <tr>
                                <th className={thClass}>Subject</th>
                                <th className={thClass}>Teacher</th>
                                <th className={thClass}>Present</th>
                                <th className={thClass}>Absent</th>
                                <th className={thClass}>Late</th>
                                <th className={thClass}>Leave</th>
                                <th className={thClass}>Total</th>
                                <th className={thClass}>Percentage</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {report.by_subject.map((subject) => (
                                <tr key={subject.class_subject_id}>
                                    <td
                                        className={`${tdClass} font-medium text-gray-900`}
                                    >
                                        {subject.subject_name}
                                        {subject.subject_code && (
                                            <span className="ml-2 text-xs text-gray-500">
                                                {subject.subject_code}
                                            </span>
                                        )}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.teacher_name}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.present}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.absent}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.late}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.leave}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.total}
                                    </td>
                                    <td
                                        className={
                                            'whitespace-nowrap px-4 py-3 text-sm font-semibold ' +
                                            (subject.percentage === null
                                                ? 'text-gray-400'
                                                : subject.percentage < THRESHOLD
                                                  ? 'text-red-600'
                                                  : 'text-navy')
                                        }
                                    >
                                        {formatPercentage(subject.percentage)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}