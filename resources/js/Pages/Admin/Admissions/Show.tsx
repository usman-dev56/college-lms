import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

interface AdmissionData {
    id: number;
    application_number: string;
    applicant_name: string;
    father_name: string | null;
    cnic_bform: string;
    date_of_birth: string | null;
    phone: string | null;
    guardian_phone: string | null;
    address: string | null;
    previous_school: string | null;
    previous_marks_obtained: number | null;
    previous_marks_total: number | null;
    merit_percentage: number | null;
    merit_rank: number | null;
    status: string;
    rejection_reason: string | null;
    reviewed_at: string | null;
    reviewed_by_name: string | null;
    created_at: string | null;
    stream_name: string | null;
    batch_name: string | null;
    enrolled_student_profile_id: number | null;
    enrolled_student_name: string | null;
}

type ShowAdmissionPageProps = {
    admission: AdmissionData;
};

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

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

const primaryAction =
    'block w-full rounded-md bg-navy px-4 py-2.5 text-sm font-semibold uppercase tracking-wider text-white transition hover:bg-navy-dark disabled:cursor-not-allowed disabled:bg-gray-300';

const secondaryAction =
    'block w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold uppercase tracking-wider text-gray-700 transition hover:bg-surface disabled:cursor-not-allowed disabled:opacity-50';

const dangerAction =
    'block w-full rounded-md border border-red-300 px-4 py-2.5 text-sm font-semibold uppercase tracking-wider text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50';

/** A card with a heading and a list of label/value rows. */
function Card({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className={cardClass}>
            <h3 className="font-serif text-base font-semibold text-navy">
                {title}
            </h3>
            <dl className="mt-4 space-y-3">{children}</dl>
        </div>
    );
}

/** One label and value. A missing value shows a dash, never a gap. */
function Row({ label, value }: { label: string; value: ReactNode }) {
    const empty = value === null || value === undefined || value === '';

    return (
        <div className="flex flex-wrap justify-between gap-2">
            <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                {label}
            </dt>
            <dd className="text-sm text-gray-800">
                {empty ? (
                    <span className="text-gray-400">—</span>
                ) : (
                    value
                )}
            </dd>
        </div>
    );
}


export default function Show() {
    const { admission } = usePage<PageProps<ShowAdmissionPageProps>>().props;

    // Marking as reviewed is a low-stakes acknowledgement, so it asks
    // nothing. Accepting and rejecting both change what the college has
    // promised, so both confirm first.
    const markReviewed = () => {
        router.patch(route('admin.admissions.review', admission.id), {}, {
            preserveScroll: true,
        });
    };

    const accept = () => {
        if (
            !window.confirm(
                `Accept ${admission.applicant_name}'s application ${admission.application_number}?`,
            )
        ) {
            return;
        }

        router.patch(route('admin.admissions.accept', admission.id), {}, {
            preserveScroll: true,
        });
    };

    /*
        Rejection asks for a reason through a plain prompt.

        A prompt is not as good as a dialog, and this is the one place it
        shows: the reason box is single-line and there is nowhere to show
        guidance. It is still better than a bare confirm, because a rejection
        with no reason is refused by the server anyway and the admin would be
        left wondering why nothing happened. Cancelling, or submitting blank,
        aborts without sending anything.
    */
    const reject = () => {
        const reason = window.prompt('Reason for rejection (required):', '');

        if (reason === null) {
            return;
        }

        if (reason.trim() === '') {
            window.alert('A reason is required to reject an application.');
            return;
        }

        router.patch(
            route('admin.admissions.reject', admission.id),
            { rejection_reason: reason },
            { preserveScroll: true },
        );
    };

    /*
        Converting creates a real login, so it confirms first. The wording
        says the password is shown once, because it genuinely is: the server
        flashes it to this one response and the User model hashes it, so
        there is no second chance to read it. The admin needs to know that
        before they click, not after.
    */
    const convert = () => {
        if (
            !window.confirm(
                'Convert this application to a student? A login will be ' +
                    'created and the password will be shown once.',
            )
        ) {
            return;
        }

        router.patch(route('admin.admissions.convert', admission.id), {}, {
            preserveScroll: true,
        });
    };

    const isPending = admission.status === 'pending';
    const isReviewed = admission.status === 'reviewed';
    const isAccepted = admission.status === 'accepted';
    const isRejected = admission.status === 'rejected';
    const isEnrolled = admission.status === 'enrolled';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            {admission.applicant_name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Application {admission.application_number}
                        </p>
                    </div>
                    <Link
                        href={route('admin.admissions.index')}
                        className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-gray-700 hover:bg-surface"
                    >
                        Back to Admissions
                    </Link>
                </div>
            }
        >
            <Head title={admission.applicant_name} />

            <div className="flex h-full min-h-0 flex-col gap-4 lg:flex-row lg:items-start">
                {/* Left column: the application itself */}
                <div className="flex w-full flex-1 flex-col gap-4">
                    <div className={cardClass}>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="font-mono text-2xl font-bold text-navy">
                                    {admission.application_number}
                                </p>
                                <h3 className="mt-2 font-serif text-xl font-semibold text-gray-900">
                                    {admission.applicant_name}
                                </h3>
                                {admission.father_name && (
                                    <p className="text-sm text-gray-500">
                                        S/o {admission.father_name}
                                    </p>
                                )}
                            </div>
                            <span
                                className={
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ' +
                                    (statusStyles[admission.status] ??
                                        'bg-gray-100 text-gray-600')
                                }
                            >
                                {statusLabels[admission.status] ??
                                    admission.status}
                            </span>
                        </div>

                        <dl className="mt-6 space-y-3 border-t border-gray-100 pt-4">
                            <Row
                                label="CNIC / B-Form"
                                value={admission.cnic_bform}
                            />
                            <Row
                                label="Date of Birth"
                                value={admission.date_of_birth}
                            />
                            <Row label="Phone" value={admission.phone} />
                            <Row
                                label="Guardian Phone"
                                value={admission.guardian_phone}
                            />
                            <Row
                                label="Address"
                                value={admission.address}
                            />
                        </dl>
                    </div>

                    <Card title="Academic Background">
                        <Row
                            label="Previous School"
                            value={admission.previous_school}
                        />
                        <Row
                            label="Previous Marks"
                            value={
                                admission.previous_marks_obtained ===
                                null
                                    ? null
                                    : `${admission.previous_marks_obtained} / ${admission.previous_marks_total ?? '—'}`
                            }
                        />
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Merit
                            </dt>
                            <dd className="font-serif text-2xl font-bold text-navy">
                                {admission.merit_percentage !== null
                                    ? `${admission.merit_percentage.toFixed(2)}%`
                                    : '—'}
                            </dd>
                        </div>
                        <Row
                            label="Merit Rank"
                            value={
                                admission.merit_rank !== null
                                    ? `#${admission.merit_rank}`
                                    : 'Not ranked yet'
                            }
                        />
                    </Card>

                    <Card title="Applied For">
                        <Row
                            label="Stream"
                            value={admission.stream_name}
                        />
                        <Row label="Batch" value={admission.batch_name} />
                        <Row
                            label="Submitted"
                            value={admission.created_at}
                        />
                    </Card>
                </div>

                {/*
                    Right column: what the office can do about it.

                    Sticky on a wide screen so the buttons stay reachable while
                    scrolling a long address or academic history. Each status
                    offers only the moves that are legal from there, which
                    mirrors the controller's guard - a button that is not
                    rendered cannot be pressed to make a request the server
                    would refuse with a 422.
                */}
                <div className="w-full flex-col gap-4 lg:w-80 lg:shrink-0 lg:sticky lg:top-0">
                    <div className={cardClass}>
                        <h3 className="font-serif text-base font-semibold text-navy">
                            Actions
                        </h3>

                        <div className="mt-4 space-y-3">
                            {isPending && (
                                <>
                                    <button
                                        type="button"
                                        onClick={markReviewed}
                                        className={secondaryAction}
                                    >
                                        Mark as Reviewed
                                    </button>
                                    <button
                                        type="button"
                                        onClick={accept}
                                        className={primaryAction}
                                    >
                                        Accept
                                    </button>
                                    <button
                                        type="button"
                                        onClick={reject}
                                        className={dangerAction}
                                    >
                                        Reject
                                    </button>
                                </>
                            )}

                            {isReviewed && (
                                <>
                                    <button
                                        type="button"
                                        onClick={accept}
                                        className={primaryAction}
                                    >
                                        Accept
                                    </button>
                                    <button
                                        type="button"
                                        onClick={reject}
                                        className={dangerAction}
                                    >
                                        Reject
                                    </button>
                                    <p className="text-xs text-gray-500">
                                        Already reviewed. It cannot be marked as
                                        reviewed a second time.
                                    </p>
                                </>
                            )}

                            {isAccepted && (
                                <>
                                    <button
                                        type="button"
                                        onClick={convert}
                                        className={primaryAction}
                                    >
                                        Convert to Student
                                    </button>
                                    <p className="text-xs text-gray-500">
                                        Creates a portal login and adds the
                                        student to the roll. The password is
                                        shown once, immediately after.
                                    </p>
                                </>
                            )}

                            {/*
                                Every other status offers the button, disabled,
                                rather than hiding it. An admin looking at a
                                pending application should be able to see that
                                conversion exists and that acceptance is what
                                unlocks it, instead of wondering whether the
                                feature is missing.
                            */}
                            {!isAccepted && !isEnrolled && (
                                <>
                                    <button
                                        type="button"
                                        disabled
                                        className={primaryAction}
                                    >
                                        Convert to Student
                                    </button>
                                    <p className="text-xs text-gray-500">
                                        Only accepted applications can be
                                        converted.
                                    </p>
                                </>
                            )}

                            {isRejected && (
                                <>
                                    <button
                                        type="button"
                                        disabled
                                        className={secondaryAction}
                                    >
                                        Reopen
                                    </button>
                                    <p className="text-xs text-gray-500">
                                        Reopening a rejected application is not
                                        implemented yet. A new application can
                                        be submitted instead.
                                    </p>
                                </>
                            )}

                            {isEnrolled && (
                                <div className="rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                                    {admission.enrolled_student_profile_id ? (
                                        <Link
                                            href={route(
                                                'admin.students.show',
                                                admission.enrolled_student_profile_id,
                                            )}
                                            className="font-medium underline"
                                        >
                                            View student profile
                                            {admission.enrolled_student_name
                                                ? ` (${admission.enrolled_student_name})`
                                                : ''}
                                        </Link>
                                    ) : (
                                        <p>
                                            This applicant has been enrolled.
                                        </p>
                                    )}
                                </div>
                            )}

                            <Link
                                href={route('admin.admissions.index')}
                                className="block pt-2 text-center text-sm font-medium text-gray-600 hover:text-navy"
                            >
                                Back to Admissions
                            </Link>
                        </div>
                    </div>

                    {admission.reviewed_at && (
                        <Card title="Review Info">
                            <Row
                                label="Reviewed At"
                                value={admission.reviewed_at}
                            />
                            <Row
                                label="Reviewed By"
                                value={admission.reviewed_by_name}
                            />
                            {admission.rejection_reason && (
                                <div className="rounded-md bg-red-50 p-3 ring-1 ring-red-200">
                                    <dt className="text-xs font-semibold uppercase tracking-wider text-red-800">
                                        Rejection Reason
                                    </dt>
                                    <dd className="mt-1 text-sm text-red-700">
                                        {admission.rejection_reason}
                                    </dd>
                                </div>
                            )}
                        </Card>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
