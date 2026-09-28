import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';

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

interface ClassSubjectOption {
    id: number;
    subject_id: number;
    subject_name: string;
    subject_code: string | null;
    teacher_id: number;
    teacher_name: string;
    periods_per_week: number;
}

interface ExistingSlot {
    id: number;
    day_of_week: number;
    period_id: number;
    class_subject_id: number;
    room: string | null;
}

type EditTimetablePageProps = {
    class: ClassInfo;
    periods: PeriodOption[];
    days: Record<string, string>;
    classSubjects: ClassSubjectOption[];
    existingSlots: ExistingSlot[];
    remaining: Record<string, number>;
    /**
     * Where each teacher is already booked in another class, keyed
     * "day-periodId-teacherId" and valued with the occupying class.
     */
    teacherConflicts: Record<string, string>;
    flash?: {
        success?: string;
        error?: string;
    };
};

/**
 * The grid is keyed "day-periodId" so a cell can be read or written without
 * searching: an empty cell is simply absent.
 */
type Grid = Record<string, number>;

const cellSelectClass =
    'block w-full h-9 rounded-md border-gray-300 text-xs shadow-sm focus:border-navy focus:ring-navy';

const cellKey = (day: number, periodId: number) => `${day}-${periodId}`;

export default function Edit() {
    const {
        class: classData,
        periods,
        days,
        classSubjects,
        existingSlots,
        remaining,
        teacherConflicts,
        flash,
    } = usePage<PageProps<EditTimetablePageProps>>().props;

    const dayNumbers = useMemo(
        () => Object.keys(days).map(Number).sort((a, b) => a - b),
        [days],
    );

    const initialGrid = useMemo<Grid>(() => {
        const grid: Grid = {};

        for (const slot of existingSlots) {
            grid[cellKey(slot.day_of_week, slot.period_id)] =
                slot.class_subject_id;
        }

        return grid;
    }, [existingSlots]);

    const [grid, setGrid] = useState<Grid>(initialGrid);

    // A successful save reloads the page with fresh props, so the local grid
    // is rebuilt from them rather than kept.
    const [savedGrid, setSavedGrid] = useState<Grid>(initialGrid);

    if (initialGrid !== savedGrid) {
        setSavedGrid(initialGrid);
        setGrid(initialGrid);
    }

    const { put, processing, transform } = useForm<{
        slots: Array<{
            day_of_week: number;
            period_id: number;
            class_subject_id: number;
        }>;
    }>({
        slots: [],
    });

    // Inertia sends the whole form state, so the payload is built here instead
    // of at the call site: only the filled cells are sent, keyed the way the
    // grid stores them.
    transform(() => ({
        slots: Object.entries(grid).map(([key, classSubjectId]) => {
            const [day, periodId] = key.split('-');

            return {
                day_of_week: Number(day),
                period_id: Number(periodId),
                class_subject_id: classSubjectId,
            };
        }),
    }));

    // Live remaining counts: what the week still needs, based on the grid as
    // it stands right now rather than what is saved.
    const liveRemaining = useMemo(() => {
        const counts: Record<number, number> = {};

        for (const value of Object.values(grid)) {
            counts[value] = (counts[value] ?? 0) + 1;
        }

        return classSubjects.reduce<Record<number, number>>((carry, option) => {
            carry[option.id] =
                option.periods_per_week - (counts[option.id] ?? 0);

            return carry;
        }, {});
    }, [grid, classSubjects]);

    const totalRemaining = Object.values(liveRemaining).reduce(
        (sum, count) => sum + count,
        0,
    );

    // Whether any filled cell puts a teacher in two classes at the same time.
    // The save is still checked server side; this only warns the admin before
    // they lose a whole round of edits to a rejected submit.
    const hasConflicts = Object.entries(grid).some(
        ([key, classSubjectId]) => {
            const [day, periodId] = key.split('-').map(Number);
            const option = classSubjects.find(
                (candidate) => candidate.id === classSubjectId,
            );

            if (!option) {
                return false;
            }

            return Boolean(
                teacherConflicts[`${day}-${periodId}-${option.teacher_id}`],
            );
        },
    );

    const setCell = (day: number, periodId: number, value: string) => {
        setGrid((current) => {
            const next = { ...current };
            const key = cellKey(day, periodId);

            if (value === '') {
                delete next[key];
            } else {
                next[key] = Number(value);
            }

            return next;
        });
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        put(route('admin.classes.timetable.update', classData.id));
    };

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
                                'admin.classes.timetable.show',
                                classData.id,
                            )}
                            className="rounded-md border border-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-navy hover:bg-surface"
                        >
                            View
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Timetable" />

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

            <div className="flex flex-col gap-4">
                {/* Legend: what is still unscheduled. A chip turns green when
                    its subject has filled its weekly allocation. */}
                <div className="rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-600">
                        Weekly allocation
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        {classSubjects.map((option) => {
                            const left = liveRemaining[option.id] ?? 0;

                            return (
                                <span
                                    key={option.id}
                                    className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold ring-1 ${
                                        left === 0
                                            ? 'bg-green-50 text-green-800 ring-green-200'
                                            : 'bg-surface text-navy ring-gray-200'
                                    }`}
                                >
                                    {option.subject_name}
                                    <span className="font-normal">
                                        (
                                        {left === 0
                                            ? 'complete'
                                            : `${left} remaining`}
                                        )
                                    </span>
                                </span>
                            );
                        })}
                        <span className="ml-auto text-sm text-gray-500">
                            {totalRemaining === 0
                                ? 'Week complete'
                                : `${totalRemaining} period${
                                      totalRemaining === 1 ? '' : 's'
                                  } still to schedule`}
                        </span>
                    </div>
                </div>

                <form
                    onSubmit={submit}
                    className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200"
                >
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
                                    <PeriodRow
                                        key={period.id}
                                        period={period}
                                        dayNumbers={dayNumbers}
                                        days={days}
                                        grid={grid}
                                        classSubjects={classSubjects}
                                        teacherConflicts={teacherConflicts}
                                        onChange={setCell}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex items-center justify-between gap-3 border-t border-gray-200 bg-surface px-4 py-3">
                        <p className="text-sm text-gray-600">
                            {totalRemaining === 0
                                ? 'Every subject has its full weekly allocation. Ready to save.'
                                : 'The week is not complete yet. The server will reject a partial save.'}
                        </p>
                        {hasConflicts && (
                            <p className="text-sm text-red-600">
                                One or more teachers are double-booked. Save
                                will be rejected until the conflicts are
                                resolved.
                            </p>
                        )}
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setGrid(initialGrid)}
                                className="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-navy"
                            >
                                Reset
                            </button>
                            <PrimaryButton
                                disabled={processing}
                                className="bg-navy px-6 py-2 uppercase tracking-wider hover:bg-navy-dark focus:bg-navy-dark active:bg-navy-dark"
                            >
                                {processing ? 'Saving...' : 'Save Timetable'}
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}

interface PeriodRowProps {
    period: PeriodOption;
    dayNumbers: number[];
    days: Record<string, string>;
    grid: Grid;
    classSubjects: ClassSubjectOption[];
    teacherConflicts: Record<string, string>;
    onChange: (day: number, periodId: number, value: string) => void;
}

/**
 * One row of the grid.
 *
 * A break spans every day as a single merged cell: nothing is taught in it,
 * so there is nothing to pick. Teaching periods get one drop-down per day.
 */
function PeriodRow({
    period,
    dayNumbers,
    days,
    grid,
    classSubjects,
    teacherConflicts,
    onChange,
}: PeriodRowProps) {
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
                const key = cellKey(day, period.id);
                const value = grid[key];
                const selected = classSubjects.find(
                    (option) => option.id === value,
                );
                const selectedConflict = selected
                    ? teacherConflicts[
                          `${day}-${period.id}-${selected.teacher_id}`
                      ]
                    : undefined;

                return (
                    <td
                        key={key}
                        className={`border-b border-gray-100 p-1 ${
                            selectedConflict ? 'bg-red-50' : ''
                        }`}
                    >
                        <select
                            aria-label={`${days[String(day)]} ${period.label}`}
                            value={value ? String(value) : ''}
                            className={cellSelectClass}
                            onChange={(event) =>
                                onChange(day, period.id, event.target.value)
                            }
                        >
                            <option value="">—</option>
                            {classSubjects.map((option) => {
                                // Marks an option the server would refuse to
                                // save here. It stays selectable on purpose: the
                                // admin may want to place it and then move it
                                // somewhere free, and only they can decide.
                                const conflictClass =
                                    teacherConflicts[
                                        `${day}-${period.id}-${option.teacher_id}`
                                    ];

                                return (
                                    <option key={option.id} value={option.id}>
                                        {option.subject_name} (
                                        {option.teacher_name})
                                        {conflictClass
                                            ? ` — ⚠ already teaching ${conflictClass}`
                                            : ''}
                                    </option>
                                );
                            })}
                        </select>
                        {selectedConflict && selected && (
                            <p className="mt-1 text-xs text-red-600">
                                {selected.teacher_name} is already teaching{' '}
                                {selectedConflict} at this time.
                            </p>
                        )}
                    </td>
                );
            })}
        </tr>
    );
}
