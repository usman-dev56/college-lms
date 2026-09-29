import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

interface EnrollmentRecord {
    id: number;
    class_display_name: string | null;
    session_name: string | null;
    enrolled_at: string | null;
    status: string;
    is_current: boolean;
}

interface StudentInfo {
    id: number;
    name: string | null;
    roll_number: string | null;
    batch_name: string | null;
}

type StudentHistoryPageProps = {
    student: StudentInfo;
    enrollments: EnrollmentRecord[];
};

const thClass =
    'px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-6 py-4 text-sm text-gray-700';

const statusStyles: Record<string, string> = {
    active: 'bg-green-100 text-green-800',
    transferred: 'bg-blue-100 text-blue-800',
    withdrawn: 'bg-red-100 text-red-800',
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    transferred: 'Transferred',
    withdrawn: 'Withdrawn',
};

export default function StudentHistory() {
    const { student, enrollments } =
        usePage<PageProps<StudentHistoryPageProps>>().props;

    const displayName = student.name ?? 'Unknown Student';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Enrollment History — {displayName}
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Roll number {student.roll_number ?? '—'}
                            {student.batch_name
                                ? ` · ${student.batch_name}`
                                : ''}
                        </p>
                    </div>
                    <Link
                        href={route('admin.students.show', student.id)}
                        className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-gray-700 hover:bg-surface"
                    >
                        Back to Student
                    </Link>
                </div>
            }
        >
            <Head title={`Enrollment History — ${displayName}`} />

            <div className="flex h-full min-h-0 flex-col gap-4">
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    {enrollments.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 p-12 text-center">
                            <p className="text-sm font-medium text-gray-700">
                                This student has not been enrolled in any class
                                yet.
                            </p>
                            <p className="text-xs text-gray-500">
                                Add them from a class roster.
                            </p>
                        </div>
                    ) : (
                        <div className="min-h-0 flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>Class</th>
                                        <th className={thClass}>Session</th>
                                        <th className={thClass}>
                                            Enrolled At
                                        </th>
                                        <th className={thClass}>Status</th>
                                        <th className={thClass}>Current</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {enrollments.map((enrollment) => (
                                        <tr
                                            key={enrollment.id}
                                            className="hover:bg-surface"
                                        >
                                            <td
                                                className={`${tdClass} font-medium text-gray-900`}
                                            >
                                                {enrollment.class_display_name ??
                                                    '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {enrollment.session_name ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {enrollment.enrolled_at ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                <span
                                                    className={
                                                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ' +
                                                        (statusStyles[
                                                            enrollment.status
                                                        ] ??
                                                            'bg-gray-100 text-gray-600')
                                                    }
                                                >
                                                    {statusLabels[
                                                        enrollment.status
                                                    ] ?? enrollment.status}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                {enrollment.is_current ? (
                                                    <span className="inline-flex items-center rounded-full bg-navy px-3 py-1 text-xs font-semibold text-white">
                                                        Yes
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400">
                                                        No
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
        </AuthenticatedLayout>
    );
}
