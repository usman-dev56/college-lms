import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    Cell,
    CartesianGrid,
    Legend,
    Pie,
    PieChart,
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
        total_admissions_pending: number;
    };
    attendance: {
        today_total: number;
        today_present: number;
        today_percentage: number | null;
    };
    studentsByStream: { stream_name: string; student_count: number }[];
    attendanceTrend: {
        date: string;
        date_label: string;
        total: number;
        present: number;
        percentage: number | null;
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
 * The pie's slice palette.
 *
 * Five shades drawn from the college's own navy and gold rather than a
 * general-purpose categorical ramp, so the chart reads as part of this site
 * instead of one dropped in from elsewhere. The grey at the end is the
 * fallback for a sixth stream, which should read as "other" rather than as a
 * colour the college chose.
 */
const SLICE_COLORS = [NAVY, '#1A3D78', GOLD, '#8B6F1A', '#6B7280'];

/**
 * Status badge colours, keyed by the admission status.
 *
 * The neutral grey fallback matters: a status added later must still render as
 * a badge rather than as unstyled text.
 */
const STATUS_COLORS: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    reviewed: 'bg-blue-100 text-blue-800',
    accepted: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    enrolled: 'bg-purple-100 text-purple-800',
};

const cardClass = 'rounded-lg bg-white shadow-sm ring-1 ring-gray-200';
export default function Dashboard(props: DashboardProps) {
    const { user, metrics, attendance, studentsByStream, attendanceTrend } =
        props;
    const { topDefaulters, recentAdmissions } = props;

    // A day with nothing marked keeps its null, and Recharts draws null as a
    // gap. That is the honest reading: an unmarked Sunday is not a day on
    // which nobody came to school.
    const trendData = attendanceTrend.map((point) => ({
        ...point,
        chartPercentage: point.percentage ?? null,
    }));

    const trendHasData = attendanceTrend.some((d) => d.total > 0);
    const streamHasData = studentsByStream.length > 0;

    const today = new Date().toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return (
        <AuthenticatedLayout
            header={
                <div className="w-full">
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Administrator Dashboard
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Welcome back, {user.name} — {today}
                    </p>
                </div>
            }
        >
            <Head title="Admin Dashboard" />

            <div className="flex flex-col gap-4">
                {/* Row 1 — headline figures */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

                    {/* The one card whose value can be "no answer": an
                        unmarked morning has an unknown attendance, which is
                        not the same claim as 0%. */}
                    <MetricCard
                        label="Today's Attendance"
                        value={
                            attendance.today_percentage === null
                                ? '—'
                                : `${attendance.today_percentage}%`
                        }
                        subtitle={
                            attendance.today_total === 0
                                ? 'No records yet'
                                : `${attendance.today_present} of ${attendance.today_total} present`
                        }
                        tone={
                            attendance.today_percentage === null
                                ? 'text-gray-400'
                                : attendance.today_percentage < 75
                                  ? 'text-red-600'
                                  : 'text-navy'
                        }
                    />
                </div>

                {/* Row 2 — charts */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <TrendChart data={trendData} hasData={trendHasData} />
                    <StreamChart
                        data={studentsByStream}
                        hasData={streamHasData}
                    />
                </div>

                {/* Row 3 — what needs a decision */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
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
 * Daily attendance rate across the last thirty days.
 *
 * The percentage is drawn rather than the headcount, because the question is
 * "how full was the college", and a day of 400 marks at 92% and a day of 30 at
 * 92% are the same answer on this chart even though one is a far busier day.
 */
function TrendChart({
    data,
    hasData,
}: {
    data: (DashboardProps['attendanceTrend'][number] & {
        chartPercentage: number | null;
    })[];
    hasData: boolean;
}) {
    return (
        <div className={`${cardClass} p-5`}>
            <h3 className="font-serif text-base font-semibold text-navy">
                Attendance Trend (30 days)
            </h3>

            {!hasData ? (
                <EmptyChart message="No attendance data yet." />
            ) : (
                <div className="mt-4 h-[280px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data}>
                            <CartesianGrid
                                strokeDasharray="3 3"
                                stroke="#E5E7EB"
                                vertical={false}
                            />
                            <XAxis
                                dataKey="date_label"
                                tick={{ fontSize: 11 }}
                                interval="preserveStartEnd"
                                stroke="#6B7280"
                            />
                            <YAxis
                                domain={[0, 100]}
                                tick={{ fontSize: 11 }}
                                stroke="#6B7280"
                                unit="%"
                            />
                            <Tooltip
                                cursor={{
                                    fill: 'rgba(15, 43, 95, 0.06)',
                                }}
                                contentStyle={{
                                    borderRadius: 6,
                                    border: '1px solid #E5E7EB',
                                    fontSize: 12,
                                }}
                            />
                            <Bar
                                dataKey="chartPercentage"
                                fill={NAVY}
                                radius={[3, 3, 0, 0]}
                                name="Attendance %"
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}

/**
 * How the roll divides across streams.
 *
 * The count travels on the slice as well as in the legend, because a legend
 * alone makes the reader do arithmetic to answer "how many".
 */
function StreamChart({
    data,
    hasData,
}: {
    data: DashboardProps['studentsByStream'];
    hasData: boolean;
}) {
    return (
        <div className={`${cardClass} p-5`}>
            <h3 className="font-serif text-base font-semibold text-navy">
                Students by Stream
            </h3>

            {!hasData ? (
                <EmptyChart message="No students enrolled yet." />
            ) : (
                <div className="mt-4 h-[280px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={data}
                                dataKey="student_count"
                                nameKey="stream_name"
                                innerRadius={55}
                                outerRadius={90}
                                paddingAngle={2}
                                stroke="#FFFFFF"
                                strokeWidth={2}
                            >
                                {data.map((entry, index) => (
                                    <Cell
                                        key={entry.stream_name}
                                        fill={
                                            SLICE_COLORS[
                                                index % SLICE_COLORS.length
                                            ]
                                        }
                                    />
                                ))}
                            </Pie>
                            <Tooltip
                                contentStyle={{
                                    borderRadius: 6,
                                    border: '1px solid #E5E7EB',
                                    fontSize: 12,
                                }}
                            />
                            <Legend
                                verticalAlign="bottom"
                                height={28}
                                iconType="circle"
                                wrapperStyle={{ fontSize: 12 }}
                            />
                        </PieChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}

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
    userPlus: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    clipboard:
        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 8h6m-6 4h4',
    chart:
        'M9 19v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
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
            label: 'Attendance',
            href: route('admin.attendance.index'),
            icon: ICONS.clipboard,
        },
        {
            label: 'View Reports',
            href: route('admin.reports.attendance.daily'),
            icon: ICONS.chart,
        },
        {
            label: 'Admissions',
            href: route('admin.admissions.index'),
            icon: ICONS.inbox,
        },
    ];

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {actions.map((action) => (
                <Link
                    key={action.label}
                    href={action.href}
                    className={`${cardClass} flex items-center gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-md`}
                >
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-surface text-navy">
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={1.8}
                            viewBox="0 0 24 24"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        >
                            <path d={action.icon} />
                        </svg>
                    </span>
                    <span className="text-sm font-medium text-navy">
                        {action.label}
                    </span>
                </Link>
            ))}
        </div>
    );
}