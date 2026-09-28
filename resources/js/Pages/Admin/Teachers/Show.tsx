import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface TeacherData {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    created_at: string | null;
}

interface Assignment {
    id: number;
    class_id: number;
    class_display_name: string;
    grade_level: number;
    stream_name: string | null;
    section: string;
    subject_id: number;
    subject_name: string;
    subject_code: string | null;
    periods_per_week: number;
}

type ShowTeacherPageProps = {
    teacher: TeacherData;
    assignments: Assignment[];
};

const thClass =
    'px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-6 py-4 text-sm text-gray-700';

const selectClass =
    'block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

export default function Show() {
    const { teacher, assignments } =
        usePage<PageProps<ShowTeacherPageProps>>().props;

    // Filtering happens in the browser: a single teacher never has more
    // assignments than fit on a screen, and a round-trip per dropdown click
    // would be needless.
    const [classFilter, setClassFilter] = useState('all');
    const [subjectFilter, setSubjectFilter] = useState('all');

    const classOptions = useMemo(
        () =>
            Array.from(
                new Map(
                    assignments.map((assignment) => [
                        assignment.class_id,
                        assignment.class_display_name,
                    ]),
                ).entries(),
            ).sort((a, b) => a[1].localeCompare(b[1])),
        [assignments],
    );

    const subjectOptions = useMemo(
        () =>
            Array.from(
                new Map(
                    assignments.map((assignment) => [
                        assignment.subject_id,
                        assignment.subject_name,
                    ]),
                ).entries(),
            ).sort((a, b) => a[1].localeCompare(b[1])),
        [assignments],
    );

    const visible = useMemo(
        () =>
            assignments.filter(
                (assignment) =>
                    (classFilter === 'all' ||
                        assignment.class_id === Number(classFilter)) &&
                    (subjectFilter === 'all' ||
                        assignment.subject_id === Number(subjectFilter)),
            ),
        [assignments, classFilter, subjectFilter],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            {teacher.name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Teaching staff profile and class assignments.
                        </p>
                    </div>
                    <Link
                        href={route('admin.teachers.index')}
                        className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-gray-700 hover:bg-surface"
                    >
                        Back to Teachers
                    </Link>
                </div>
            }
        >
            <Head title={teacher.name} />

            <div className="flex flex-col gap-4">
                {/* Profile card */}
                <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 className="font-serif text-2xl font-semibold text-navy">
                                {teacher.name}
                            </h3>
                            <dl className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                        Email
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-800">
                                        {teacher.email}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                        Phone
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-800">
                                        {teacher.phone ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                        Status
                                    </dt>
                                    <dd className="mt-1">
                                        {teacher.is_active ? (
                                            <span className="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                                Active
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                                Inactive
                                            </span>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                            {teacher.created_at && (
                                <p className="mt-4 text-xs text-gray-500">
                                    Joined on {teacher.created_at}
                                </p>
                            )}
                        </div>

                        <Link
                            href={route(
                                'admin.teachers.edit',
                                teacher.id,
                            )}
                            className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                        >
                            Edit Teacher
                        </Link>
                    </div>
                </div>

                {/* Assignments card */}
                <div className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="flex flex-wrap items-end gap-3 border-b border-gray-200 px-6 py-4">
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700">
                            Class Assignments
                        </h3>

                        <div className="ml-auto flex flex-wrap items-end gap-3">
                            <div className="w-full sm:w-64">
                                <label
                                    htmlFor="assignment-class-filter"
                                    className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                                >
                                    Class
                                </label>
                                <select
                                    id="assignment-class-filter"
                                    value={classFilter}
                                    className={selectClass}
                                    onChange={(e) =>
                                        setClassFilter(e.target.value)
                                    }
                                >
                                    <option value="all">All classes</option>
                                    {classOptions.map(
                                        ([id, displayName]) => (
                                            <option
                                                key={id}
                                                value={id}
                                            >
                                                {displayName}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </div>

                            <div className="w-full sm:w-56">
                                <label
                                    htmlFor="assignment-subject-filter"
                                    className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                                >
                                    Subject
                                </label>
                                <select
                                    id="assignment-subject-filter"
                                    value={subjectFilter}
                                    className={selectClass}
                                    onChange={(e) =>
                                        setSubjectFilter(e.target.value)
                                    }
                                >
                                    <option value="all">
                                        All subjects
                                    </option>
                                    {subjectOptions.map(([id, name]) => (
                                        <option key={id} value={id}>
                                            {name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <p className="flex h-10 items-center text-sm text-gray-500">
                                Showing {visible.length} of{' '}
                                {assignments.length}
                            </p>
                        </div>
                    </div>

                    {visible.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-gray-600">
                            {assignments.length === 0
                                ? 'This teacher has no class assignments yet.'
                                : 'No assignments match the selected filters.'}
                        </p>
                    ) : (
                        <div className="overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>Class</th>
                                        <th className={thClass}>Grade</th>
                                        <th className={thClass}>Stream</th>
                                        <th className={thClass}>
                                            Subject
                                        </th>
                                        <th
                                            className={`${thClass} text-right`}
                                        >
                                            Periods / Week
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {visible.map((assignment) => (
                                        <tr
                                            key={assignment.id}
                                            className="hover:bg-surface"
                                        >
                                            <td
                                                className={`${tdClass} font-medium text-navy`}
                                            >
                                                {assignment.class_display_name}
                                            </td>
                                            <td className={tdClass}>
                                                <span className="inline-flex items-center rounded-full bg-surface px-3 py-1 text-xs font-semibold text-navy ring-1 ring-gray-200">
                                                    Grade{' '}
                                                    {assignment.grade_level}
                                                </span>
                                            </td>
                                            <td className={tdClass}>
                                                {assignment.stream_name ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {assignment.subject_name}{' '}
                                                <span className="text-xs text-gray-500">
                                                    (
                                                    {
                                                        assignment.subject_code
                                                    }
                                                    )
                                                </span>
                                            </td>
                                            <td
                                                className={`${tdClass} text-right`}
                                            >
                                                {assignment.periods_per_week}
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