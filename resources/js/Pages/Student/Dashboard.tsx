import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

interface StudentSummary {
    name: string;
    roll_number: string;
    batch_name: string | null;
    status: string;
}

interface ClassInfo {
    id: number;
    display_name: string;
    grade_level: number;
    section: string | null;
    stream_name: string | null;
    room: string | null;
}

interface ScheduleEntry {
    period_label: string | null;
    start_time: string | null;
    end_time: string | null;
    is_break: boolean;
    subject_name: string | null;
    teacher_name: string | null;
    room: string | null;
}

interface DaySummary {
    day_number: number;
    day_name: string;
    periods_count: number;
}

type StudentDashboardPageProps = {
    student: StudentSummary | null;
    classInfo: ClassInfo | null;
    todaySchedule: ScheduleEntry[];
    weekSummary: DaySummary[];
    today_name: string;
    message: string | null;
};

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-4 py-3 text-sm text-gray-700';

/** A titled panel. */
function Card({
    title,
    children,
    className = '',
}: {
    title?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={`${cardClass} ${className}`}>
            {title && (
                <h3 className="font-serif text-base font-semibold text-navy">
                    {title}
                </h3>
            )}
            {children}
        </div>
    );
}

/** One of the three summary tiles at the top. */
function SummaryCard({ label, value }: { label: string; value: string }) {
    const empty = value === '' || value === '-';

    return (
        <div className={cardClass}>
            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                {label}
            </p>
            <p
                className={
                    'mt-2 font-serif text-xl font-semibold ' +
                    (empty ? 'text-gray-400' : 'text-navy')
                }
            >
                {empty ? 'Not set' : value}
            </p>
        </div>
    );
}

export default function Dashboard() {
    const {
        student,
        classInfo,
        todaySchedule,
        weekSummary,
        today_name,
        message,
    } = usePage<PageProps<StudentDashboardPageProps>>().props;

    // Sunday has no entry in the summary, so a Sunday visit matches nothing
    // and no row is highlighted.
    const todayNumber = weekSummary.find(
        (day) => day.day_name === today_name,
    )?.day_number;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Dashboard
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            {student
                                ? `Welcome, ${student.name}`
                                : 'Student portal'}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Dashboard" />

            {message ? (
                <div className="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-gray-200">
                    <p className="text-sm text-gray-700">{message}</p>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    {/* Summary row */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <SummaryCard
                            label="Roll Number"
                            value={student?.roll_number ?? ''}
                        />
                        <SummaryCard
                            label="Batch"
                            value={student?.batch_name ?? ''}
                        />
                        <SummaryCard
                            label="Class"
                            value={classInfo?.display_name ?? ''}
                        />
                    </div>

                    {/* Today's schedule and the week at a glance */}
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <Card
                            title={`Today's Schedule${today_name ? ` (${today_name})` : ''}`}
                            className="lg:col-span-2"
                        >
                            {todaySchedule.length === 0 ? (
                                <p className="mt-4 text-sm text-gray-600">
                                    No classes scheduled today.
                                </p>
                            ) : (
                                <div className="mt-4 overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr>
                                                <th className={thClass}>
                                                    Period
                                                </th>
                                                <th className={thClass}>
                                                    Time
                                                </th>
                                                <th className={thClass}>
                                                    Subject
                                                </th>
                                                <th className={thClass}>
                                                    Teacher
                                                </th>
                                                <th className={thClass}>
                                                    Room
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {todaySchedule.map(
                                                (entry, index) => (
                                                    <tr
                                                        key={`${entry.period_label}-${index}`}
                                                        className={
                                                            entry.is_break
                                                                ? 'bg-surface text-gray-400'
                                                                : 'bg-white'
                                                        }
                                                    >
                                                        <td
                                                            className={`${tdClass} font-medium`}
                                                        >
                                                            {entry.period_label ??
                                                                '-'}
                                                        </td>
                                                        <td className={tdClass}>
                                                            {entry.start_time ??
                                                                '-'}
                                                            {entry.start_time &&
                                                                entry.end_time &&
                                                                ' - '}
                                                            {entry.end_time ?? ''}
                                                        </td>
                                                        <td
                                                            className={
                                                                entry.is_break
                                                                    ? 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500'
                                                                    : `${tdClass} font-medium text-navy`
                                                            }
                                                        >
                                                            {entry.is_break
                                                                ? 'Break'
                                                                : (entry.subject_name ??
                                                                  '-')}
                                                        </td>
                                                        <td className={tdClass}>
                                                            {entry.is_break
                                                                ? '-'
                                                                : (entry.teacher_name ??
                                                                  '-')}
                                                        </td>
                                                        <td className={tdClass}>
                                                            {entry.room ?? '-'}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Card>

                        <Card title="Weekly Summary">
                            {weekSummary.length === 0 ? (
                                <p className="mt-4 text-sm text-gray-600">
                                    No schedule available.
                                </p>
                            ) : (
                                <ul className="mt-4 space-y-2">
                                    {weekSummary.map((day) => {
                                        const isToday =
                                            todayNumber !== undefined &&
                                            day.day_number === todayNumber;

                                        return (
                                            <li
                                                key={day.day_number}
                                                className={
                                                    'flex items-center justify-between rounded-md px-3 py-2 text-sm ' +
                                                    (isToday
                                                        ? 'bg-navy font-semibold text-white'
                                                        : 'text-gray-700')
                                                }
                                            >
                                                <span>{day.day_name}</span>
                                                <span
                                                    className={
                                                        isToday
                                                            ? 'text-white'
                                                            : 'text-gray-500'
                                                    }
                                                >
                                                    {day.periods_count}{' '}
                                                    {day.periods_count === 1
                                                        ? 'period'
                                                        : 'periods'}
                                                </span>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </Card>
                    </div>

                    {/* Quick links */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Link
                            href={route('student.timetable')}
                            className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 transition hover:ring-navy"
                        >
                            <p className="text-sm font-semibold text-navy">
                                View Timetable
                            </p>
                            <p className="mt-1 text-xs text-gray-500">
                                Your full weekly schedule
                            </p>
                        </Link>

                        <Link
                            href={route('student.profile')}
                            className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 transition hover:ring-navy"
                        >
                            <p className="text-sm font-semibold text-navy">
                                My Profile
                            </p>
                            <p className="mt-1 text-xs text-gray-500">
                                Your college record
                            </p>
                        </Link>

                        {/*
                            The two that are not built yet. Shown disabled
                            rather than hidden, so the portal's shape is
                            honest about what is coming and a student can see
                            a feature is planned rather than missing.
                        */}
                        <div className="rounded-lg bg-surface p-4 opacity-60 ring-1 ring-gray-200">
                            <p className="text-sm font-semibold text-gray-500">
                                Attendance
                            </p>
                            <p className="mt-1 text-xs text-gray-400">
                                Coming soon
                            </p>
                        </div>

                        <div className="rounded-lg bg-surface p-4 opacity-60 ring-1 ring-gray-200">
                            <p className="text-sm font-semibold text-gray-500">
                                Results
                            </p>
                            <p className="mt-1 text-xs text-gray-400">
                                Coming soon
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
