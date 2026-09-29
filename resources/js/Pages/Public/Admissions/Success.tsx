import GuestLayout from '@/Layouts/GuestLayout';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

type SuccessAdmissionPageProps = {
    application_number: string;
    applicant_name: string;
    stream_name: string | null;
    batch_name: string | null;
    submitted_at: string | null;
};

/** One labelled line in the receipt card. */
function ReceiptRow({
    label,
    value,
    emphasis = false,
}: {
    label: string;
    value: string | null;
    /** The application number is set larger: it is the thing to copy. */
    emphasis?: boolean;
}) {
    return (
        <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100 py-2 last:border-b-0">
            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                {label}
            </span>
            <span
                className={
                    emphasis
                        ? 'font-mono text-base font-bold text-navy'
                        : 'text-sm text-gray-800'
                }
            >
                {value ?? '—'}
            </span>
        </div>
    );
}

export default function Success() {
    const {
        application_number,
        applicant_name,
        stream_name,
        batch_name,
        submitted_at,
    } = usePage<PageProps<SuccessAdmissionPageProps>>().props;

    return (
        <GuestLayout title="Application Submitted">
            <div className="text-center">
                {/* A tick rather than a heading alone, so the page reads as a
                    confirmation at a glance before any text is read. */}
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                    <svg
                        className="h-6 w-6 text-green-700"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth={2.5}
                        stroke="currentColor"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M4.5 12.75l6 6 9-13.5"
                        />
                    </svg>
                </div>

                <h1 className="mt-4 font-serif text-2xl font-bold text-navy">
                    Application Submitted
                </h1>
                <div className="mx-auto mt-2 h-1 w-16 bg-gold" />
                <p className="mt-4 text-sm text-gray-600">
                    Your application has been received.
                </p>
            </div>

            <div className="mt-6 rounded-lg bg-surface p-5 ring-1 ring-gray-200">
                <ReceiptRow
                    label="Application Number"
                    value={application_number}
                    emphasis
                />
                <ReceiptRow label="Applicant" value={applicant_name} />
                <ReceiptRow label="Stream" value={stream_name} />
                <ReceiptRow label="Batch" value={batch_name} />
                <ReceiptRow label="Submitted" value={submitted_at} />
            </div>

            <p className="mt-4 text-center text-sm text-gray-600">
                Please keep this application number for future reference.
            </p>
            <p className="mt-2 text-center text-xs text-gray-500">
                The application number is the only way to check on this
                application, so keep it somewhere safe.
            </p>

            <div className="mt-6 text-center">
                <Link
                    href="/"
                    className="text-sm font-medium text-navy hover:text-gold"
                >
                    Return to Home
                </Link>
            </div>
        </GuestLayout>
    );
}
