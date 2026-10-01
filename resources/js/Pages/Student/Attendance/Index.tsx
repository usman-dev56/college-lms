import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface OverallSummary {
    present: number;
    absent: number;
    late: number;
    leave: number;
    total: number;
    percentage: number | null;
    is_below_threshold: boolean;
}

interface SubjectSummary {
    class_subject_id: number;
    subject_name: string;
    subject_code: string | null;
    class_display_name: string;
    present: number;
    absent: number;
    late: number;
    leave: number;
    total: number;
    percentage: number | null;
    is_below_threshold: boolean;
}

type StudentAttendancePageProps = {
    student: {
        name: string;
        roll_number: string | null;
        batch_name: string | null;
    } | null;
    summary: {
        overall: OverallSummary;
        by_subject: SubjectSummary[];
    } | null;
    message: string | null;
};

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-4 py-3 text-sm text-gray-700';

/**
 * A percentage with two decimals, or a dash when there is nothing to divide.
 *
 * Null is not 0: a student with no marks has an unknown attendance, and
 * printing "0.00%" would tell them they have missed every period.
 */
function formatPercentage(value: number | null): string {
    return value === null ? '—' : `${value.toFixed(2)}%`;
}

/** One of the four status counts in the breakdown row. */
function Count({
    label,
    value,
    className,
}: {
    label: string;
    value: number;
    className?: string;
}) {
    return (
        <div className="text-center">
            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                {label}
            </p>
            <p className={`mt-1 font-serif text-xl font-semibold ${className}`}>
                {value}
            </p>
        </div>
    );
}
export default function AttendanceIndex() {
    const { student, summary, message } =
        usePage<PageProps<StudentAttendancePageProps>>().props;

    const overall = summary?.overall;
    const bySubject = summary?.by_subject ?? [];
    const hasRecords = overall !== undefined && overall.total > 0;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            My Attendance
                        </h2>
                        {student && (
                            <p className="mt-1 text-sm text-gray-600">
                                {student.name}
                                {student.roll_number
                                    ? ` · Roll ${student.roll_number}`
                                    : ''}
                                {student.batch_name
                                    ? ` · ${student.batch_name}`
                                    : ''}
                            </p>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="My Attendance" />

            {student === null || message ? (
                <div className="flex flex-col items-center justify-center gap-2 rounded-lg bg-white p-12 text-center shadow-sm ring-1 ring-gray-200">
                    <p className="text-sm font-medium text-gray-700">
                        {message ??
                            'Your student profile has not been created yet.'}
                    </p>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    {/* The overall figure */}
                    <div className={cardClass}>
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Overall Attendance
                                </p>

                                {hasRecords ? (
                                    <p
                                        className={
                                            'mt-2 font-serif text-4xl font-semibold ' +
                                            (overall.is_below_threshold
                                                ? 'text-red-600'
                                                : 'text-navy')
                                        }
                                    >
                                        {formatPercentage(overall.percentage)}
                                    </p>
                                ) : (
                                    /*
                                        No marks at all. Deliberately not a
                                        zero: the honest answer is that nobody
                                        has recorded anything yet.
                                    */
                                    <p className="mt-2 font-serif text-2xl font-semibold text-gray-400">
                                        No attendance recorded yet
                                    </p>
                                )}
                            </div>

                            {hasRecords &&
                                (overall.is_below_threshold ? (
                                    <span className="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">
                                        Below 75% — At Risk
                                    </span>
                                ) : (
                                    <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                        Above threshold
                                    </span>
                                ))}
                        </div>

                        {hasRecords && (
                            <div className="mt-6 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 sm:grid-cols-5">
                                <Count
                                    label="Present"
                                    value={overall.present}
                                    className="text-green-700"
                                />
                                <Count
                                    label="Absent"
                                    value={overall.absent}
                                    className="text-red-700"
                                />
                                <Count
                                    label="Late"
                                    value={overall.late}
                                    className="text-amber-700"
                                />
                                <Count
                                    label="Leave"
                                    value={overall.leave}
                                    className="text-blue-700"
                                />
                                <Count
                                    label="Total"
                                    value={overall.total}
                                    className="text-navy"
                                />
                            </div>
                        )}

                        {hasRecords && (
                            <p className="mt-4 text-xs text-gray-500">
                                Late arrivals and approved leave both count as
                                attended. Only absence lowers the percentage.
                            </p>
                        )}
                    </div>
{/* Per subject */}
                    <div className="flex flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="font-serif text-base font-semibold text-navy">
                                By Subject
                            </h3>
                        </div>

                        {bySubject.length === 0 ? (
                            <p className="p-12 text-center text-sm text-gray-600">
                                No attendance records yet. Once your teachers
                                start marking attendance, it will appear here.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-surface">
                                        <tr>
                                            <th className={thClass}>Subject</th>
                                            <th className={thClass}>Class</th>
                                            <th className={thClass}>Present</th>
                                            <th className={thClass}>Absent</th>
                                            <th className={thClass}>Late</th>
                                            <th className={thClass}>Leave</th>
                                            <th className={thClass}>Total</th>
                                            <th className={thClass}>
                                                Percentage
                                            </th>
                                            <th className={thClass}>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {bySubject.map((row) => (
                                            <tr key={row.class_subject_id}>
                                                <td
                                                    className={`${tdClass} font-medium text-gray-900`}
                                                >
                                                    {row.subject_name}
                                                    {row.subject_code
                                                        ? ` (${row.subject_code})`
                                                        : ''}
                                                </td>
                                                <td className={tdClass}>
                                                    {
                                                        row.class_display_name
                                                    }
                                                </td>
                                                <td className={tdClass}>
                                                    {row.present}
                                                </td>
                                                <td className={tdClass}>
                                                    {row.absent}
                                                </td>
                                                <td className={tdClass}>
                                                    {row.late}
                                                </td>
                                                <td className={tdClass}>
                                                    {row.leave}
                                                </td>
                                                <td className={tdClass}>
                                                    {row.total}
                                                </td>
<td
                                                    className={
                                                        'whitespace-nowrap px-4 py-3 text-sm font-semibold ' +
                                                        (row.percentage ===
                                                        null
                                                            ? 'text-gray-400'
                                                            : row.is_below_threshold
                                                              ? 'text-red-600'
                                                              : 'text-navy')
                                                    }
                                                >
                                                    {
                                                        formatPercentage(
                                                            row.percentage,
                                                        )
                                                    }
                                                </td>
                                                <td className={tdClass}>
                                                    {row.percentage === null ? (
                                                        <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                                            —
                                                        </span>
                                                    ) : row.is_below_threshold ? (
                                                        <span className="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">
                                                            At Risk
                                                        </span>
                                                    ) : (
                                                        <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                                            OK
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}