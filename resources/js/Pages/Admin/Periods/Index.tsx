import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface Period {
    id: number;
    number: number;
    label: string;
    start_time: string;
    end_time: string;
    is_break: boolean;
}

interface SessionOption {
    id: number;
    name: string;
    is_active: boolean;
}

type PeriodsPageProps = {
    periods: Period[];
    sessions: SessionOption[];
    selectedSessionId: number;
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
    const { periods, sessions, selectedSessionId, flash } =
        usePage<PageProps<PeriodsPageProps>>().props;

    // Filtering happens on the server so the session in the URL is
    // shareable and survives a refresh.
    const changeSession = (sessionId: number) => {
        router.get(
            route('admin.periods.index'),
            { session_id: sessionId },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const handleDelete = (period: Period) => {
        if (
            !window.confirm(
                `Delete "${period.label}" (${period.start_time}-${period.end_time})? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(route('admin.periods.destroy', period.id), {
            preserveScroll: true,
        });
    };

    const teachingCount = periods.filter((p) => !p.is_break).length;
    const breakCount = periods.length - teachingCount;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Periods
                    </h2>
                    <Link
                        href={route('admin.periods.create', {
                            session_id: selectedSessionId,
                        })}
                        className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Period
                    </Link>
                </div>
            }
        >
            <Head title="Periods" />

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
                    <div className="w-full sm:w-64">
                        <label
                            htmlFor="session-filter"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Academic Session
                        </label>
                        <select
                            id="session-filter"
                            value={selectedSessionId}
                            className={selectClass}
                            onChange={(e) =>
                                changeSession(Number(e.target.value))
                            }
                        >
                            {sessions.map((session) => (
                                <option
                                    key={session.id}
                                    value={session.id}
                                >
                                    {session.name}
                                    {session.is_active ? ' (active)' : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    <p className="ml-auto flex h-10 items-center text-sm text-gray-500">
                        {periods.length === 0
                            ? 'No periods yet'
                            : `${periods.length} periods — ${teachingCount} teaching, ${breakCount} break${
                                  breakCount === 1 ? '' : 's'
                              }`}
                    </p>
                </div>

                {periods.length === 0 ? (
                    <div className="rounded-lg bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-200">
                        <p className="text-sm text-gray-600">
                            No periods configured for this session yet.
                            Create the first one to get started.
                        </p>
                        <Link
                            href={route('admin.periods.create', {
                                session_id: selectedSessionId,
                            })}
                            className="mt-4 inline-block rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                        >
                            New Period
                        </Link>
                    </div>
                ) : (
                    <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        <div className="min-h-0 flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>#</th>
                                        <th className={thClass}>Label</th>
                                        <th className={thClass}>
                                            Start Time
                                        </th>
                                        <th className={thClass}>
                                            End Time
                                        </th>
                                        <th className={thClass}>Type</th>
                                        <th
                                            className={`${thClass} text-right`}
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {periods.map((period) => (
                                        <tr
                                            key={period.id}
                                            className={
                                                period.is_break
                                                    ? 'bg-gray-50 text-gray-500'
                                                    : 'hover:bg-surface'
                                            }
                                        >
                                            <td
                                                className={`${tdClass} font-medium text-navy`}
                                            >
                                                {period.number}
                                            </td>
                                            <td
                                                className={`${tdClass} font-medium text-navy`}
                                            >
                                                {period.label}
                                            </td>
                                            <td className={tdClass}>
                                                {period.start_time}
                                            </td>
                                            <td className={tdClass}>
                                                {period.end_time}
                                            </td>
                                            <td className={tdClass}>
                                                {period.is_break ? (
                                                    <span className="inline-flex items-center rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700">
                                                        Break
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center rounded-full bg-surface px-3 py-1 text-xs font-semibold text-navy ring-1 ring-gray-200">
                                                        Teaching
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                className={`${tdClass} text-right`}
                                            >
                                                <div className="flex items-center justify-end gap-3">
                                                    <Link
                                                        href={route(
                                                            'admin.periods.edit',
                                                            period.id,
                                                        )}
                                                        className="font-medium text-navy hover:text-gold"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(
                                                                period,
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