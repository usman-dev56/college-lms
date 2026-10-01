import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type AttendanceStatus = 'present' | 'absent' | 'late' | 'leave';

interface RosterStudent {
    student_profile_id: number;
    roll_number: string | null;
    name: string | null;
    batch_name: string | null;
    status: AttendanceStatus | null;
}

type TeacherAttendanceMarkPageProps = {
    class_subject: {
        id: number;
        subject_name: string | null;
        subject_code: string | null;
    };
    class: {
        id: number | null;
        display_name: string | null;
        grade_level: number | null;
        section: string | null;
        stream_name: string | null;
    };
    period: {
        id: number;
        number: number | null;
        label: string | null;
        start_time: string | null;
        end_time: string | null;
    };
    attendance_date: string;
    students: RosterStudent[];
    is_locked: boolean;
    locked_message: string | null;
    flash?: {
        success?: string;
        error?: string;
    };
};

/**
 * The four marks, in the order a teacher reads them, each with the colours the
 * Attendance model publishes for the rest of the application. One list, so the
 * badge and the button can never disagree about what a status looks like.
 */
const STATUS_OPTIONS: {
    value: AttendanceStatus;
    label: string;
    badge: string;
}[] = [
    {
        value: 'present',
        label: 'Present',
        badge: 'bg-green-100 text-green-800',
    },
    {
        value: 'absent',
        label: 'Absent',
        badge: 'bg-red-100 text-red-800',
    },
    {
        value: 'late',
        label: 'Late',
        badge: 'bg-amber-100 text-amber-800',
    },
    {
        value: 'leave',
        label: 'Leave',
        badge: 'bg-blue-100 text-blue-800',
    },
];

const badgeFor = (status: AttendanceStatus | null) =>
    STATUS_OPTIONS.find((option) => option.value === status)?.badge ??
    'bg-gray-100 text-gray-800';

const labelFor = (status: AttendanceStatus | null) =>
    STATUS_OPTIONS.find((option) => option.value === status)?.label ?? 'Unmarked';

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';
/**
 * The four status buttons for one row.
 *
 * Split out so the roster map stays readable: without it, twenty lines of
 * Tailwind sit inside the row markup and bury the student name. The selected
 * button takes the status colour, everything else stays grey, so an unmarked
 * row reads as four identical buttons and a marked row reads as one coloured
 * pill.
 */
function StatusButtons({
    status,
    onSelect,
}: {
    status: AttendanceStatus | null;
    onSelect: (value: AttendanceStatus) => void;
}) {
    return (
        <div className="flex flex-wrap gap-1">
            {STATUS_OPTIONS.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    onClick={() => onSelect(option.value)}
                    aria-pressed={status === option.value}
                    className={
                        'rounded px-3 py-1 text-xs font-semibold transition ' +
                        (status === option.value
                            ? option.badge
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200')
                    }
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

export default function AttendanceMark() {
    const {
        class_subject,
        class: classInfo,
        period,
        attendance_date,
        students,
        is_locked,
        locked_message,
        flash,
    } = usePage<PageProps<TeacherAttendanceMarkPageProps>>().props;

    /*
     * The roster is held in local state rather than read straight from props,
     * because a teacher marking thirty names must be able to move between
     * four buttons per row without a round trip for each one. The server is
     * still the only thing that decides what was saved.
     */
    const [statuses, setStatuses] = useState<
        Record<number, AttendanceStatus | null>
    >(() =>
        Object.fromEntries(
            students.map((student) => [
                student.student_profile_id,
                student.status,
            ]),
        ),
    );
    const [processing, setProcessing] = useState(false);

    const setStatus = (
        studentProfileId: number,
        status: AttendanceStatus,
    ) => {
        setStatuses((current) => ({ ...current, [studentProfileId]: status }));
    };

    const markAllPresent = () => {
        setStatuses(
            Object.fromEntries(
                students.map((student) => [
                    student.student_profile_id,
                    'present' as AttendanceStatus,
                ]),
            ),
        );
    };

    const resetAll = () => {
        setStatuses(
            Object.fromEntries(
                students.map((student) => [
                    student.student_profile_id,
                    null,
                ]),
            ),
        );
    };

    const markedCount = students.filter(
        (student) => statuses[student.student_profile_id] != null,
    ).length;

    const allMarked = markedCount === students.length && students.length > 0;

    const submit = () => {
        // The dialog is not decoration: the server refuses a second save for
        // this period, so a teacher who confirms by reflex has no way back
        // without going through the administration.
        if (
            !window.confirm(
                'Submit attendance? This cannot be undone.',
            )
        ) {
            return;
        }

        setProcessing(true);

        router.post(
            route('teacher.attendance.store'),
            {
                class_subject_id: class_subject.id,
                period_id: period.id,
                attendance_date,
                records: students.map((student) => ({
                    student_profile_id: student.student_profile_id,
                    status: statuses[student.student_profile_id],
                })),
            },
            { preserveScroll: true },
        );
    };
return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div className="min-w-0">
                        <h2 className="truncate font-serif text-xl font-semibold text-navy">
                            {class_subject.subject_name ?? 'Subject'} — Period{' '}
                            {period.number}
                        </h2>
                        <p className="mt-1 truncate text-sm text-gray-600">
                            {classInfo.display_name ?? 'Class'} ·{' '}
                            {period.label ?? 'Period'} · {attendance_date}
                        </p>
                    </div>

                    <Link
                        href={route('teacher.attendance.index')}
                        className="shrink-0 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-surface"
                    >
                        Back
                    </Link>
                </div>
            }
        >
            <Head title="Mark Attendance" />

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

            {is_locked && (
                <div className="mb-4 rounded-md bg-amber-50 p-4 ring-1 ring-amber-200">
                    <p className="text-sm font-semibold text-amber-900">
                        {locked_message ??
                            'Attendance locked. Contact administration to change.'}
                    </p>
                </div>
            )}

            {!is_locked && (
                <div className="mb-4 rounded-md bg-surface p-4 ring-1 ring-gray-200">
                    <p className="text-sm text-gray-700">
                        Mark each student&apos;s attendance. Once you submit, you
                        cannot change it. Contact administration if a change is
                        needed.
                    </p>
                </div>
            )}
{students.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-lg bg-white p-12 text-center shadow-sm ring-1 ring-gray-200">
                    <p className="text-sm font-medium text-gray-700">
                        No students are enrolled in this class yet.
                    </p>
                    <Link
                        href={route('teacher.attendance.index')}
                        className="text-sm font-semibold text-navy hover:text-gold"
                    >
                        Back to Today&apos;s Periods
                    </Link>
                </div>
            ) : (
                <>
                    {!is_locked && (
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                onClick={markAllPresent}
                                className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
                            >
                                Mark All Present
                            </button>
                            <button
                                type="button"
                                onClick={resetAll}
                                className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-surface"
                            >
                                Reset
                            </button>
                        </div>
                    )}

                    <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        <div className="min-h-0 flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>Roll #</th>
                                        <th className={thClass}>Student</th>
                                        <th className={thClass}>Batch</th>
                                        <th className={thClass}>Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {students.map((student) => {
                                        const status =
                                            statuses[student.student_profile_id] ??
                                            null;

                                        return (
                                            <tr
                                                key={student.student_profile_id}
                                                className="hover:bg-surface"
                                            >
                                                <td className="whitespace-nowrap px-4 py-2 text-sm font-semibold text-navy">
                                                    {student.roll_number ?? '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-2 text-sm font-medium text-gray-900">
                                                    {student.name ?? '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-2 text-sm text-gray-700">
                                                    {student.batch_name ?? '—'}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {is_locked ? (
                                                        <span
                                                            className={
                                                                'rounded-full px-3 py-1 text-xs font-semibold ' +
                                                                badgeFor(status)
                                                            }
                                                        >
                                                            {labelFor(status)}
                                                        </span>
                                                    ) : (
                                                        <StatusButtons
                                                            status={status}
                                                            onSelect={(value) =>
                                                                setStatus(
                                                                    student.student_profile_id,
                                                                    value,
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
{!is_locked && (
                            <div className="flex shrink-0 items-center justify-between gap-4 border-t border-gray-200 bg-surface px-4 py-3">
                                <p className="text-sm text-gray-700">
                                    {markedCount} of {students.length} marked
                                </p>

                                <button
                                    type="button"
                                    onClick={submit}
                                    disabled={!allMarked || processing}
                                    className="rounded-md bg-navy px-6 py-2 text-sm font-semibold text-white hover:bg-navy-dark disabled:cursor-not-allowed disabled:bg-gray-300"
                                >
                                    {processing
                                        ? 'Submitting...'
                                        : 'Submit Attendance'}
                                </button>
                            </div>
                        )}
                    </div>

                    {is_locked && (
                        <div className="mt-4">
                            <Link
                                href={route('teacher.attendance.index')}
                                className="text-sm font-semibold text-navy hover:text-gold"
                            >
                                Back to Today&apos;s Periods
                            </Link>
                        </div>
                    )}
                </>
            )}
        </AuthenticatedLayout>
    );
}