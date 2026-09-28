import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface ClassRow {
    id: number;
    display_name: string;
    academic_session_id: number;
    academic_session: string | null;
    stream_id: number;
    stream: string | null;
    grade_level: number;
    section: string;
    capacity: number | null;
    room: string | null;
    is_active: boolean;
}

interface SessionOption {
    id: number;
    name: string;
    is_active: boolean;
}

interface StreamOption {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
}

type ClassesPageProps = {
    classes: ClassRow[];
    sessions: SessionOption[];
    streams: StreamOption[];
    flash?: {
        success?: string;
        error?: string;
    };
};

const thClass =
    'px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-6 py-4 text-sm text-gray-700';

const selectClass =
    'mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

export default function Index() {
    const { classes, sessions, streams, flash } =
        usePage<PageProps<ClassesPageProps>>().props;

    const activeSession = sessions.find((session) => session.is_active);

    // The session filter starts on the active session, which is where new
    // classes are created.
    const [sessionFilter, setSessionFilter] = useState<string>(
        activeSession ? String(activeSession.id) : 'all',
    );
    const [gradeFilter, setGradeFilter] = useState('all');
    const [streamFilter, setStreamFilter] = useState('all');

    const filtered = useMemo(
        () =>
            classes.filter((classRow) => {
                if (
                    sessionFilter !== 'all' &&
                    classRow.academic_session_id !== Number(sessionFilter)
                ) {
                    return false;
                }

                if (
                    gradeFilter !== 'all' &&
                    classRow.grade_level !== Number(gradeFilter)
                ) {
                    return false;
                }

                if (
                    streamFilter !== 'all' &&
                    classRow.stream_id !== Number(streamFilter)
                ) {
                    return false;
                }

                return true;
            }),
        [classes, sessionFilter, gradeFilter, streamFilter],
    );

    const handleDelete = (classRow: ClassRow) => {
        if (
            !window.confirm(
                `Delete class "${classRow.display_name}"? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(route('admin.classes.destroy', classRow.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Classes
                    </h2>
                    <Link
                        href={route('admin.classes.create')}
                        className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Class
                    </Link>
                </div>
            }
        >
            <Head title="Classes" />

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
                {/* Filter bar */}
                <div className="flex flex-wrap items-end gap-4 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
                    <div>
                        <label
                            htmlFor="session-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Session
                        </label>
                        <select
                            id="session-filter"
                            value={sessionFilter}
                            onChange={(e) => setSessionFilter(e.target.value)}
                            className={selectClass}
                        >
                            <option value="all">All sessions</option>
                            {sessions.map((session) => (
                                <option key={session.id} value={session.id}>
                                    {session.name}
                                    {session.is_active ? ' (active)' : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="grade-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Grade
                        </label>
                        <select
                            id="grade-filter"
                            value={gradeFilter}
                            onChange={(e) => setGradeFilter(e.target.value)}
                            className={selectClass}
                        >
                            <option value="all">All grades</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="stream-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Stream
                        </label>
                        <select
                            id="stream-filter"
                            value={streamFilter}
                            onChange={(e) => setStreamFilter(e.target.value)}
                            className={selectClass}
                        >
                            <option value="all">All streams</option>
                            {streams.map((stream) => (
                                <option key={stream.id} value={stream.id}>
                                    {stream.name} ({stream.code})
                                </option>
                            ))}
                        </select>
                    </div>

                    <p className="ml-auto flex h-10 items-center text-sm text-gray-500">
                        Showing {filtered.length} of {classes.length} classes
                    </p>
                </div>

                {filtered.length === 0 ? (
                    <div className="rounded-lg bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-200">
                        <p className="text-sm text-gray-600">
                            {classes.length === 0
                                ? 'No classes yet. Create the first one to get started.'
                                : 'No classes match the selected filters.'}
                        </p>
                        <Link
                            href={route('admin.classes.create')}
                            className="mt-4 inline-block rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                        >
                            New Class
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        <div className="overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>Class</th>
                                        <th className={thClass}>Session</th>
                                        <th className={thClass}>Stream</th>
                                        <th className={thClass}>Grade</th>
                                        <th className={thClass}>Section</th>
                                        <th className={thClass}>Capacity</th>
                                        <th className={thClass}>Room</th>
                                        <th className={thClass}>Status</th>
                                        <th className={`${thClass} text-right`}>
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {filtered.map((classRow) => (
                                        <tr key={classRow.id} className="hover:bg-surface">
                                            <td
                                                className={`${tdClass} font-medium text-navy`}
                                            >
                                                {classRow.display_name}
                                            </td>
                                            <td className={tdClass}>
                                                {classRow.academic_session ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {classRow.stream ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                <span className="inline-flex items-center rounded-full bg-surface px-3 py-1 text-xs font-semibold text-navy ring-1 ring-gray-200">
                                                    Grade {classRow.grade_level}
                                                </span>
                                            </td>
                                            <td className={tdClass}>
                                                {classRow.section}
                                            </td>
                                            <td className={tdClass}>
                                                {classRow.capacity ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {classRow.room ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {classRow.is_active ? (
                                                    <span className="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                                        Inactive
                                                    </span>
                                                )}
                                            </td>
                                            <td className={`${tdClass} text-right`}>
                                                <div className="flex items-center justify-end gap-3">
                                                    <Link
                                                        href={route(
                                                            'admin.classes.timetable.edit',
                                                            classRow.id,
                                                        )}
                                                        className="font-medium text-navy hover:text-gold"
                                                    >
                                                        Timetable
                                                    </Link>
                                                    <Link
                                                        href={route(
                                                            'admin.classes.assignments.edit',
                                                            classRow.id,
                                                        )}
                                                        className="font-medium text-gold hover:text-gold-dark"
                                                    >
                                                        Assign
                                                    </Link>
                                                    <Link
                                                        href={route(
                                                            'admin.classes.edit',
                                                            classRow.id,
                                                        )}
                                                        className="font-medium text-navy hover:text-gold"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(classRow)
                                                        }
                                                        className="font-medium text-red-600 hover:text-red-800"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
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
