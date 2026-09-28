import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

interface ClassInfo {
    id: number;
    display_name: string;
    grade_level: number;
    stream_name: string | null;
    section: string;
    room: string | null;
}

interface PeriodOption {
    id: number;
    number: number;
    label: string;
    start_time: string;
    end_time: string;
    is_break: boolean;
}

interface ResolvedSlot {
    day_of_week: number;
    period_id: number;
    class_subject_id: number;
    room: string | null;
    subject_name: string;
    subject_code: string | null;
    teacher_name: string;
}

type ShowTimetablePageProps = {
    class: ClassInfo;
    periods: PeriodOption[];
    days: Record<string, string>;
    slots: ResolvedSlot[];
    flash?: {
        success?: string;
        error?: string;
    };
};

const cellKey = (day: number, periodId: number) => `${day}-${periodId}`;

export default function Show() {
    const { class: classData, periods, days, slots } =
        usePage<PageProps<ShowTimetablePageProps>>().props;

    const dayNumbers = useMemo(
        () => Object.keys(days).map(Number).sort((a, b) => a - b),
        [days],
    );

    // The server already resolved each cell to names, so the grid is a plain
    // lookup with no id joining in the browser.
    const cells = useMemo(() => {
        const map: Record<string, ResolvedSlot> = {};

        for (const slot of slots) {
            map[cellKey(slot.day_of_week, slot.period_id)] = slot;
        }

        return map;
    }, [slots]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Timetable — {classData.display_name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Grade {classData.grade_level} ·{' '}
                            {classData.stream_name} · Section{' '}
                            {classData.section}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.classes.index')}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-gray-700 hover:bg-surface"
                        >
                            Back to Classes
                        </Link>
                        <Link
                            href={route(
                                'admin.classes.timetable.edit',
                                classData.id,
                            )}
                            className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                        >
                            Edit Timetable
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Timetable" />

            <div className="flex flex-col gap-4">
                {slots.length === 0 ? (
                    <div className="rounded-lg bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-200">
                        <p className="text-sm text-gray-600">
                            No timetable has been built for this class yet.
                        </p>
                        <Link
                            href={route(
                                'admin.classes.timetable.edit',
                                classData.id,
                            )}
                            className="mt-4 inline-block rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                        >
                            Build Timetable
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        <div className="overflow-auto">
                            <table className="min-w-full border-collapse">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className="border-b border-gray-200 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                            Period
                                        </th>
                                        {dayNumbers.map((day) => (
                                            <th
                                                key={day}
                                                className="border-b border-gray-200 px-2 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600"
                                            >
                                                {days[String(day)]}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {periods.map((period) => (
                                        <ReadOnlyRow
                                            key={period.id}
                                            period={period}
                                            dayNumbers={dayNumbers}
                                            days={days}
                                            cells={cells}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

interface ReadOnlyRowProps {
    period: PeriodOption;
    dayNumbers: number[];
    days: Record<string, string>;
    cells: Record<string, ResolvedSlot>;
}

/**
 * One row of the read-only grid: a break spans the week, a teaching period
 * shows the subject and teacher booked in each day.
 */
function ReadOnlyRow({
    period,
    dayNumbers,
    days,
    cells,
}: ReadOnlyRowProps) {
    if (period.is_break) {
        return (
            <tr className="bg-gray-50">
                <td className="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-500">
                    {period.label}
                    <span className="ml-1 font-normal text-xs">
                        {period.start_time}-{period.end_time}
                    </span>
                </td>
                <td
                    colSpan={dayNumbers.length}
                    className="border-b border-gray-100 px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500"
                >
                    Break — {period.start_time} to {period.end_time}
                </td>
            </tr>
        );
    }

    return (
        <tr className="hover:bg-surface">
            <td className="border-b border-gray-100 px-4 py-2 text-sm font-medium text-navy">
                {period.label}
                <span className="ml-1 text-xs font-normal text-gray-500">
                    {period.start_time}-{period.end_time}
                </span>
            </td>
            {dayNumbers.map((day) => {
                const cell = cells[cellKey(day, period.id)];

                return (
                    <td
                        key={cellKey(day, period.id)}
                        className="border-b border-gray-100 px-2 py-2 text-xs"
                    >
                        {cell ? (
                            <>
                                <span className="block font-semibold text-navy">
                                    {cell.subject_name}
                                </span>
                                <span className="block text-gray-500">
                                    {cell.teacher_name}
                                </span>
                            </>
                        ) : (
                            <span className="text-gray-300">—</span>
                        )}
                    </td>
                );
            })}
        </tr>
    );
}
