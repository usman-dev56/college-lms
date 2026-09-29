import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

interface StudentProfileData {
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    roll_number: string;
    batch_name: string | null;
    cnic_bform: string | null;
    father_name: string | null;
    date_of_birth: string | null;
    gender: string | null;
    guardian_phone: string | null;
    address: string | null;
    admission_date: string | null;
    previous_school: string | null;
    previous_marks_obtained: number | null;
    previous_marks_total: number | null;
    status: string;
}

type StudentProfilePageProps = {
    student: StudentProfileData | null;
    message: string | null;
};

const cardClass = 'rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200';

const statusStyles: Record<string, string> = {
    active: 'bg-green-100 text-green-800',
    graduated: 'bg-blue-100 text-blue-800',
    withdrawn: 'bg-gray-100 text-gray-600',
    suspended: 'bg-amber-100 text-amber-800',
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    graduated: 'Graduated',
    withdrawn: 'Withdrawn',
    suspended: 'Suspended',
};

/** A group of related fields under one heading. */
function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div>
            <h3 className="font-serif text-base font-semibold text-navy">
                {title}
            </h3>
            <dl className="mt-3 space-y-3">{children}</dl>
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
                {empty ? <span className="text-gray-400">—</span> : value}
            </dd>
        </div>
    );
}

export default function Profile() {
    const { student, message } =
        usePage<PageProps<StudentProfilePageProps>>().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            My Profile
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            {student
                                ? `Roll number ${student.roll_number}`
                                : 'Student record'}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="My Profile" />

            {message || student === null ? (
                <div className="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-gray-200">
                    <p className="text-sm text-gray-700">
                        {message ?? 'Your student profile has not been created yet.'}
                    </p>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className={cardClass}>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 className="font-serif text-2xl font-semibold text-navy">
                                    {student.name}
                                </h3>
                                <p className="mt-1 text-sm text-gray-500">
                                    Roll number {student.roll_number}
                                </p>
                            </div>
                            <div className="flex flex-col items-end gap-2">
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
                                {!student.is_active && (
                                    <span className="text-xs text-amber-700">
                                        Account deactivated
                                    </span>
                                )}
                            </div>
                        </div>

                        <div className="mt-6 space-y-6 border-t border-gray-100 pt-6">
                            <Section title="Identity">
                                <Row label="Name" value={student.name} />
                                <Row
                                    label="Roll Number"
                                    value={student.roll_number}
                                />
                                <Row
                                    label="Batch"
                                    value={student.batch_name}
                                />
                            </Section>

                            <Section title="Contact">
                                <Row label="Email" value={student.email} />
                                <Row label="Phone" value={student.phone} />
                                <Row
                                    label="Guardian Phone"
                                    value={student.guardian_phone}
                                />
                                <Row
                                    label="Address"
                                    value={student.address}
                                />
                            </Section>

                            <Section title="Personal">
                                <Row
                                    label="Father's Name"
                                    value={student.father_name}
                                />
                                <Row
                                    label="CNIC / B-Form"
                                    value={student.cnic_bform}
                                />
                                <Row
                                    label="Date of Birth"
                                    value={student.date_of_birth}
                                />
                                <Row
                                    label="Gender"
                                    value={
                                        student.gender
                                            ? student.gender
                                                  .charAt(0)
                                                  .toUpperCase() +
                                              student.gender.slice(1)
                                            : null
                                    }
                                />
                            </Section>

                            <Section title="Academic">
                                <Row
                                    label="Admission Date"
                                    value={student.admission_date}
                                />
                                <Row
                                    label="Previous School"
                                    value={student.previous_school}
                                />
                                <Row
                                    label="Previous Marks"
                                    value={
                                        student.previous_marks_obtained ===
                                        null
                                            ? null
                                            : `${student.previous_marks_obtained} / ${student.previous_marks_total ?? '-'}`
                                    }
                                />
                            </Section>
                        </div>
                    </div>

                    {/*
                        Read-only on purpose, and the page says so. Letting a
                        student edit their own CNIC or matric marks would
                        let them disqualify themselves and create duplicates
                        at the same time, so changes go through the office.
                    */}
                    <p className="text-center text-sm text-gray-500">
                        To update your information, please contact the
                        administration.
                    </p>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
