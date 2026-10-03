import ReportsTabs from '@/Components/ReportsTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

interface PeriodRow {
    period_id: number;
    period_number: number | null;
    period_label: string | null;
    start_time: string | null;
    end_time: string | null;
    subject_name: string;
    subject_code: string | null;
    teacher_name: string;
    total: number;
    present: number;
    absent: number;
    late: number;
    leave: number;
    percentage: number | null;
    is_marked: boolean;
}

interface StudentCell {
    period_id: number;
    status: string | null;
    subject_name: string;
}

interface StudentRow {
    student_profile_id: number;
    roll_number: string;
    student_name: string;
    periods: StudentCell[];
    present: number;
    absent: number;
    late: number;
    leave: number;
    total: number;
    percentage: number | null;
}

interface Report {
    class: {
        id: number;
        display_name: string;
        grade_level: number;
        section: string | null;
        stream_name: string;
        session_name: string;
    } | null;
    date: string;
    periods: PeriodRow[];
    students: StudentRow[];
    overall: {
        total: number;
        present: number;
        absent: number;
        late: number;
        leave: number;
        percentage: number | null;
    };
}

/** A report that definitely has a class, as the tables below assume. */
type ReportWithClass = Report & { class: NonNullable<Report['class']> };

type DailyReportPageProps = {
    report: Report | null;
    classes: { id: number; display_name: string }[];
    filters: { class_id: number | null; date: string };
    flash?: { success?: string; error?: string };
};

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-4 py-3 text-sm text-gray-700';

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

/**
 * The four marks, abbreviated for the matrix.
 *
 * The matrix cell is too small for a full word, so the initials are used and
 * spelled out in the title attribute. Leave is LV rather than L so it cannot
 * be read as late.
 */
const MATRIX_CODES: Record<string, string> = {
    present: 'P',
    absent: 'A',
    late: 'L',
    leave: 'LV',
};

const MATRIX_COLORS: Record<string, string> = {
    present: 'bg-green-100 text-green-800',
    absent: 'bg-red-100 text-red-800',
    late: 'bg-amber-100 text-amber-800',
    leave: 'bg-blue-100 text-blue-800',
};

/** A percentage, or a dash when there is nothing to divide. */
function formatPercentage(value: number | null): string {
    return value === null ? '—' : `${value.toFixed(2)}%`;
}

/** One of the summary tiles across the top. */
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

export default function Daily() {
    const { report, classes, filters, flash } =
        usePage<PageProps<DailyReportPageProps>>().props;

    const [classId, setClassId] = useState(filters.class_id?.toString() ?? '');
    const [date, setDate] = useState(filters.date);

    const apply = () => {
        // Only a chosen class navigates: applying with nothing selected would
        // bounce straight back with an error.
        if (classId === '') {
            return;
        }

        router.get(
            route('admin.reports.attendance.daily'),
            { class_id: classId, date },
            { preserveState: true, preserveScroll: true },
        );
    };

    const exportHref = report
        ? route('admin.reports.attendance.daily.export', {
              class_id: report.class?.id,
              date: report.date,
          })
        : null;

    const hasClass = report !== null && report.class !== null;
    const noPeriods = hasClass && report.periods.length === 0;

    /*
        Narrowed once here and handed down, because the individual child
        tables read report.class half a dozen times and narrowing it inside
        each of them would repeat the same guard everywhere. The service only
        returns a null class when the id does not exist, which the filter
        cannot produce.
    */
    const readyReport =
        report !== null && report.class !== null
            ? ({ ...report, class: report.class } as ReportWithClass)
            : null;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Daily Attendance Report
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Period-by-period register for one class
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
            <Head title="Daily Attendance Report" />

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
                <ReportsTabs active="daily" />

                <FilterBar
                    classId={classId}
                    date={date}
                    classes={classes}
                    onClass={setClassId}
                    onDate={setDate}
                    onApply={apply}
                />

                {!hasClass ? (
                    <EmptyState message="Select a class and a date to see the report." />
                ) : noPeriods ? (
                    <EmptyState message="No periods scheduled for this day." />
                ) : (
                    readyReport && <ReportBody report={readyReport} />
                )}
            </div>
        </AuthenticatedLayout>
    );
}

/** The class and date pickers, shared by nothing else on this page. */
function FilterBar({
    classId,
    date,
    classes,
    onClass,
    onDate,
    onApply,
}: {
    classId: string;
    date: string;
    classes: { id: number; display_name: string }[];
    onClass: (value: string) => void;
    onDate: (value: string) => void;
    onApply: () => void;
}) {
    const inputClass =
        'mt-1 block h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

    return (
        <div className="shrink-0 flex flex-wrap items-end gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
            <div className="min-w-[14rem] flex-1">
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
                    htmlFor="date"
                    className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                >
                    Date
                </label>
                <input
                    id="date"
                    type="date"
                    className={inputClass}
                    value={date}
                    onChange={(e) => onDate(e.target.value)}
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

/** The "nothing chosen yet" / "nothing to show" panel. */
function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-lg bg-white p-12 text-center shadow-sm ring-1 ring-gray-200">
            <p className="text-sm font-medium text-gray-700">{message}</p>
        </div>
    );
}
/**
 * Everything below the filter, once a class and date are chosen.
 *
 * Only rendered when report.class is set - the page checks that before
 * calling - so the class is read through a non-null local rather than through
 * a nullable property that TypeScript would guard on every line.
 */
function ReportBody({ report }: { report: ReportWithClass }) {
    return (
        <>
            {/* Summary */}
            <div className="grid shrink-0 grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
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
                    value={formatPercentage(report.overall.percentage)}
                />
            </div>

            <p className="shrink-0 text-sm text-gray-600">
                {report.class.display_name} · {report.date} ·{' '}
                {report.class.session_name}
            </p>

            {/* Period table (2/3) beside the student summary (1/3) */}
            <div className="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-3">
                <PeriodTable report={report} />
                <StudentSummaryTable report={report} />
            </div>

            <MatrixTable report={report} />
        </>
    );
}

/** The strip heading a table card. */
function CardTitle({ children }: { children: ReactNode }) {
    return (
        <div className="shrink-0 border-b border-gray-200 px-4 py-3">
            <h3 className="font-serif text-base font-semibold text-navy">
                {children}
            </h3>
        </div>
    );
}
/** The register by period - what each teacher marked, for the day. */
function PeriodTable({ report }: { report: Report }) {
    return (
        <div className="flex min-h-0 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 lg:col-span-2">
            <CardTitle>By Period</CardTitle>
            <div className="min-h-0 flex-1 overflow-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="sticky top-0 z-10 bg-surface">
                        <tr>
                            <th className={thClass}>Period</th>
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
                        {report.periods.map((period) => (
                            <tr key={period.period_id}>
                                <td
                                    className={`${tdClass} font-medium text-gray-900`}
                                >
                                    {period.period_label}
                                    {period.start_time && (
                                        <span className="ml-2 text-xs text-gray-500">
                                            {period.start_time}–{period.end_time}
                                        </span>
                                    )}
                                </td>
                                <td className={tdClass}>
                                    {period.subject_name}
                                </td>
                                <td className={tdClass}>
                                    {period.teacher_name}
                                </td>
                                <td className={tdClass}>{period.present}</td>
                                <td className={tdClass}>{period.absent}</td>
                                <td className={tdClass}>{period.late}</td>
                                <td className={tdClass}>{period.leave}</td>
                                <td className={tdClass}>{period.total}</td>
                                {/* Below the day's own average: the periods
                                    dragging the class down, which is what
                                    this column is for. */}
                                <td
                                    className={
                                        'whitespace-nowrap px-4 py-3 text-sm font-semibold ' +
                                        (isBelowDayAverage(
                                            period.percentage,
                                            report.overall.percentage,
                                        )
                                            ? 'text-red-600'
                                            : 'text-navy')
                                    }
                                >
                                    {formatPercentage(period.percentage)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/**
 * Whether one period sits below the day's average.
 *
 * Compared against the day's own figure rather than the 75% threshold,
 * because this table answers "which period is dragging the class down", and
 * a term of good days makes every period comfortably clear of 75 without any
 * of them standing out.
 */
function isBelowDayAverage(
    percentage: number | null,
    average: number | null,
): boolean {
    return percentage !== null && average !== null && percentage < average;
}
/** The day's totals per student - the short list beside the period table. */
function StudentSummaryTable({ report }: { report: Report }) {
    return (
        <div className="flex min-h-0 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
            <CardTitle>By Student</CardTitle>
            <div className="min-h-0 flex-1 overflow-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="sticky top-0 z-10 bg-surface">
                        <tr>
                            <th className={thClass}>Roll #</th>
                            <th className={thClass}>Student</th>
                            <th className={thClass}>Present</th>
                            <th className={thClass}>Absent</th>
                            <th className={thClass}>Percentage</th>
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
                                <td className={tdClass}>{student.present}</td>
                                <td className={tdClass}>{student.absent}</td>
                                <td
                                    className={
                                        'whitespace-nowrap px-4 py-3 text-sm font-semibold ' +
                                        (student.percentage !== null &&
                                        student.percentage < 75
                                            ? 'text-red-600'
                                            : 'text-navy')
                                    }
                                >
                                    {formatPercentage(student.percentage)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
/**
 * The full grid: students down, periods across.
 *
 * This is the sheet a class teacher used to be handed on paper, and it is
 * the only view that answers "who was missing Biology on Tuesday" without
 * the reader cross-referencing two tables. It sits below the summaries
 * rather than replacing them because it is wide and the summary is what most
 * callers came for.
 */
function MatrixTable({ report }: { report: Report }) {
    return (
        <div className="flex max-h-[28rem] flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
            <CardTitle>Student × Period</CardTitle>
            <div className="min-h-0 flex-1 overflow-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="sticky top-0 z-10 bg-surface">
                        <tr>
                            <th className={thClass}>Roll #</th>
                            <th className={thClass}>Student</th>
                            {report.periods.map((period) => (
                                <th
                                    key={period.period_id}
                                    className="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-600"
                                    title={`${period.subject_name} — ${period.teacher_name}`}
                                >
                                    {period.period_number ?? ''}
                                </th>
                            ))}
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
                                {report.periods.map((period) => {
                                    const cell = student.periods.find(
                                        (p) => p.period_id === period.period_id,
                                    );

                                    return (
                                        <td
                                            key={period.period_id}
                                            className="px-3 py-2 text-center"
                                        >
                                            <MatrixCell status={cell?.status ?? null} />
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/**
 * One matrix cell.
 *
 * An unmarked cell is a dash rather than a grey badge: "the teacher has not
 * submitted this period" and "the teacher submitted it as absent" are
 * different facts and must not look alike.
 */
function MatrixCell({ status }: { status: string | null }) {
    if (status === null) {
        return <span className="text-gray-300">—</span>;
    }

    const code = MATRIX_CODES[status] ?? '?';
    const colour = MATRIX_COLORS[status] ?? 'bg-gray-100 text-gray-800';

    return (
        <span
            title={status}
            className={`inline-block h-6 w-6 rounded-full text-xs font-semibold leading-6 ${colour}`}
        >
            {code}
        </span>
    );
}