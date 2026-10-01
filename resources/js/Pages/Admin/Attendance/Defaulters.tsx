import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface DefaulterRow {
    student_profile_id: number;
    roll_number: string;
    student_name: string;
    batch_id: number;
    batch_name: string;
    class_id: number | null;
    class_display_name: string | null;
    percentage: number;
    shortfall: number;
    present: number;
    absent: number;
    late: number;
    leave: number;
    total: number;
}

interface GroupRow {
    class_id?: number | null;
    class_display_name?: string | null;
    batch_id?: number;
    batch_name?: string;
    defaulter_count: number;
}

type DefaultersPageProps = {
    students: DefaulterRow[];
    by_class: GroupRow[];
    by_batch: GroupRow[];
    total: number;
    batches: Record<string, string>;
    classes: { id: number; display_name: string }[];
    streams: Record<string, string>;
    filters: {
        batch_id: number | null;
        class_id: number | null;
        stream_id: number | null;
    };
};

const thClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-4 py-3 text-sm text-gray-700';

const selectClass =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-navy focus:ring-navy';

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

/** One of the three tiles at the top. */
function SummaryCard({
    label,
    value,
    tone = 'text-navy',
}: {
    label: string;
    value: string | number;
    tone?: string;
}) {
    return (
        <div className={cardClass}>
            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                {label}
            </p>
            <p className={`mt-2 font-serif text-2xl font-semibold ${tone}`}>
                {value}
            </p>
        </div>
    );
}
export default function Defaulters() {
    const { students, by_class, by_batch, total, batches, classes, streams, filters } =
        usePage<PageProps<DefaultersPageProps>>().props;

    /*
        Rebuilt from the normalised filters rather than spread from them: the
        filter object holds numbers and nulls, which a query string cannot
        carry. Starting empty means a cleared drop-down really clears.
    */
    const currentQuery = () => {
        const query: Record<string, string> = {};

        if (filters.batch_id) query.batch_id = String(filters.batch_id);
        if (filters.class_id) query.class_id = String(filters.class_id);
        if (filters.stream_id) query.stream_id = String(filters.stream_id);

        return query;
    };

    const applyFilter = (key: string, value: string) => {
        const query = currentQuery();

        if (value) {
            query[key] = value;
        } else {
            delete query[key];
        }

        router.get(route('admin.attendance.defaulters'), query, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const anyFilterSet =
        filters.batch_id !== null ||
        filters.class_id !== null ||
        filters.stream_id !== null;

    // Counted rather than measured against the total: "classes affected" means
    // classes with at least one defaulter, which is not every class.
    const classesAffected = by_class.length;
    const batchesAffected = by_batch.length;

    const exportHref = route(
        'admin.attendance.defaulters.export',
        currentQuery(),
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Attendance Defaulters
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Students below 75% attendance threshold
                        </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        <a
                            href={exportHref}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-surface"
                        >
                            Export CSV
                        </a>
                        <Link
                            href={route('admin.attendance.index')}
                            className="rounded-md bg-navy px-4 py-2 text-sm font-medium text-white hover:bg-navy-dark"
                        >
                            Back to Attendance
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Attendance Defaulters" />

            <div className="flex h-full min-h-0 flex-col gap-4">
                {/* Summary */}
                <div className="grid shrink-0 grid-cols-1 gap-4 sm:grid-cols-3">
                    <SummaryCard
                        label="Total Defaulters"
                        value={total}
                        tone={total > 0 ? 'text-red-600' : 'text-navy'}
                    />
                    <SummaryCard
                        label="Batches Affected"
                        value={batchesAffected}
                    />
                    <SummaryCard
                        label="Classes Affected"
                        value={classesAffected}
                    />
                </div>
{/* Filter bar */}
                <div className="shrink-0 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <div>
                            <label
                                htmlFor="batch_id"
                                className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                            >
                                Batch
                            </label>
                            <select
                                id="batch_id"
                                className={`mt-1 w-full ${selectClass}`}
                                value={filters.batch_id ?? ''}
                                onChange={(e) =>
                                    applyFilter('batch_id', e.target.value)
                                }
                            >
                                <option value="">All batches</option>
                                {Object.entries(batches).map(([id, name]) => (
                                    <option key={id} value={id}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label
                                htmlFor="class_id"
                                className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                            >
                                Class
                            </label>
                            <select
                                id="class_id"
                                className={`mt-1 w-full ${selectClass}`}
                                value={filters.class_id ?? ''}
                                onChange={(e) =>
                                    applyFilter('class_id', e.target.value)
                                }
                            >
                                <option value="">All classes</option>
                                {classes.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.display_name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label
                                htmlFor="stream_id"
                                className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                            >
                                Stream
                            </label>
                            <select
                                id="stream_id"
                                className={`mt-1 w-full ${selectClass}`}
                                value={filters.stream_id ?? ''}
                                onChange={(e) =>
                                    applyFilter('stream_id', e.target.value)
                                }
                            >
                                <option value="">All streams</option>
                                {Object.entries(streams).map(([id, name]) => (
                                    <option key={id} value={id}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {anyFilterSet && (
                            <div className="flex items-end">
                                <Link
                                    href={route('admin.attendance.defaulters')}
                                    className="pb-2 text-sm font-medium text-gray-600 hover:text-navy"
                                >
                                    Clear filters
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
{/* Main table */}
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    {students.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-3 p-12 text-center">
                            <span className="text-3xl" aria-hidden="true">
                                ✓
                            </span>
                            <p className="text-sm font-medium text-gray-700">
                                No students below the threshold. Well done!
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="min-h-0 flex-1 overflow-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="sticky top-0 z-10 bg-surface">
                                        <tr>
                                            <th className={thClass}>Roll #</th>
                                            <th className={thClass}>
                                                Student Name
                                            </th>
                                            <th className={thClass}>Batch</th>
                                            <th className={thClass}>Class</th>
                                            <th className={thClass}>Present</th>
                                            <th className={thClass}>Absent</th>
                                            <th className={thClass}>Late</th>
                                            <th className={thClass}>Leave</th>
                                            <th className={thClass}>Total</th>
                                            <th className={thClass}>
                                                Percentage
                                            </th>
                                            <th className={thClass}>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {students.map((student) => (
                                            <DefaulterRowView
                                                key={student.student_profile_id}
                                                student={student}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="shrink-0 border-t border-gray-200 bg-surface px-4 py-3">
                                <p className="text-sm text-gray-700">
                                    {students.length} student
                                    {students.length === 1 ? '' : 's'} below the
                                    75% threshold
                                </p>
                            </div>
                        </>
                    )}
                </div>
{/* Breakdowns */}
                <div className="grid shrink-0 grid-cols-1 gap-4 lg:grid-cols-2">
                    <div className={cardClass}>
                        <h3 className="font-serif text-base font-semibold text-navy">
                            Defaulters by Class
                        </h3>

                        {by_class.length === 0 ? (
                            <p className="mt-4 text-sm text-gray-600">
                                No defaulters.
                            </p>
                        ) : (
                            <ul className="mt-4 space-y-2">
                                {by_class.map((row) => (
                                    <li
                                        key={String(row.class_id)}
                                        className="flex items-center justify-between gap-3 text-sm"
                                    >
                                        <span className="text-gray-700">
                                            {row.class_display_name ??
                                                'Not enrolled'}
                                        </span>
                                        <span className="font-semibold text-red-600">
                                            {row.defaulter_count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className={cardClass}>
                        <h3 className="font-serif text-base font-semibold text-navy">
                            Defaulters by Batch
                        </h3>

                        {by_batch.length === 0 ? (
                            <p className="mt-4 text-sm text-gray-600">
                                No defaulters.
                            </p>
                        ) : (
                            <ul className="mt-4 space-y-2">
                                {by_batch.map((row) => (
                                    <li
                                        key={String(row.batch_id)}
                                        className="flex items-center justify-between gap-3 text-sm"
                                    >
                                        <span className="text-gray-700">
                                            {row.batch_name}
                                        </span>
                                        <span className="font-semibold text-red-600">
                                            {row.defaulter_count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
/**
 * One defaulter.
 *
 * Split out so the table body stays readable: eleven columns inlined would
 * bury the two numbers that matter, which are the percentage and how far
 * below the threshold the student is.
 */
function DefaulterRowView({ student }: { student: DefaulterRow }) {
    return (
        <tr className="hover:bg-surface">
            <td className={`${tdClass} font-semibold text-navy`}>
                {student.roll_number}
            </td>
            <td className={`${tdClass} font-medium text-gray-900`}>
                {student.student_name}
            </td>
            <td className={tdClass}>{student.batch_name}</td>

            {/* A student on the roll with no live enrollment is still a
                defaulter and still needs contacting, so they are named
                rather than left blank. */}
            <td className={tdClass}>
                {student.class_display_name ?? 'Not enrolled'}
            </td>
            <td className={tdClass}>{student.present}</td>
            <td className={tdClass}>{student.absent}</td>
            <td className={tdClass}>{student.late}</td>
            <td className={tdClass}>{student.leave}</td>
            <td className={tdClass}>{student.total}</td>
            <td className="whitespace-nowrap px-4 py-3">
                <span className="font-semibold text-red-600">
                    {student.percentage.toFixed(2)}%
                </span>
                <span className="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                    {student.shortfall.toFixed(2)}% short
                </span>
            </td>
            <td className="whitespace-nowrap px-4 py-3">
                <div className="flex items-center gap-3">
                    <Link
                        href={route(
                            'admin.students.show',
                            student.student_profile_id,
                        )}
                        className="text-sm font-medium text-navy hover:text-gold"
                    >
                        View Student
                    </Link>

                    {/*
                        Deliberately disabled rather than hidden: the office
                        expects to notify a parent from this list, and a
                        greyed-out button is honest about what exists and
                        what is coming.
                    */}
                    <button
                        type="button"
                        disabled
                        title="SMS/email coming soon"
                        className="rounded-md bg-gray-100 px-3 py-1 text-xs font-medium text-gray-400"
                    >
                        Notify
                    </button>
                </div>
            </td>
        </tr>
    );
}