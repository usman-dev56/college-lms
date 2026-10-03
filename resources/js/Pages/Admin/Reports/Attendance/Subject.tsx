import ReportsTabs from '@/Components/ReportsTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface SubjectRow {
    class_subject_id: number;
    subject_id: number;
    subject_name: string;
    subject_code: string | null;
    class_id: number;
    class_display_name: string;
    grade_level: number;
    stream_name: string;
    teacher_name: string;
    total: number;
    present: number;
    absent: number;
    late: number;
    leave: number;
    percentage: number | null;
}

interface GradeRow {
    grade_level: number;
    total: number;
    present: number;
    percentage: number | null;
}

type SubjectReportPageProps = {
    report: {
        from: string;
        to: string;
        subjects: SubjectRow[];
        by_grade: GradeRow[];
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
    streams: Record<string, string>;
    grades: number[];
    filters: {
        class_id: number | null;
        stream_id: number | null;
        grade_level: number | null;
        from: string;
        to: string;
    };
};

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-4 py-3 text-sm text-gray-700';

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

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
            <p className={`mt-2 font-serif text-2xl font-semibold ${tone}`}>{value}</p>
        </div>
    );
}
export default function Subject() {
    const { report, classes, streams, grades, filters } =
        usePage<PageProps<SubjectReportPageProps>>().props;

    const [classId, setClassId] = useState(filters.class_id?.toString() ?? '');
    const [streamId, setStreamId] = useState(
        filters.stream_id?.toString() ?? '',
    );
    const [grade, setGrade] = useState(
        filters.grade_level?.toString() ?? '',
    );
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const apply = () => {
        router.get(
            route('admin.reports.attendance.subject'),
            {
                class_id: classId,
                stream_id: streamId,
                grade_level: grade,
                from,
                to,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    // The export carries the filters that produced this page, not the ones
    // sitting in the inputs: the CSV has to match the table on screen even if
    // the admin has typed something new but not pressed Apply.
    const exportHref = route('admin.reports.attendance.subject.export', {
        class_id: filters.class_id ?? '',
        stream_id: filters.stream_id ?? '',
        grade_level: filters.grade_level ?? '',
        from: report.from,
        to: report.to,
    });

    const inputClass =
        'mt-1 block h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Subject-wise Attendance Report
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            {report.from} to {report.to}
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
            <Head title="Subject Attendance Report" />

            <div className="flex h-full min-h-0 flex-col gap-4">
                <ReportsTabs active="subject" />

                <FilterBar
                    classId={classId}
                    streamId={streamId}
                    grade={grade}
                    from={from}
                    to={to}
                    classes={classes}
                    streams={streams}
                    grades={grades}
                    inputClass={inputClass}
                    onClass={setClassId}
                    onStream={setStreamId}
                    onGrade={setGrade}
                    onFrom={setFrom}
                    onTo={setTo}
                    onApply={apply}
                />

                <div className="grid shrink-0 grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <SummaryCard
                        label="Total Records"
                        value={report.overall.total}
                    />
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
                        tone={
                            report.overall.percentage !== null &&
                            report.overall.percentage < THRESHOLD
                                ? 'text-red-600'
                                : 'text-navy'
                        }
                    />
                </div>

                <div className="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-3">
                    <SubjectTable report={report} />
                    <GradeCard byGrade={report.by_grade} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
/** The class, stream, grade and date pickers. */
function FilterBar({
    classId,
    streamId,
    grade,
    from,
    to,
    classes,
    streams,
    grades,
    inputClass,
    onClass,
    onStream,
    onGrade,
    onFrom,
    onTo,
    onApply,
}: {
    classId: string;
    streamId: string;
    grade: string;
    from: string;
    to: string;
    classes: { id: number; display_name: string }[];
    streams: Record<string, string>;
    grades: number[];
    inputClass: string;
    onClass: (v: string) => void;
    onStream: (v: string) => void;
    onGrade: (v: string) => void;
    onFrom: (v: string) => void;
    onTo: (v: string) => void;
    onApply: () => void;
}) {
    const label =
        'block text-xs font-semibold uppercase tracking-wider text-gray-500';

    return (
        <div className="shrink-0 flex flex-wrap items-end gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
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
                <label htmlFor="stream_id" className={label}>
                    Stream
                </label>
                <select
                    id="stream_id"
                    className={`block w-full ${inputClass}`}
                    value={streamId}
                    onChange={(e) => onStream(e.target.value)}
                >
                    <option value="">All streams</option>
                    {Object.entries(streams).map(([id, name]) => (
                        <option key={id} value={id}>
                            {name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="min-w-[7rem]">
                <label htmlFor="grade_level" className={label}>
                    Grade
                </label>
                <select
                    id="grade_level"
                    className={`block w-full ${inputClass}`}
                    value={grade}
                    onChange={(e) => onGrade(e.target.value)}
                >
                    <option value="">All grades</option>
                    {grades.map((g) => (
                        <option key={g} value={g}>
                            {g}th
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <label htmlFor="from" className={label}>
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
                <label htmlFor="to" className={label}>
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
                className="h-10 rounded-md bg-navy px-6 text-sm font-medium text-white hover:bg-navy-dark"
            >
                Apply
            </button>
        </div>
    );
}
/** The per class-subject register, worst first. */
function SubjectTable({
    report,
}: {
    report: SubjectReportPageProps['report'];
}) {
    return (
        <div className="flex min-h-0 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 lg:col-span-2">
            <div className="shrink-0 border-b border-gray-200 px-4 py-3">
                <h3 className="font-serif text-base font-semibold text-navy">
                    By Subject
                </h3>
            </div>

            {report.subjects.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center p-12 text-center">
                    <p className="text-sm font-medium text-gray-700">
                        No attendance records in this range.
                    </p>
                </div>
            ) : (
                <div className="min-h-0 flex-1 overflow-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="sticky top-0 z-10 bg-surface">
                            <tr>
                                <th className={thClass}>Subject</th>
                                <th className={thClass}>Class</th>
                                <th className={thClass}>Grade</th>
                                <th className={thClass}>Teacher</th>
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
                            {report.subjects.map((subject) => (
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
                                        {subject.class_display_name}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.grade_level}th
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
                                    <td className={tdClass}>
                                        <StatusBadge
                                            percentage={subject.percentage}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
/**
 * The OK / At Risk badge beside a percentage.
 *
 * Null is a dash rather than either verdict: a subject with nothing marked
 * has an unknown attendance, and calling it "OK" would be as wrong as
 * calling it "At Risk".
 */
function StatusBadge({ percentage }: { percentage: number | null }) {
    if (percentage === null) {
        return (
            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                —
            </span>
        );
    }

    return percentage < THRESHOLD ? (
        <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
            At Risk
        </span>
    ) : (
        <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">
            OK
        </span>
    );
}

/** The same figures rolled up per grade level. */
function GradeCard({ byGrade }: { byGrade: GradeRow[] }) {
    return (
        <div className="flex min-h-0 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
            <div className="shrink-0 border-b border-gray-200 px-4 py-3">
                <h3 className="font-serif text-base font-semibold text-navy">
                    By Grade
                </h3>
            </div>

            <div className="min-h-0 flex-1 overflow-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="sticky top-0 z-10 bg-surface">
                        <tr>
                            <th className={thClass}>Grade</th>
                            <th className={thClass}>Total</th>
                            <th className={thClass}>Present</th>
                            <th className={thClass}>Percentage</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {byGrade.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-6 text-center text-sm text-gray-600"
                                >
                                    No data.
                                </td>
                            </tr>
                        )}
                        {byGrade.map((grade) => (
                            <tr key={grade.grade_level}>
                                <td
                                    className={`${tdClass} font-semibold text-navy`}
                                >
                                    {grade.grade_level}th
                                </td>
                                <td className={tdClass}>{grade.total}</td>
                                <td className={tdClass}>{grade.present}</td>
                                <td
                                    className={
                                        'whitespace-nowrap px-4 py-3 text-sm font-semibold ' +
                                        (grade.percentage === null
                                            ? 'text-gray-400'
                                            : grade.percentage < THRESHOLD
                                              ? 'text-red-600'
                                              : 'text-navy')
                                    }
                                >
                                    {formatPercentage(grade.percentage)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}