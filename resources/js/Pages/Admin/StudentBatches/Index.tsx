import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface StudentBatch {
    id: number;
    name: string;
    start_grade: number;
    expected_graduation_year: number;
    is_active: boolean;
    notes: string | null;
    students_count: number;
    current_grade: number | null;
}

type StudentBatchesPageProps = {
    batches: StudentBatch[];
    flash?: {
        success?: string;
        error?: string;
    };
};

const thClass =
    'px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-6 py-4 text-sm text-gray-700';

const selectClass =
    'block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

export default function Index() {
    const { batches, flash } =
        usePage<PageProps<StudentBatchesPageProps>>().props;

    const [status, setStatus] = useState('all');

    // Filtered in the browser, the same way the subjects and classes lists do
    // it. A college runs a handful of cohorts, so there is nothing to gain
    // from asking the server again on every change.
    const filtered = useMemo(
        () =>
            batches.filter((batch) =>
                status === 'all'
                    ? true
                    : batch.is_active === (status === 'active'),
            ),
        [batches, status],
    );

    const handleDelete = (batch: StudentBatch) => {
        if (
            !window.confirm(
                `Delete batch "${batch.name}"? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(route('admin.student-batches.destroy', batch.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Student Batches
                    </h2>
                    <Link
                        href={route('admin.student-batches.create')}
                        className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Batch
                    </Link>
                </div>
            }
        >
            <Head title="Student Batches" />

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

            <div className="flex h-full min-h-0 flex-col gap-4">
                {/* Filter bar */}
                <div className="shrink-0 flex flex-wrap items-end gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
                    <div className="w-full sm:w-56">
                        <label
                            htmlFor="status-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Status
                        </label>
                        <select
                            id="status-filter"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className={`mt-1 ${selectClass}`}
                        >
                            <option value="all">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <p className="ml-auto flex h-10 items-center text-sm text-gray-500">
                        Showing {filtered.length} of {batches.length} batches
                    </p>
                </div>

                {filtered.length === 0 ? (
                    <div className="rounded-lg bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-200">
                        <p className="text-sm text-gray-600">
                            {batches.length === 0
                                ? 'No student batches yet. Create the first one to get started.'
                                : 'No batches match the selected status.'}
                        </p>
                        {batches.length === 0 && (
                            <Link
                                href={route(
                                    'admin.student-batches.create',
                                )}
                                className="mt-4 inline-block rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                            >
                                New Batch
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        <div className="min-h-0 flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>Name</th>
                                        <th className={thClass}>
                                            Start Grade
                                        </th>
                                        <th className={thClass}>
                                            Expected Graduation
                                        </th>
                                        <th className={thClass}>
                                            Current Grade
                                        </th>
                                        <th className={thClass}>Status</th>
                                        <th
                                            className={`${thClass} text-right`}
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {filtered.map((batch) => (
                                        <tr
                                            key={batch.id}
                                            className="hover:bg-surface"
                                        >
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-navy">
                                                {batch.name}
                                            </td>
                                            <td className={tdClass}>
                                                Grade {batch.start_grade}
                                            </td>
                                            <td className={tdClass}>
                                                {
                                                    batch.expected_graduation_year
                                                }
                                            </td>
                                            <td className={tdClass}>
                                                {batch.current_grade ===
                                                null ? (
                                                    <span className="text-gray-500">
                                                        —
                                                    </span>
                                                ) : (
                                                    `Grade ${batch.current_grade}`
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                {batch.is_active ? (
                                                    <span className="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                                        Inactive
                                                    </span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                <div className="flex items-center justify-end gap-3">
                                                    <Link
                                                        href={route(
                                                            'admin.student-batches.edit',
                                                            batch.id,
                                                        )}
                                                        className="font-medium text-navy hover:text-gold"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(
                                                                batch,
                                                            )
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