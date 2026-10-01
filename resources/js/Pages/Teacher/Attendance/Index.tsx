import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

interface AttendanceSlot {
    slot_id: number;
    class_subject_id: number;
    period: {
        id: number;
        number: number | null;
        label: string | null;
        start_time: string | null;
        end_time: string | null;
    };
    class: {
        id: number | null;
        display_name: string | null;
    };
    subject: {
        id: number | null;
        name: string | null;
        code: string | null;
    };
    is_marked: boolean;
    marked_count: number;
    enrolled_count: number;
}

type TeacherAttendanceIndexPageProps = {
    today_name: string;
    attendance_date: string;
    teacher_name: string;
    is_sunday: boolean;
    slots: AttendanceSlot[];
    flash?: {
        success?: string;
        error?: string;
    };
};

export default function AttendanceIndex() {
    const {
        today_name,
        attendance_date,
        teacher_name,
        is_sunday,
        slots,
        flash,
    } = usePage<PageProps<TeacherAttendanceIndexPageProps>>().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Mark Attendance
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Today&apos;s periods: {today_name}
                        </p>
                    </div>
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
{slots.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-3 rounded-lg bg-white p-12 text-center shadow-sm ring-1 ring-gray-200">
                    <p className="text-sm font-medium text-gray-700">
                        {is_sunday
                            ? 'There are no classes scheduled today.'
                            : 'You have no periods scheduled for today. Check your timetable.'}
                    </p>
                    <Link
                        href={route('teacher.timetable')}
                        className="text-sm font-semibold text-navy hover:text-gold"
                    >
                        View My Timetable
                    </Link>
                </div>
            ) : (
                <ul className="flex flex-col gap-3">
                    {slots.map((slot) => (
                        <li
                            key={slot.slot_id}
                            className="flex flex-col gap-3 rounded-lg border-l-4 border-navy bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-serif text-base font-semibold text-navy">
                                        {slot.period.label ??
                                            `Period ${slot.period.number}`}
                                    </span>
                                    {slot.period.start_time &&
                                        slot.period.end_time && (
                                            <span className="text-sm text-gray-600">
                                                {slot.period.start_time}–
                                                {slot.period.end_time}
                                            </span>
                                        )}
                                </div>

                                <p className="mt-1 text-sm text-gray-700">
                                    {slot.subject.name ?? 'Subject'}
                                    {slot.subject.code
                                        ? ` (${slot.subject.code})`
                                        : ''}
                                </p>

                                <p className="mt-0.5 text-sm text-gray-500">
                                    {slot.class.display_name ?? 'Class'} ·{' '}
                                    {slot.enrolled_count} student
                                    {slot.enrolled_count === 1 ? '' : 's'}{' '}
                                    enrolled
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-3">
                                {slot.is_marked ? (
                                    <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                        Marked ({slot.marked_count} student
                                        {slot.marked_count === 1 ? '' : 's'})
                                    </span>
                                ) : (
                                    <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                        Not Marked
                                    </span>
                                )}

                                <Link
                                    href={route('teacher.attendance.mark', {
                                        class_subject_id:
                                            slot.class_subject_id,
                                        period_id: slot.period.id,
                                        attendance_date,
                                    })}
                                    className={
                                        'rounded-md px-4 py-2 text-sm font-semibold text-white transition ' +
                                        (slot.is_marked
                                            ? 'bg-gray-600 hover:bg-gray-700'
                                            : 'bg-navy hover:bg-navy-dark')
                                    }
                                >
                                    {slot.is_marked ? 'View' : 'Mark Attendance'}
                                </Link>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {slots.length > 0 && (
                <p className="mt-4 text-xs text-gray-500">
                    Signed in as {teacher_name}. Once a period is submitted it
                    cannot be changed by you.
                </p>
            )}
        </AuthenticatedLayout>
    );
}
