import ReportsTabs from '@/Components/ReportsTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface TrendPoint {
    date: string;
    date_label: string;
    total: number;
    present: number;
    absent: number;
    percentage: number | null;
}

type TrendReportPageProps = {
    report: {
        days: number;
        from: string;
        to: string;
        data: TrendPoint[];
        class_id: number | null;
        class: { id: number; display_name: string } | null;
    };
    classes: { id: number; display_name: string }[];
    filters: {
        days: number;
        class_id: number | null;
    };
};

const WINDOW_DAYS = [7, 14, 30, 60, 90];

const NAVY = '#0F2B5F';

const THRESHOLD = 75;

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-4 py-3 text-sm text-gray-700';

const inputClass =
    'mt-1 block h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

function formatPercentage(value: number | null): string {
    return value === null ? '—' : `${value.toFixed(2)}%`;
}
export default function Trend() {
    const { report, classes, filters } =
        usePage<PageProps<TrendReportPageProps>>().props;

    const [days, setDays] = useState(filters.days.toString());
    const [classId, setClassId] = useState(
        filters.class_id?.toString() ?? '',
    );

    const apply = () => {
        router.get(
            route('admin.reports.attendance.trend'),
            { days, class_id: classId },
            { preserveState: true, preserveScroll: true },
        );
    };

    const exportHref = route('admin.reports.attendance.trend.export', {
        days: report.days,
        class_id: report.class_id ?? '',
    });

    const label =
        'block text-xs font-semibold uppercase tracking-wider text-gray-500';

    // A day with nothing marked keeps its null rather than becoming a zero.
    // Recharts draws null as a gap, and a zero-height bar sitting on the axis
    // would read as "nobody came in" when it really means "nobody marked it".
    const chartData = report.data.map((point) => ({
        ...point,
        chartPercentage: point.percentage ?? null,
    }));

    const daysWithRecords = report.data.filter(
        (d) => d.total > 0,
    ).length;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Attendance Trend
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
            <Head title="Attendance Trend" />

            <div className="flex h-full min-h-0 flex-col gap-4">
                <ReportsTabs active="trend" />

                <FilterBar
                    days={days}
                    classId={classId}
                    classes={classes}
                    label={label}
                    onDays={setDays}
                    onClass={setClassId}
                    onApply={apply}
                />

                <TrendChart
                    chartData={chartData}
                    daysWithRecords={daysWithRecords}
                    totalDays={report.data.length}
                    title={report.class ? report.class.display_name : 'All classes'}
                />

                <DailyTable data={report.data} />
            </div>
        </AuthenticatedLayout>
    );
}
/** The window and class pickers. */
function FilterBar({
    days,
    classId,
    classes,
    label,
    onDays,
    onClass,
    onApply,
}: {
    days: string;
    classId: string;
    classes: { id: number; display_name: string }[];
    label: string;
    onDays: (v: string) => void;
    onClass: (v: string) => void;
    onApply: () => void;
}) {
    return (
        <div className="shrink-0 flex flex-wrap items-end gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
            <div className="min-w-[8rem]">
                <label htmlFor="days" className={label}>
                    Days
                </label>
                <select
                    id="days"
                    className={`block w-full ${inputClass}`}
                    value={days}
                    onChange={(e) => onDays(e.target.value)}
                >
                    {WINDOW_DAYS.map((d) => (
                        <option key={d} value={d}>
                            Last {d} days
                        </option>
                    ))}
                </select>
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

/**
 * The daily attendance rate as a bar chart.
 *
 * One bar per day of the window, so the bar count tracks the selected range
 * rather than the number of days that happened to be marked.
 */
function TrendChart({
    chartData,
    daysWithRecords,
    totalDays,
    title,
}: {
    chartData: (TrendPoint & { chartPercentage: number | null })[];
    daysWithRecords: number;
    totalDays: number;
    title: string;
}) {
    return (
        <div className="shrink-0 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="font-serif text-base font-semibold text-navy">
                    {title}
                </h3>
                <p className="text-sm text-gray-600">
                    {daysWithRecords} of {totalDays} days with records
                </p>
            </div>

            {daysWithRecords === 0 ? (
                <div className="flex h-[320px] flex-col items-center justify-center text-center">
                    <p className="text-sm font-medium text-gray-700">
                        No attendance recorded in this period.
                    </p>
                    <p className="mt-1 text-sm text-gray-500">
                        Nothing to chart until marks are entered.
                    </p>
                </div>
            ) : (
                <div className="h-[320px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={chartData}>
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
                                content={<TrendTooltip />}
                                cursor={{ fill: 'rgba(15, 43, 95, 0.06)' }}
                            />
                            <Bar
                                dataKey="chartPercentage"
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
 * The hover card.
 *
 * Shows the day's rate alongside the counts behind it, because a percentage
 * on its own hides whether it came from 500 students or 5.
 */
function TrendTooltip({
    active,
    payload,
}: {
    active?: boolean;
    payload?: { payload: TrendPoint }[];
}) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    const day = payload[0].payload;

    return (
        <div className="rounded-md border border-gray-200 bg-white px-3 py-2 shadow-sm">
            <p className="text-xs font-semibold text-gray-900">{day.date}</p>
            <p className="mt-1 text-xs text-gray-700">
                Attendance:{' '}
                <span className="font-semibold">
                    {formatPercentage(day.percentage)}
                </span>
            </p>
            <p className="text-xs text-gray-700">
                Present: <span className="font-semibold">{day.present}</span>
            </p>
            <p className="text-xs text-gray-700">
                Total: <span className="font-semibold">{day.total}</span>
            </p>
        </div>
    );
}

/** The per-day figures behind the chart. */
function DailyTable({ data }: { data: TrendPoint[] }) {
    return (
        <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
            <div className="shrink-0 border-b border-gray-200 px-4 py-3">
                <h3 className="font-serif text-base font-semibold text-navy">
                    Daily Detail
                </h3>
            </div>

            <div className="min-h-0 flex-1 overflow-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="sticky top-0 z-10 bg-surface">
                        <tr>
                            <th className={thClass}>Date</th>
                            <th className={thClass}>Total</th>
                            <th className={thClass}>Present</th>
                            <th className={thClass}>Absent</th>
                            <th className={thClass}>Percentage</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {data.map((day) => (
                            <tr key={day.date}>
                                <td
                                    className={`${tdClass} font-medium text-gray-900`}
                                >
                                    {day.date}
                                </td>
                                <td className={tdClass}>{day.total}</td>
                                <td className={tdClass}>{day.present}</td>
                                <td className={tdClass}>{day.absent}</td>
                                <td
                                    className={
                                        'whitespace-nowrap px-4 py-3 text-sm font-semibold ' +
                                        (day.percentage === null
                                            ? 'text-gray-400'
                                            : day.percentage < THRESHOLD
                                              ? 'text-red-600'
                                              : 'text-navy')
                                    }
                                >
                                    {formatPercentage(day.percentage)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}