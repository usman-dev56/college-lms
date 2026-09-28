/**
 * Read-only weekly timetable grid, shared by the teacher and student views.
 *
 * This component is purely presentational: it fetches nothing, touches no
 * Inertia hooks, and renders exactly the props it is given. The admin
 * timetable builder has its own editable grid, which is deliberately not
 * shared with this one.
 */

export interface TimetablePeriod {
    id: number;
    number: number;
    label: string;
    /** HH:MM */
    start_time: string;
    /** HH:MM */
    end_time: string;
    is_break: boolean;
}

export interface TimetableSlotView {
    day_of_week: number;
    period_id: number;
    subject_name: string;
    subject_code: string | null;
    /** Null in the student view, where the teacher is the person reading. */
    teacher_name: string | null;
    room: string | null;
    /** Which class, in the teacher view. */
    class_display_name?: string;
}

export interface TimetableGridProps {
    periods: TimetablePeriod[];
    days: Record<number, string>;
    slots: TimetableSlotView[];
    mode: 'teacher' | 'student';
}

const cellBase = 'border border-gray-200 px-2 py-2 text-xs';

const thClass =
    'border border-gray-200 bg-surface px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const cellKey = (day: number, periodId: number) => `${day}-${periodId}`;

export default function TimetableGrid({
    periods,
    days,
    slots,
    mode,
}: TimetableGridProps) {
    const dayNumbers = Object.keys(days)
        .map(Number)
        .filter((day) => !Number.isNaN(day))
        .sort((a, b) => a - b);

    const orderedPeriods = [...periods].sort((a, b) => a.number - b.number);

    // Cells arrive keyed by day and period so a lookup is a single access.
    const cells = new Map<string, TimetableSlotView>();

    for (const slot of slots) {
        cells.set(cellKey(slot.day_of_week, slot.period_id), slot);
    }

    if (slots.length === 0) {
        return (
            <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p className="text-sm text-gray-600">
                    No timetable has been configured yet.
                </p>
            </div>
        );
    }

    /**
     * The secondary line under a subject: who or where. A teacher cares
     * which class they are in; a student cares who is teaching them.
     */
    const detail = (slot: TimetableSlotView): string | null => {
        const parts =
            mode === 'teacher'
                ? [slot.class_display_name, slot.room]
                : [slot.teacher_name, slot.room];

        return parts.filter((part): part is string => Boolean(part)).join(' · ') || null;
    };

    const cellBody = (slot: TimetableSlotView | undefined) => {
        if (!slot) {
            return (
                <span className="text-gray-300" aria-label="No class">
                    —
                </span>
            );
        }

        const secondary = detail(slot);

        return (
            <>
                <span className="block font-semibold text-navy">
                    {slot.subject_name}
                    {slot.subject_code && (
                        <span className="ml-1 font-normal text-gray-500">
                            ({slot.subject_code})
                        </span>
                    )}
                </span>
                {secondary && (
                    <span className="mt-0.5 block text-gray-500">
                        {secondary}
                    </span>
                )}
            </>
        );
    };

    return (
        <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
            {/*
                A six-column grid cannot be read on a phone, so below the md
                breakpoint each day becomes its own card instead of a column.
                Both renderings come from the same props and only the markup
                differs, so there is no JavaScript resize handling to keep in
                step with the CSS.
            */}
            <div className="hidden overflow-x-auto md:block">
                <table className="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th className={thClass}>Period</th>
                            {dayNumbers.map((day) => (
                                <th key={day} className={thClass}>
                                    {days[day]}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {orderedPeriods.map((period) =>
                            period.is_break ? (
                                <tr key={period.id} className="bg-gray-50">
                                    <td
                                        className={`${cellBase} font-semibold text-gray-600`}
                                    >
                                        {period.label}
                                        <span className="ml-1 font-normal text-gray-500">
                                            {period.start_time}-
                                            {period.end_time}
                                        </span>
                                    </td>
                                    <td
                                        colSpan={dayNumbers.length}
                                        className={`${cellBase} text-center uppercase tracking-wider text-gray-500`}
                                    >
                                        {period.label} — {period.start_time}{' '}
                                        to {period.end_time}
                                    </td>
                                </tr>
                            ) : (
                                <tr key={period.id}>
                                    <td
                                        className={`${cellBase} font-semibold text-navy`}
                                    >
                                        {period.label}
                                        <span className="ml-1 font-normal text-gray-500">
                                            {period.start_time}-
                                            {period.end_time}
                                        </span>
                                    </td>
                                    {dayNumbers.map((day) => {
                                        const slot = cells.get(
                                            cellKey(day, period.id),
                                        );

                                        return (
                                            <td
                                                key={cellKey(day, period.id)}
                                                className={`${cellBase} ${
                                                    slot
                                                        ? 'bg-white'
                                                        : 'bg-surface'
                                                }`}
                                            >
                                                {cellBody(slot)}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ),
                        )}
                    </tbody>
                </table>
            </div>

            {/* Mobile: one card per day, with the periods stacked inside. */}
            <div className="flex flex-col gap-4 md:hidden">
                {dayNumbers.map((day) => {
                    const dayCells = orderedPeriods
                        .filter((period) => !period.is_break)
                        .map((period) => ({
                            period,
                            slot: cells.get(cellKey(day, period.id)),
                        }));

                    return (
                        <div
                            key={day}
                            className="rounded-md border border-gray-200"
                        >
                            <p className="bg-surface px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-700">
                                {days[day]}
                            </p>
                            <ul className="divide-y divide-gray-100">
                                {dayCells.map(({ period, slot }) => (
                                    <li
                                        key={period.id}
                                        className="px-3 py-2"
                                    >
                                        <p className="text-xs text-gray-500">
                                            {period.label}{' '}
                                            <span className="text-gray-400">
                                                {period.start_time}-
                                                {period.end_time}
                                            </span>
                                        </p>
                                        <div className="mt-0.5 text-sm">
                                            {cellBody(slot)}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
