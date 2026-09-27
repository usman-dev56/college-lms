import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface Stream {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
}

type StreamsPageProps = {
    streams: Stream[];
    flash?: {
        success?: string;
        error?: string;
    };
};

export default function Index() {
    const { streams, flash } = usePage<PageProps<StreamsPageProps>>().props;

    const handleDelete = (stream: Stream) => {
        if (
            !window.confirm(
                `Delete stream "${stream.name}"? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(route('admin.streams.destroy', stream.id));
    };

    const truncate = (value: string | null) => {
        if (!value) {
            return '—';
        }

        return value.length > 70 ? `${value.slice(0, 70)}…` : value;
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Streams
                    </h2>
                    <Link
                        href={route('admin.streams.create')}
                        className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Stream
                    </Link>
                </div>
            }
        >
            <Head title="Streams" />

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

            {streams.length === 0 ? (
                <div className="rounded-lg bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <p className="text-sm text-gray-600">
                        No streams yet. Create the first one to get started.
                    </p>
                    <Link
                        href={route('admin.streams.create')}
                        className="mt-4 inline-block rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                    >
                        New Stream
                    </Link>
                </div>
            ) : (
                <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-surface">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                    Name
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                    Code
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                    Description
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
                            {streams.map((stream) => (
                                <tr key={stream.id} className="hover:bg-surface">
                                    <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-navy">
                                        {stream.name}
                                    </td>
                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                        <span className="inline-flex items-center rounded-md bg-surface px-2 py-1 font-mono text-xs font-semibold text-navy ring-1 ring-gray-200">
                                            {stream.code}
                                        </span>
                                    </td>
                                    <td
                                        className="px-6 py-4 text-sm text-gray-700"
                                        title={
                                            stream.description ?? undefined
                                        }
                                    >
                                        {truncate(stream.description)}
                                    </td>
                                    <td className="whitespace-nowrap px-6 py-4 text-sm">
                                        {stream.is_active ? (
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
                                                    'admin.streams.edit',
                                                    stream.id,
                                                )}
                                                className="font-medium text-navy hover:text-gold"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleDelete(stream)
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
