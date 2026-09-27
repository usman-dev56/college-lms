import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface AcademicSession {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
}

type AcademicSessionsPageProps = {
    sessions: AcademicSession[];
    flash?: {
        success?: string;
        error?: string;
    };
};

export default function Index() {
    const { sessions, flash } = usePage<PageProps<AcademicSessionsPageProps>>().props;

    const handleActivate = (session: AcademicSession) => {
        if (
            !confirm(
                `Activate session "${session.name}"? This will deactivate the currently active session.`,
            )
        ) {
            return;
        }

        router.patch(route('admin.academic-sessions.activate', session.id));
    };

    const handleDelete = (session: AcademicSession) => {
        if (
            !confirm(
                `Delete session "${session.name}"? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(route('admin.academic-sessions.destroy', session.id));
    };

    const formatDate = (iso: string) => {
        const d = new Date(iso);
        return d.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Academic Sessions
                    </h2>
                    <Link
                        href={route('admin.academic-sessions.create')}
                        className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Session
                    </Link>
                </div>
            }
        >
            <Head title="Academic Sessions" />

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

            <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-surface">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                Name
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                Start Date
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                End Date
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                Status
                            </th>
                            <th className="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 bg-white">
                        {sessions.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-6 py-12 text-center text-sm text-gray-500"
                                >
                                    No academic sessions yet. Create the first
                                    one to get started.
                                </td>
                            </tr>
                        )}

                        {sessions.map((session) => (
                            <tr key={session.id} className="hover:bg-surface">
                                <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-navy">
                                    {session.name}
                                </td>
                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    {formatDate(session.start_date)}
                                </td>
                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    {formatDate(session.end_date)}
                                </td>
                                <td className="whitespace-nowrap px-6 py-4 text-sm">
                                    {session.is_active ? (
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
                                        {!session.is_active && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleActivate(session)
                                                }
                                                className="font-medium text-green-700 hover:text-green-900"
                                            >
                                                Activate
                                            </button>
                                        )}
                                        <Link
                                            href={route(
                                                'admin.academic-sessions.edit',
                                                session.id,
                                            )}
                                            className="font-medium text-navy hover:text-gold"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleDelete(session)
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
        </AuthenticatedLayout>
    );
}