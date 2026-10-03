import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface DashboardProps {
    user: { name: string };
    metrics: {
        total_students: number;
        total_teachers: number;
        total_classes: number;
        total_enrolled: number;
        total_unassigned: number;
    };
    pendingAdmissions: number;
    studentsByStream: {
        stream_name: string;
        grade_11_count: number;
        grade_12_count: number;
        total: number;
    }[];
    classCapacity: {
        class_id: number;
        class_display_name: string;
        capacity: number | null;
        enrolled_count: number;
        utilization_percentage: number | null;
    }[];
    topDefaulters: {
        student_name: string;
        roll_number: string;
        class_display_name: string | null;
        percentage: number;
        shortfall: number;
    }[];
    recentAdmissions: {
        id: number;
        applicant_name: string;
        stream_name: string | null;
        batch_name: string | null;
        status: string;
        created_at: string | null;
    }[];
}

const NAVY = '#0F2B5F';
const GOLD = '#C9A227';

/**
 * Status badge colours, keyed by admission status.
 *
 * The grey fallback matters: a status added later must still render as a badge
 * rather than as unstyled text.
 */
const STATUS_COLORS: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    reviewed: 'bg-blue-100 text-blue-800',
    accepted: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    enrolled: 'bg-purple-100 text-purple-800',
};

const cardClass = 'rounded-lg bg-white shadow-sm ring-1 ring-gray-200';
export default function Dashboard({
    user,
    metrics,
    pendingAdmissions,
    studentsByStream,
    classCapacity,
    topDefaulters,
    recentAdmissions,
}: DashboardProps) {
    /*
        Only classes with a capacity set can be charted. One with no capacity
        is not 0% full - it is undecided - and putting it on the axis beside
        real classes would claim a fact nobody entered. They are listed
        nowhere here, which is why the chart's empty state mentions capacity
        rather than simply saying "no data".
    */
    const chartableClasses = classCapacity.filter(
        (c): c is DashboardProps['classCapacity'][number] & {
            utilization_percentage: number;
        } => c.utilization_percentage !== null,
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="w-full">
                    <h2 className="font-serif text-2xl font-semibold text-navy">
                        Administrator Dashboard
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Welcome back, {user.name}
                    </p>
                </div>
            }
        >
            <Head title="Admin Dashboard" />

            <div className="flex h-full min-h-0 flex-col gap-4">
                {pendingAdmissions > 0 && (
                    <PendingBanner count={pendingAdmissions} />
                )}

                {/* Row 1 — headline figures */}
                <div className="grid shrink-0 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        label="Total Students"
                        value={metrics.total_students}
                        subtitle="Active students"
                    />
                    <MetricCard
                        label="Total Teachers"
                        value={metrics.total_teachers}
                        subtitle="Active teachers"
                    />
                    <MetricCard
                        label="Total Classes"
                        value={metrics.total_classes}
                        subtitle="In current session"
                    />
                    <MetricCard
                        label="Total Enrollments"
                        value={metrics.total_enrolled}
                        subtitle={
                            metrics.total_unassigned > 0
                                ? `${metrics.total_unassigned} unassigned`
                                : 'All students enrolled'
                        }
                        // The one card whose subtitle can be a problem: an
                        // unassigned student is work still to be done.
                        tone={
                            metrics.total_unassigned > 0
                                ? 'text-amber-600'
                                : 'text-navy'
                        }
                    />
                </div>

                {/* Row 2 — charts */}
                <div className="grid shrink-0 grid-cols-1 gap-4 lg:grid-cols-2">
                    <StreamChart data={studentsByStream} />
                    <CapacityChart data={chartableClasses} />
                </div>

                {/* Row 3 — what needs a decision */}
                <div className="grid shrink-0 grid-cols-1 gap-4 lg:grid-cols-2">
                    <DefaultersPanel defaulters={topDefaulters} />
                    <AdmissionsPanel admissions={recentAdmissions} />
                </div>

                {/* Row 4 — shortcuts */}
                <QuickActions />
            </div>
        </AuthenticatedLayout>
    );
}

/** One headline figure, with its caption underneath. */
function MetricCard({
    label,
    value,
    subtitle,
    tone = 'text-navy',
}: {
    label: string;
    value: string | number;
    subtitle: string;
    tone?: string;
}) {
    return (
        <div className={`${cardClass} p-5`}>
            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                {label}
            </p>
            <p className={`mt-2 font-serif text-2xl font-semibold ${tone}`}>
                {value}
            </p>
            <p className="mt-1 text-xs text-gray-500">{subtitle}</p>
        </div>
    );
}

/**
 * The amber strip above the metrics, shown only when there is a queue.
 *
 * Deliberately the first thing on the page rather than another card: a pending
 * application is the one thing on this dashboard that is a task rather than a
 * statistic, and burying it in a row of counts would let it be missed.
 */
function PendingBanner({ count }: { count: number }) {
    return (
        <div className="shrink-0 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-5 py-3">
            <p className="text-sm font-medium text-amber-900">
                You have {count} pending admission{count === 1 ? '' : 's'}{' '}
                waiting for review.
            </p>
            <Link
                href={`${route('admin.admissions.index')}?status=pending`}
                className="text-sm font-semibold text-amber-900 underline hover:no-underline"
            >
                Review now
            </Link>
        </div>
    );
}
/**
 * The roll per stream, split by grade.
 *
 * Stacked rather than side-by-side because the two questions are different:
 * the total height of a bar is "how big is this stream", and the boundary
 * inside it is "how much of that is next year's problem".
 */
function StreamChart({ data }: { data: DashboardProps['studentsByStream'] }) {
    return (
        <div className={`${cardClass} p-5`}>
            <h3 className="font-serif text-base font-semibold text-navy">
                Students by Stream
            </h3>

            {data.length === 0 ? (
                <EmptyChart message="No students enrolled yet." />
            ) : (
                <div className="mt-4 h-[280px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data} margin={{ bottom: 5 }}>
                            <CartesianGrid
                                strokeDasharray="3 3"
                                stroke="#E5E7EB"
                                vertical={false}
                            />
                            <XAxis
                                dataKey="stream_name"
                                tick={{ fontSize: 11 }}
                                stroke="#6B7280"
                            />
                            <YAxis
                                tick={{ fontSize: 11 }}
                                stroke="#6B7280"
                                allowDecimals={false}
                            />
                            <Tooltip
                                cursor={{ fill: 'rgba(15, 43, 95, 0.06)' }}
                                contentStyle={tooltipStyle}
                            />
                            <Legend
                                verticalAlign="top"
                                height={28}
                                iconType="circle"
                                wrapperStyle={{ fontSize: 12 }}
                            />
                            {/* The shared stackId is what stacks them; the two
                                columns are otherwise just two bars. */}
                            <Bar
                                dataKey="grade_11_count"
                                name="Grade 11"
                                stackId="grades"
                                fill={NAVY}
                            />
                            <Bar
                                dataKey="grade_12_count"
                                name="Grade 12"
                                stackId="grades"
                                fill={GOLD}
                                radius={[3, 3, 0, 0]}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}

/**
 * How full each class is, as a percentage of its stated capacity.
 *
 * A percentage rather than a headcount, because the point of capacity is the
 * ratio: 40 students in a class of 40 and 30 in a class of 30 are the same
 * problem, and only the percentage says so.
 */
function CapacityChart({
    data,
}: {
    data: (DashboardProps['classCapacity'][number] & {
        utilization_percentage: number;
    })[];
}) {
    return (
        <div className={`${cardClass} p-5`}>
            <h3 className="font-serif text-base font-semibold text-navy">
                Class Capacity
            </h3>

            {data.length === 0 ? (
                <EmptyChart message="No classes yet." />
            ) : (
                <div className="mt-4 h-[320px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={data}
                            margin={{ bottom: 5, left: -10 }}
                        >
                            <CartesianGrid
                                strokeDasharray="3 3"
                                stroke="#E5E7EB"
                                vertical={false}
                            />
                            <XAxis
                                dataKey="class_display_name"
                                interval={0}
                                angle={-30}
                                height={80}
                                textAnchor="end"
                                tick={{ fontSize: 10 }}
                                stroke="#6B7280"
                            />
                            <YAxis
                                domain={[0, 100]}
                                tick={{ fontSize: 11 }}
                                stroke="#6B7280"
                                unit="%"
                            />
                            <Tooltip
                                cursor={{ fill: 'rgba(15, 43, 95, 0.06)' }}
                                content={<CapacityTooltip />}
                            />
                            <Bar
                                dataKey="utilization_percentage"
                                fill={NAVY}
                                radius={[3, 3, 0, 0]}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}
/**
 * The hover card for a class bar.
 *
 * Shows the headcount beside the percentage, because a bar labelled 40% does
 * not say whether that is 20 of 50 or 4 of 10, and the two call for very
 * different responses.
 */
function CapacityTooltip({
    active,
    payload,
}: {
    active?: boolean;
    payload?: { payload: DashboardProps['classCapacity'][number] }[];
}) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    const row = payload[0].payload;

    return (
        <div className="rounded-md border border-gray-200 bg-white px-3 py-2 shadow-sm">
            <p className="text-xs font-semibold text-gray-900">
                {row.class_display_name}
            </p>
            <p className="mt-1 text-xs text-gray-700">
                Enrolled:{' '}
                <span className="font-semibold">{row.enrolled_count}</span>
                {row.capacity !== null && ` of ${row.capacity}`}
            </p>
            <p className="text-xs text-gray-700">
                Utilisation:{' '}
                <span className="font-semibold">
                    {row.utilization_percentage}%
                </span>
            </p>
        </div>
    );
}

const tooltipStyle = {
    borderRadius: 6,
    border: '1px solid #E5E7EB',
    fontSize: 12,
} as const;

/** The shared "there is nothing to draw" state. */
function EmptyChart({ message }: { message: string }) {
    return (
        <div className="flex h-[280px] flex-col items-center justify-center text-center">
            <p className="text-sm font-medium text-gray-700">{message}</p>
            <p className="mt-1 text-sm text-gray-500">
                It will appear here once records exist.
            </p>
        </div>
    );
}

/**
 * The students furthest below the 75% mark.
 *
 * Shown with the shortfall rather than the percentage alone, because a phone
 * call is about "six points short", not "69%". The green empty state is
 * deliberate: "no defaulters" is good news and should look like it.
 */
function DefaultersPanel({
    defaulters,
}: {
    defaulters: DashboardProps['topDefaulters'];
}) {
    return (
        <div className={`${cardClass} flex flex-col`}>
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                <h3 className="font-serif text-base font-semibold text-navy">
                    Attendance Defaulters (Below 75%)
                </h3>
                {defaulters.length > 0 && (
                    <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                        {defaulters.length}
                    </span>
                )}
            </div>

            {defaulters.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center p-10 text-center">
                    <p className="text-sm font-medium text-green-700">
                        No defaulters. Well done!
                    </p>
                    <p className="mt-1 text-sm text-gray-500">
                        Every student on the roll is at or above the mark.
                    </p>
                </div>
            ) : (
                <table className="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th className="px-5 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                Student
                            </th>
                            <th className="px-5 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                Class
                            </th>
                            <th className="px-5 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                Percentage
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {defaulters.map((student) => (
                            <tr key={student.roll_number}>
                                <td className="px-5 py-2.5 text-sm">
                                    <span className="font-medium text-gray-900">
                                        {student.student_name}
                                    </span>
                                    <span className="ml-2 text-xs text-gray-500">
                                        {student.roll_number}
                                    </span>
                                </td>
                                <td className="px-5 py-2.5 text-sm text-gray-600">
                                    {student.class_display_name ?? '—'}
                                </td>
                                <td className="px-5 py-2.5 text-right text-sm">
                                    <span className="font-semibold text-red-600">
                                        {student.percentage.toFixed(2)}%
                                    </span>
                                    <span className="ml-2 text-xs text-gray-500">
                                        {student.shortfall.toFixed(1)} short
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            <div className="mt-auto border-t border-gray-200 px-5 py-3">
                <Link
                    href={route('admin.attendance.defaulters')}
                    className="text-sm font-medium text-navy hover:underline"
                >
                    View all defaulters
                </Link>
            </div>
        </div>
    );
}
/** The newest applications, with where each one has got to. */
function AdmissionsPanel({
    admissions,
}: {
    admissions: DashboardProps['recentAdmissions'];
}) {
    return (
        <div className={`${cardClass} flex flex-col`}>
            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                <h3 className="font-serif text-base font-semibold text-navy">
                    Recent Admissions
                </h3>
            </div>

            {admissions.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center p-10 text-center">
                    <p className="text-sm font-medium text-gray-700">
                        No applications yet.
                    </p>
                </div>
            ) : (
                <ul className="divide-y divide-gray-100">
                    {admissions.map((admission) => (
                        <li
                            key={admission.id}
                            className="flex items-center justify-between gap-3 px-5 py-3"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-gray-900">
                                    {admission.applicant_name}
                                </p>
                                <p className="truncate text-xs text-gray-500">
                                    {admission.stream_name ?? 'No stream'}
                                    {admission.batch_name &&
                                        ` · ${admission.batch_name}`}
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                {admission.created_at && (
                                    <span className="hidden text-xs text-gray-400 sm:inline">
                                        {admission.created_at}
                                    </span>
                                )}
                                <span
                                    className={
                                        'rounded-full px-2 py-0.5 text-xs font-semibold capitalize ' +
                                        (STATUS_COLORS[admission.status] ??
                                            'bg-gray-100 text-gray-800')
                                    }
                                >
                                    {admission.status}
                                </span>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-auto border-t border-gray-200 px-5 py-3">
                <Link
                    href={route('admin.admissions.index')}
                    className="text-sm font-medium text-navy hover:underline"
                >
                    View all admissions
                </Link>
            </div>
        </div>
    );
}

/** Inline icons, so no icon package is needed for four glyphs. */
const ICONS = {
    userPlus:
        'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    chart: 'M9 19v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    clipboard:
        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 8h6m-6 4h4',
    inbox: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-4l-2 3h-4l-2-3H4',
};

/**
 * The four things an administrator opens this screen to do.
 *
 * Attendance links to the office register rather than the teacher's marking
 * page: an admin has no periods of their own, so the teacher URL would show
 * them an empty day.
 */
function QuickActions() {
    const actions = [
        {
            label: 'Add Student',
            href: route('admin.students.create'),
            icon: ICONS.userPlus,
        },
        {
            label: 'View Reports',
            href: route('admin.reports.attendance.daily'),
            icon: ICONS.chart,
        },
        {
            label: 'Attendance',
            href: route('admin.attendance.index'),
            icon: ICONS.clipboard,
        },
        {
            label: 'Admissions',
            href: route('admin.admissions.index'),
            icon: ICONS.inbox,
        },
    ];

    return (
        <div className="grid shrink-0 grid-cols-2 gap-3 sm:grid-cols-4">
            {actions.map((action) => (
                <Link
                    key={action.label}
                    href={action.href}
                    className={`${cardClass} flex items-center gap-3 p-4 text-navy transition hover:bg-surface`}
                >
                    <svg
                        className="h-5 w-5 shrink-0"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.8}
                        viewBox="0 0 24 24"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                        <path d={action.icon} />
                    </svg>
                    <span className="text-sm font-medium">{action.label}</span>
                </Link>
            ))}
        </div>
    );
}