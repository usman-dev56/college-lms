import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import { STATUS_OPTIONS } from './StudentFormFields';

interface StudentData {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    roll_number: string;
    board_registration_number: string | null;
    cnic_bform: string | null;
    date_of_birth: string | null;
    gender: string | null;
    father_name: string | null;
    guardian_phone: string | null;
    address: string | null;
    admission_date: string | null;
    previous_school: string | null;
    previous_marks_obtained: number | null;
    previous_marks_total: number | null;
    status: string;
    created_at: string | null;
    batch: {
        id: number | null;
        name: string | null;
        start_grade: number | null;
        current_grade: number | null;
    };
}

type ShowStudentPageProps = {
    student: StudentData;

    /**
     * Class enrollments. Always empty in 3.2 - the enrollments table does
     * not exist until 3.4. Typed as the shape the relation will return
     * rather than `never[]`, so the card below is written once against the
     * real thing instead of being rewritten when it lands.
     */
    enrollments: {
        id: number;
        class_id: number;
        class_display_name: string;
    }[];
};

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

const statusStyles: Record<string, string> = {
    active: 'bg-green-100 text-green-800',
    graduated: 'bg-blue-100 text-blue-800',
    withdrawn: 'bg-gray-100 text-gray-600',
    suspended: 'bg-amber-100 text-amber-800',
};

const statusLabels: Record<string, string> = Object.fromEntries(
    STATUS_OPTIONS.map((option) => [option.value, option.label]),
);

/** A labelled card of detail rows. */
function DetailCard({
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
    const { student, enrollments } =
        usePage<PageProps<ShowStudentPageProps>>().props;

    const displayName = student.name ?? 'Unknown Student';

    // A percentage is only meaningful when both halves of the pair are
    // there; one without the other is shown as a dash rather than a
    // division by zero.
    const percentage =
        student.previous_marks_obtained !== null &&
        student.previous_marks_total
            ? Math.round(
                  (student.previous_marks_obtained /
                      student.previous_marks_total) *
                      100,
              )
            : null;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            {displayName}
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Student record and enrollment.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.students.index')}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-gray-700 hover:bg-surface"
                        >
                            Back to Students
                        </Link>
                        <Link
                            href={route('admin.students.edit', student.id)}
                            className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                        >
                            Edit
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={displayName} />

            <div className="flex h-full min-h-0 flex-col gap-4 lg:flex-row lg:items-start">
                {/* Left column: who this student is */}
                <div className="flex w-full flex-col gap-4 lg:w-1/3">
                    <div className={cardClass}>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 className="font-serif text-2xl font-semibold text-navy">
                                    {displayName}
                                </h3>
                                <p className="mt-1 text-sm text-gray-500">
                                    Roll number {student.roll_number}
                                </p>
                            </div>
                            <span
                                className={
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ' +
                                    (statusStyles[student.status] ??
                                        'bg-gray-100 text-gray-600')
                                }
                            >
                                {statusLabels[student.status] ??
                                    student.status}
                            </span>
                        </div>

                        {!student.is_active && (
                            <p className="mt-4 rounded-md bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">
                                This account is deactivated, so the student
                                cannot sign in. The record is kept.
                            </p>
                        )}

                        {student.created_at && (
                            <p className="mt-4 text-xs text-gray-500">
                                Admitted on {student.created_at}
                            </p>
                        )}
                    </div>

                    <DetailCard title="Batch">
                        <Row label="Cohort" value={student.batch.name} />
                        <Row
                            label="Current Grade"
                            value={
                                student.batch.current_grade !== null
                                    ? `Grade ${student.batch.current_grade}`
                                    : null
                            }
                        />
                    </DetailCard>

                    <DetailCard title="Contact">
                        <Row label="Email" value={student.email} />
                        <Row label="Phone" value={student.phone} />
                        <Row
                            label="Guardian Phone"
                            value={student.guardian_phone}
                        />
                        <Row label="Address" value={student.address} />
                    </DetailCard>

                    <DetailCard title="Personal">
                        <Row
                            label="Father's Name"
                            value={student.father_name}
                        />
                        <Row
                            label="CNIC / B-Form"
                            value={student.cnic_bform}
                        />
                        <Row label="Date of Birth" value={student.date_of_birth} />
                        <Row
                            label="Gender"
                            value={
                                student.gender
                                    ? student.gender.charAt(0).toUpperCase() +
                                      student.gender.slice(1)
                                    : null
                            }
                        />
                    </DetailCard>

                    <DetailCard title="Academic">
                        <Row
                            label="Previous School"
                            value={student.previous_school}
                        />
                        <Row
                            label="Matric Marks"
                            value={
                                student.previous_marks_obtained === null
                                    ? null
                                    : `${student.previous_marks_obtained} / ${student.previous_marks_total ?? '—'}${
                                          percentage !== null
                                              ? ` (${percentage}%)`
                                          : ''
                                      }`
                            }
                        />
                        <Row
                            label="Admission Date"
                            value={student.admission_date}
                        />
                        <Row
                            label="Board Registration"
                            value={student.board_registration_number}
                        />
                    </DetailCard>
                </div>

                {/* Right column: what the student is enrolled in */}
                <div className="flex w-full flex-1 flex-col gap-4">
                    <div className={cardClass}>
                        <h3 className="font-serif text-base font-semibold text-navy">
                            Enrollment
                        </h3>

                        {/*
                            Both cards below are placeholders for sub-stages
                            that have not been built yet. They are shown as
                            empty states rather than hidden, because a student
                            with no enrollment is the normal state right now
                            and an admin needs to know the feature is coming
                            rather than wonder whether the page is broken.
                        */}
                        {enrollments.length === 0 ? (
                            <p className="mt-4 rounded-md bg-surface p-4 text-sm text-gray-600 ring-1 ring-gray-200">
                                Enrollment into classes will be available in a
                                future update.
                            </p>
                        ) : (
                            <ul className="mt-4 space-y-2">
                                {enrollments.map((enrollment) => (
                                    <li
                                        key={enrollment.id}
                                        className="text-sm text-gray-800"
                                    >
                                        {enrollment.class_display_name}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className={cardClass}>
                        <h3 className="font-serif text-base font-semibold text-navy">
                            Attendance
                        </h3>
                        <p className="mt-4 rounded-md bg-surface p-4 text-sm text-gray-600 ring-1 ring-gray-200">
                            Attendance tracking will be available in a future
                            update.
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
