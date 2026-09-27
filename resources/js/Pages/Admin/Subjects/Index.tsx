import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Subject {
    id: number;
    name: string;
    code: string | null;
    grade_level: number;
    stream_id: number | null;
    stream: string | null;
    has_practical: boolean;
    is_active: boolean;
}

interface StreamOption {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
}

type SubjectsPageProps = {
    subjects: Subject[];
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
    'block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

export default function Index() {
    const { subjects, streams, flash } =
        usePage<PageProps<SubjectsPageProps>>().props;

    const [gradeFilter, setGradeFilter] = useState('all');
    const [streamFilter, setStreamFilter] = useState('all');

    const filtered = useMemo(
        () =>
            subjects.filter((subject) => {
                if (
                    gradeFilter !== 'all' &&
                    subject.grade_level !== Number(gradeFilter)
                ) {
                    return false;
                }

                if (streamFilter === 'compulsory') {
                    return subject.stream_id === null;
                }

                if (streamFilter !== 'all') {
                    return subject.stream_id === Number(streamFilter);
                }

                return true;
            }),
        [subjects, gradeFilter, streamFilter],
    );

    const handleDelete = (subject: Subject) => {
        if (
            !window.confirm(
                `Delete subject "${subject.name}" (Grade ${subject.grade_level})? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(route('admin.subjects.destroy', subject.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Subjects
                    </h2>
                    <Link
                        href={route('admin.subjects.create')}
                        className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Subject
                    </Link>
                </div>
            }
        >
            <Head title="Subjects" />

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

            <div className="mb-4 flex flex-wrap items-end gap-4 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
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
                        className={`mt-1 ${selectClass}`}
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
                        className={`mt-1 ${selectClass}`}
                    >
                        <option value="all">All streams</option>
                        <option value="compulsory">Compulsory only</option>
                        {streams.map((stream) => (
                            <option key={stream.id} value={stream.id}>
                                {stream.name} ({stream.code})
                            </option>
                        ))}
                    </select>
                </div>

                <p className="ml-auto text-sm text-gray-500">
                    Showing {filtered.length} of {subjects.length} subjects
                </p>
            </div>

            {filtered.length === 0 ? (
                <div className="rounded-lg bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <p className="text-sm text-gray-600">
                        {subjects.length === 0
                            ? 'No subjects yet. Create the first one to get started.'
                            : 'No subjects match the selected filters.'}
                    </p>
                    <Link
                        href={route('admin.subjects.create')}
                        className="mt-4 inline-block rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Subject
                    </Link>
                </div>
            ) : (
                <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-surface">
                            <tr>
                                <th className={thClass}>Name</th>
                                <th className={thClass}>Code</th>
                                <th className={thClass}>Grade</th>
                                <th className={thClass}>Stream</th>
                                <th className={thClass}>Practical</th>
                                <th className={thClass}>Status</th>
                                <th className={`${thClass} text-right`}>
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                            {filtered.map((subject) => (
                                <tr key={subject.id} className="hover:bg-surface">
                                    <td
                                        className={`${tdClass} font-medium text-navy`}
                                    >
                                        {subject.name}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.code ? (
                                            <span className="inline-flex items-center rounded-md bg-surface px-2 py-1 font-mono text-xs font-semibold text-navy ring-1 ring-gray-200">
                                                {subject.code}
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className={tdClass}>
                                        <span className="inline-flex items-center rounded-full bg-surface px-3 py-1 text-xs font-semibold text-navy ring-1 ring-gray-200">
                                            Grade {subject.grade_level}
                                        </span>
                                    </td>
                                    <td className={tdClass}>
                                        {subject.stream ?? (
                                            <span className="text-gray-500">
                                                Compulsory
                                            </span>
                                        )}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.has_practical ? (
                                            <span className="inline-flex items-center rounded-full bg-gold-light px-3 py-1 text-xs font-semibold text-navy">
                                                Yes
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                                No
                                            </span>
                                        )}
                                    </td>
                                    <td className={tdClass}>
                                        {subject.is_active ? (
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
                                                    'admin.subjects.edit',
                                                    subject.id,
                                                )}
                                                className="font-medium text-navy hover:text-gold"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleDelete(subject)
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
            )}
        </AuthenticatedLayout>
    );
}
