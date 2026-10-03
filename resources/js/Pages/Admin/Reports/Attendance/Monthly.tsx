import ReportsTabs from '@/Components/ReportsTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface StudentRow {
    student_profile_id: number;
    roll_number: string;
    student_name: string;
    batch_name: string;
    class_display_name: string | null;
    present: number;
    absent: number;
    late: number;
    leave: number;
    total: number;
    percentage: number | null;
    is_below_threshold: boolean;
}

type MonthlyReportPageProps = {
    report: {
        month: string;
        month_label: string;
        days_in_range: number;
        students: StudentRow[];
        overall: {
            total: number;
            present: number;
            absent: number;
            late: number;
            leave: number;
            percentage: number | null;
        };
    };
    classes: { id: number; display_name: string }[];
    batches: Record<string, string>;
    filters: {
        month: string;
        class_id: number | null;
        batch_id: number | null;
    };
};

const THRESHOLD = 75;

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-4 py-3 text-sm text-gray-700';

const inputClass =
    'mt-1 block h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

function formatPercentage(value: number | null): string {
    return value === null ? '—' : `${value.toFixed(2)}%`;
}
export default function Monthly() {
    const { report, classes, batches, filters } =
        usePage<PageProps<MonthlyReportPageProps>>().props;

    const [month, setMonth] = useState(filters.month);
    const [classId, setClassId] = useState(filters.class_id?.toString() ?? '');
    const [batchId, setBatchId] = useState(
        filters.batch_id?.toString() ?? '',
    );

    const apply = () => {
        router.get(
            route('admin.reports.attendance.monthly'),
            { month, class_id: classId, batch_id: batchId },
            { preserveState: true, preserveScroll: true },
        );
    };

    // The filters that produced this page, not the ones in the inputs: the
    // CSV must match the table on screen even when an admin has typed a new
    // month without pressing Apply yet.
    const exportHref = route('admin.reports.attendance.monthly.export', {
        month: report.month,
        class_id: filters.class_id ?? '',
        batch_id: filters.batch_id ?? '',
    });

    const label =
        'block text-xs font-semibold uppercase tracking-wider text-gray-500';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Monthly Attendance Summary
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            {report.month_label}
                        </p>
                    </div>

                    <a
                        href={exportHref}
                        className="shrink-0 rounded-md bg-navy px-4 py-2 text-sm font-medium text-white hover:bg-navy-dark"
                    >
                        Export CSV
                    </a>
                </div>
            }
        >
            <Head title="Monthly Attendance Summary" />

            <div className="flex h-full min-h-0 flex-col gap-4">
                <ReportsTabs active="monthly" />

                <FilterBar
                    month={month}
                    classId={classId}
                    batchId={batchId}
                    classes={classes}
                    batches={batches}
                    label={label}
                    onMonth={setMonth}
                    onClass={setClassId}
                    onBatch={setBatchId}
                    onApply={apply}
                />

                <SummaryCards report={report} />

                <StudentTable students={report.students} />
            </div>
        </AuthenticatedLayout>
    );
}
/** The month, class and batch pickers. */
function FilterBar({
    month,
    classId,
    batchId,
    classes,
    batches,
    label,
    onMonth,
    onClass,
    onBatch,
    onApply,
}: {
    month: string;
    classId: string;
    batchId: string;
    classes: { id: number; display_name: string }[];
    batches: Record<string, string>;
    label: string;
    onMonth: (v: string) => void;
    onClass: (v: string) => void;
    onBatch: (v: string) => void;
    onApply: () => void;
}) {
    return (
        <div className="shrink-0 flex flex-wrap items-end gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
            <div>
                <label htmlFor="month" className={label}>
                    Month
                </label>
                <input
                    id="month"
                    type="month"
                    className={inputClass}
                    value={month}
                    onChange={(e) => onMonth(e.target.value)}
                />
            </div>

            <div className="min-w-[11rem]">
                <label htmlFor="class_id" className={label}>
                    Class
                </label>
                <select
                    id="class_id"
                    className={`block w-full ${inputClass}`}
                    value={classId}
                    onChange={(e) => onClass(e.target.value)}
                >
                    <option value="">All classes</option>
                    {classes.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.display_name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="min-w-[10rem]">
                <label htmlFor="batch_id" className={label}>
                    Batch
                </label>
                <select
                    id="batch_id"
                    className={`block w-full ${inputClass}`}
                    value={batchId}
                    onChange={(e) => onBatch(e.target.value)}
                >
                    <option value="">All batches</option>
                    {Object.entries(batches).map(([id, name]) => (
                        <option key={id} value={id}>
                            {name}
                        </option>
                    ))}
                </select>
            </div>

            <button
                type="button"
                onClick={onApply}
                className="h-10 rounded-md bg-navy px-6 text-sm font-medium text-white hover:bg-navy-dark"
            >
                Apply
            </button>
        </div>
    );
}

/** The month's four headline figures. */
function SummaryCards({
    report,
}: {
    report: MonthlyReportPageProps['report'];
}) {
    const percentage = report.overall.percentage;

    return (
        <div className="grid shrink-0 grid-cols-2 gap-4 lg:grid-cols-4">
            <div className={cardClass}>
                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                    Month
                </p>
                <p className="mt-2 font-serif text-2xl font-semibold text-navy">
                    {report.month_label}
                </p>
            </div>

            <div className={cardClass}>
                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                    Days in Range
                </p>
                <p className="mt-2 font-serif text-2xl font-semibold text-navy">
                    {report.days_in_range}
                </p>
            </div>

            <div className={cardClass}>
                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                    Total Records
                </p>
                <p className="mt-2 font-serif text-2xl font-semibold text-navy">
                    {report.overall.total}
                </p>
            </div>

            <div className={cardClass}>
                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                    Percentage
                </p>
                <p
                    className={
                        'mt-2 font-serif text-2xl font-semibold ' +
                        (percentage !== null && percentage < THRESHOLD
                            ? 'text-red-600'
                            : 'text-navy')
                    }
                >
                    {formatPercentage(percentage)}
                </p>
            </div>
        </div>
    );
}
/** The per-student register for the month, worst first. */
function StudentTable({ students }: { students: StudentRow[] }) {
    if (students.length === 0) {
        return (
            <div className="flex min-h-0 flex-1 flex-col items-center justify-center rounded-lg bg-white p-12 text-center shadow-sm ring-1 ring-gray-200">
                <p className="text-sm font-medium text-gray-700">
                    No students match these filters.
                </p>
                <p className="mt-1 text-sm text-gray-500">
                    Try a different month, class or batch.
                </p>
            </div>
        );
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
            <div className="min-h-0 flex-1 overflow-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="sticky top-0 z-10 bg-surface">
                        <tr>
                            <th className={thClass}>Roll #</th>
                            <th className={thClass}>Student</th>
                            <th className={thClass}>Batch</th>
                            <th className={thClass}>Class</th>
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
                        {students.map((student) => (
                            <tr key={student.student_profile_id}>
                                <td className={`${tdClass} font-medium text-gray-900`}>
                                    {student.roll_number}
                                </td>
                                <td
                                    className={`${tdClass} font-medium text-gray-900`}
                                >
                                    {student.student_name}
                                </td>
                                <td className={tdClass}>{student.batch_name}</td>
                                <td className={tdClass}>
                                    {student.class_display_name ?? (
                                        <span className="text-gray-400">
                                            Not enrolled
                                        </span>
                                    )}
                                </td>
                                <td className={tdClass}>{student.present}</td>
                                <td className={tdClass}>{student.absent}</td>
                                <td className={tdClass}>{student.late}</td>
                                <td className={tdClass}>{student.leave}</td>
                                <td className={tdClass}>{student.total}</td>
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
                                    {student.is_below_threshold ? (
                                        <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                                            Below {THRESHOLD}%
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
    );
}