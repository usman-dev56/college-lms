import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface RankedApplication {
    id: number;
    application_number: string;
    applicant_name: string;
    father_name: string | null;
    cnic_bform: string;
    merit_percentage: number;
    previous_marks_obtained: number | null;
    previous_marks_total: number | null;
    stream_name: string | null;
    batch_name: string | null;
    merit_rank: number;
    status: string;
}

interface UnrankedApplication {
    id: number;
    application_number: string;
    applicant_name: string;
    cnic_bform: string;
    status: string;
}

interface BatchOption {
    id: number;
    name: string;
}

interface StreamOption {
    id: number;
    name: string;
    code: string | null;
}

type MeritListPageProps = {
    applications: RankedApplication[];
    unranked: UnrankedApplication[];
    batches: BatchOption[];
    streams: StreamOption[];
    selected_batch_id: number | null;
    selected_stream_id: number | null;
};

const thClass =
    'px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-6 py-4 text-sm text-gray-700';

const selectClass =
    'block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    reviewed: 'bg-blue-100 text-blue-800',
    accepted: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    enrolled: 'bg-purple-100 text-purple-800',
};

const statusLabels: Record<string, string> = {
    pending: 'Pending',
    reviewed: 'Reviewed',
    accepted: 'Accepted',
    rejected: 'Rejected',
    enrolled: 'Enrolled',
};

const StatusBadge = ({ status }: { status: string }) => (
    <span
        className={
            'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ' +
            (statusStyles[status] ?? 'bg-gray-100 text-gray-600')
        }
    >
        {statusLabels[status] ?? status}
    </span>
);

export default function MeritList() {
    const {
        applications,
        unranked,
        batches,
        streams,
        selected_batch_id,
        selected_stream_id,
    } = usePage<PageProps<MeritListPageProps>>().props;

    /*
        Filtering is a plain GET rather than an in-place router visit on a
        controlled select. The list is a generated document, not an editable
        form: there is nothing on screen to lose by a full reload, and a
        shareable URL that reproduces exactly this ranking is worth more here
        than instant swapping would be.
    */
    const reload = (params: { batch_id?: string; stream_id?: string }) => {
        const query: Record<string, string> = {};

        if (params.batch_id !== undefined) {
            query.batch_id = params.batch_id;
        } else if (selected_batch_id !== null) {
            query.batch_id = String(selected_batch_id);
        }

        if (params.stream_id !== undefined) {
            query.stream_id = params.stream_id;
        } else if (selected_stream_id !== null) {
            query.stream_id = String(selected_stream_id);
        }

        router.get(route('admin.admissions.merit-list', query), {}, {
            preserveScroll: true,
        });
    };

    const hasSelection = selected_batch_id !== null;
    const isEmpty = applications.length === 0 && unranked.length === 0;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Merit List
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Applications ranked by matric percentage.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {/* Placeholder: export lands with the office's
                            reporting needs, which are not settled yet.
                            Disabled rather than hidden so the page's shape is
                            honest about what is coming. */}
                        <button
                            type="button"
                            disabled
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-gray-400"
                        >
                            Export
                        </button>
                        <Link
                            href={route('admin.admissions.index')}
                            className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                        >
                            Back to Admissions
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Merit List" />

            <div className="flex h-full min-h-0 flex-col gap-4">
                {/* Filter bar */}
                <div className="shrink-0 flex flex-wrap items-end gap-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-gray-200">
                    <div className="w-full sm:w-48">
                        <label
                            htmlFor="merit-batch"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Batch
                        </label>
                        <select
                            id="merit-batch"
                            value={selected_batch_id ?? ''}
                            className={selectClass}
                            onChange={(e) =>
                                reload({ batch_id: e.target.value })
                            }
                        >
                            {batches.map((batch) => (
                                <option key={batch.id} value={batch.id}>
                                    {batch.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="w-full sm:w-52">
                        <label
                            htmlFor="merit-stream"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Stream
                        </label>
                        <select
                            id="merit-stream"
                            value={selected_stream_id ?? ''}
                            className={selectClass}
                            onChange={(e) =>
                                reload({ stream_id: e.target.value })
                            }
                        >
                            <option value="">All streams</option>
                            {streams.map((stream) => (
                                <option key={stream.id} value={stream.id}>
                                    {stream.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <p className="ml-auto flex h-10 items-center text-sm text-gray-500">
                        {applications.length} ranked
                        {unranked.length > 0 &&
                            `, ${unranked.length} awaiting marks`}
                    </p>
                </div>

                {/* Ranked applications */}
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    {isEmpty ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 p-12 text-center">
                            <p className="text-sm font-medium text-gray-700">
                                {hasSelection
                                    ? 'No applications to rank for the selected filters.'
                                    : 'Select a batch and stream to generate the merit list.'}
                            </p>
                            {!hasSelection && (
                                <p className="text-xs text-gray-500">
                                    A batch is needed before anything can be
                                    ranked.
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="min-h-0 flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>Rank</th>
                                        <th className={thClass}>
                                            Application #
                                        </th>
                                        <th className={thClass}>Applicant</th>
                                        <th className={thClass}>
                                            Father Name
                                        </th>
                                        <th className={thClass}>CNIC</th>
                                        <th className={thClass}>
                                            Previous Marks
                                        </th>
                                        <th className={thClass}>
                                            Percentage
                                        </th>
                                        <th className={thClass}>Stream</th>
                                        <th className={thClass}>Status</th>
                                        <th
                                            className={`${thClass} text-right`}
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {applications.map((application) => (
                                        <tr
                                            key={application.id}
                                            className="hover:bg-surface"
                                        >
                                            <td
                                                className={`${tdClass} font-serif text-lg font-bold text-navy`}
                                            >
                                                {application.merit_rank}
                                            </td>
                                            <td
                                                className={`${tdClass} font-mono text-xs font-semibold text-navy`}
                                            >
                                                {
                                                    application.application_number
                                                }
                                            </td>
                                            <td
                                                className={`${tdClass} font-medium text-gray-900`}
                                            >
                                                {application.applicant_name}
                                            </td>
                                            <td className={tdClass}>
                                                {application.father_name ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {application.cnic_bform}
                                            </td>
                                            <td className={tdClass}>
                                                {application.previous_marks_obtained}{' '}
                                                /{' '}
                                                {
                                                    application.previous_marks_total ??
                                                    '—'
                                                }
                                            </td>
                                            <td
                                                className={`${tdClass} font-semibold text-navy`}
                                            >
                                                {application.merit_percentage.toFixed(
                                                    2,
                                                )}
                                                %
                                            </td>
                                            <td className={tdClass}>
                                                {application.stream_name ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                <StatusBadge
                                                    status={application.status}
                                                />
                                            </td>
                                            <td
                                                className={`${tdClass} text-right`}
                                            >
                                                <div className="flex items-center justify-end gap-3">
                                                    <Link
                                                        href={route(
                                                            'admin.admissions.show',
                                                            application.id,
                                                        )}
                                                        className="font-medium text-navy hover:text-gold"
                                                    >
                                                        View
                                                    </Link>
                                                    {/*
                                                        Only accepted
                                                        applications can
                                                        become students; the
                                                        rest show View alone,
                                                        so the column never
                                                        offers an action the
                                                        server would refuse.
                                                    */}
                                                    {application.status ===
                                                        'accepted' && (
                                                        <Link
                                                            href={route(
                                                                'admin.admissions.show',
                                                                application.id,
                                                            )}
                                                            className="font-medium text-green-700 hover:text-green-800"
                                                        >
                                                            Convert
                                                        </Link>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/*
                    Applications that cannot be ranked. They are shown rather
                    than dropped: a missing total is a data-entry slip on a
                    public form, and an applicant who quietly vanishes from
                    the merit list looks exactly like one who was never
                    applied.
                */}
                {unranked.length > 0 && (
                    <div className="shrink-0 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        <div className="border-b border-gray-100 px-6 py-4">
                            <h3 className="font-serif text-base font-semibold text-navy">
                                Applications Without Complete Marks (
                                {unranked.length})
                            </h3>
                            <p className="mt-1 text-xs text-gray-500">
                                These applications cannot be ranked because
                                previous marks are missing.
                            </p>
                        </div>
                        <div className="max-h-72 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>
                                            Application #
                                        </th>
                                        <th className={thClass}>Applicant</th>
                                        <th className={thClass}>CNIC</th>
                                        <th className={thClass}>Status</th>
                                        <th
                                            className={`${thClass} text-right`}
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {unranked.map((application) => (
                                        <tr
                                            key={application.id}
                                            className="hover:bg-surface"
                                        >
                                            <td
                                                className={`${tdClass} font-mono text-xs font-semibold text-navy`}
                                            >
                                                {
                                                    application.application_number
                                                }
                                            </td>
                                            <td
                                                className={`${tdClass} font-medium text-gray-900`}
                                            >
                                                {application.applicant_name}
                                            </td>
                                            <td className={tdClass}>
                                                {application.cnic_bform}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                <StatusBadge
                                                    status={application.status}
                                                />
                                            </td>
                                            <td
                                                className={`${tdClass} text-right`}
                                            >
                                                <Link
                                                    href={route(
                                                        'admin.admissions.show',
                                                        application.id,
                                                    )}
                                                    className="font-medium text-navy hover:text-gold"
                                                >
                                                    View
                                                </Link>
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
