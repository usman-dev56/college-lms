import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { ReactNode } from 'react';

/**
 * The two forms a student is entered on - create and edit - share every
 * field. They are written out once here rather than duplicated, because the
 * two pages drift apart the moment they are copied: a field added to one and
 * forgotten on the other is a field the admin cannot fill in.
 *
 * The components are dumb on purpose - no useForm, no state of their own. The
 * owning page passes values and a setter, so this file stays a description
 * of the form's shape and nothing more.
 */

/**
 * The value of every field, as strings.
 *
 * Everything is a string because that is what an input element holds and
 * what Inertia sends. The numbers are cast on the way to the server; the
 * checkbox is the one exception, since React needs a real boolean to render
 * it.
 */
export interface StudentFormValues {
    // Account
    name: string;
    email: string;
    phone: string;
    password: string;
    password_confirmation: string;
    is_active: boolean;

    // Batch and roll number
    batch_id: string;
    roll_number: string;

    // Student details
    father_name: string;
    cnic_bform: string;
    date_of_birth: string;
    gender: string;
    guardian_phone: string;
    address: string;
    admission_date: string;
    previous_school: string;
    previous_marks_obtained: string;
    previous_marks_total: string;
    status: string;
}

/** The validation errors, keyed by field name. */
export type StudentFormErrors = Record<string, string | undefined>;

/** Sets one field of the form. */
export type SetField = <K extends keyof StudentFormValues>(
    field: K,
    value: StudentFormValues[K],
) => void;

const inputClass =
    'mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy';

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

/** The gender choices, shared by both forms. */
export const GENDER_OPTIONS = [
    { value: '', label: '—' },
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'other', label: 'Other' },
];

/** The status choices, in the order a student passes through them. */
export const STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'graduated', label: 'Graduated' },
    { value: 'withdrawn', label: 'Withdrawn' },
    { value: 'suspended', label: 'Suspended' },
];

/** One labelled field in the form grid. */
function Field({
    id,
    label,
    error,
    className = '',
    children,
}: {
    id: string;
    label: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={className}>
            <InputLabel
                htmlFor={id}
                value={label}
                className="text-gray-700"
            />
            {children}
            <InputError message={error} className="mt-2" />
        </div>
    );
}

/**
 * A section of the form.
 *
 * The form covers two different records - an account and a profile - so the
 * split is labelled rather than left to the field order to imply.
 */
function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4">
            <div className="border-b border-gray-100 pb-3">
                <h3 className="font-serif text-lg font-semibold text-navy">
                    {title}
                </h3>
                <p className="mt-1 text-sm text-gray-600">{description}</p>
            </div>
            {children}
        </section>
    );
}

export function AccountFields({
    data,
    errors,
    setData,
    isEdit,
}: {
    data: StudentFormValues;
    errors: StudentFormErrors;
    setData: SetField;
    /** On edit the password is optional and the boxes start empty. */
    isEdit: boolean;
}) {
    return (
        <Section
            title="Login & Account"
            description="How the student signs in to the college portal."
        >
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <Field
                    id="name"
                    label="Full Name"
                    error={errors.name}
                    className="sm:col-span-2"
                >
                    <TextInput
                        id="name"
                        type="text"
                        name="name"
                        value={data.name}
                        className={inputClass}
                        placeholder="Ahmed Khan"
                        maxLength={100}
                        autoFocus
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </Field>

                <Field id="email" label="Email" error={errors.email}>
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className={inputClass}
                        placeholder="ahmed.khan@college.test"
                        maxLength={150}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </Field>

                <Field id="phone" label="Phone" error={errors.phone}>
                    <TextInput
                        id="phone"
                        type="text"
                        name="phone"
                        value={data.phone}
                        className={inputClass}
                        placeholder="0300-1234567"
                        maxLength={20}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        The student&apos;s own number, if they have one.
                    </p>
                </Field>

                <Field
                    id="password"
                    label="Password"
                    error={errors.password}
                >
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className={inputClass}
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        {isEdit
                            ? 'Leave blank to keep the current password.'
                            : 'Minimum 8 characters.'}
                    </p>
                </Field>

                <Field
                    id="password_confirmation"
                    label="Confirm Password"
                    error={errors.password_confirmation}
                >
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className={inputClass}
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                </Field>
            </div>

            <div className="flex items-center gap-3 rounded-md bg-surface p-4 ring-1 ring-gray-200">
                <input
                    id="is_active"
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                    className="h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy"
                />
                <label
                    htmlFor="is_active"
                    className="text-sm text-gray-700"
                >
                    <span className="font-medium">Active</span>
                    <span className="mt-0.5 block text-xs text-gray-500">
                        Inactive students keep their record but cannot sign in.
                    </span>
                </label>
            </div>
        </Section>
    );
}



export function ProfileFields({
    data,
    errors,
    setData,
    batches,
    nextRollNumber,
    isEdit,
}: {
    data: StudentFormValues;
    errors: StudentFormErrors;
    setData: SetField;
    batches: { id: number; name: string }[];
    /** The number the server would hand out for the default batch. */
    nextRollNumber: string | null;
    isEdit: boolean;
}) {
    return (
        <Section
            title="Student Details"
            description="The record the college office keeps for this student."
        >
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <Field id="batch_id" label="Batch" error={errors.batch_id}>
                    <select
                        id="batch_id"
                        name="batch_id"
                        value={data.batch_id}
                        className={selectClass}
                        onChange={(e) => setData('batch_id', e.target.value)}
                    >
                        <option value="">Select a batch</option>
                        {batches.map((batch) => (
                            <option key={batch.id} value={batch.id}>
                                {batch.name}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-gray-500">
                        The cohort the student was admitted with. Roll numbers
                        are counted within a batch.
                    </p>
                </Field>

                <Field
                    id="roll_number"
                    label="Roll Number"
                    error={errors.roll_number}
                >
                    <TextInput
                        id="roll_number"
                        type="text"
                        name="roll_number"
                        value={data.roll_number}
                        className={inputClass}
                        placeholder="Auto-generated if left blank"
                        maxLength={20}
                        onChange={(e) =>
                            setData('roll_number', e.target.value)
                        }
                    />
                    {/*
                        The preview is deliberately static. It is computed for
                        one batch when the page loads, so a hint that
                        "updated when the batch changed" would be wrong the
                        moment a different batch was picked - it would still
                        show the first batch's number. The real number is
                        chosen on save, inside the transaction.
                    */}
                    <p className="mt-1 text-xs text-gray-500">
                        {isEdit
                            ? "Leave blank to keep the student's current roll number."
                            : nextRollNumber
                              ? `Next available in the default batch: ${nextRollNumber}. Auto-assigned on save.`
                              : 'Auto-assigned on save.'}
                    </p>
                </Field>

                <Field
                    id="father_name"
                    label="Father's Name"
                    error={errors.father_name}
                >
                    <TextInput
                        id="father_name"
                        type="text"
                        name="father_name"
                        value={data.father_name}
                        className={inputClass}
                        maxLength={100}
                        onChange={(e) =>
                            setData('father_name', e.target.value)
                        }
                    />
                </Field>

                <Field
                    id="cnic_bform"
                    label="CNIC / B-Form"
                    error={errors.cnic_bform}
                >
                    <TextInput
                        id="cnic_bform"
                        type="text"
                        name="cnic_bform"
                        value={data.cnic_bform}
                        className={inputClass}
                        placeholder="35202-1234567-1"
                        maxLength={20}
                        onChange={(e) =>
                            setData('cnic_bform', e.target.value)
                        }
                    />
                </Field>

                <Field
                    id="date_of_birth"
                    label="Date of Birth"
                    error={errors.date_of_birth}
                >
                    <TextInput
                        id="date_of_birth"
                        type="date"
                        name="date_of_birth"
                        value={data.date_of_birth}
                        className={inputClass}
                        onChange={(e) =>
                            setData('date_of_birth', e.target.value)
                        }
                    />
                </Field>

                <Field id="gender" label="Gender" error={errors.gender}>
                    <select
                        id="gender"
                        name="gender"
                        value={data.gender}
                        className={selectClass}
                        onChange={(e) => setData('gender', e.target.value)}
                    >
                        {GENDER_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    id="guardian_phone"
                    label="Guardian Phone"
                    error={errors.guardian_phone}
                >
                    <TextInput
                        id="guardian_phone"
                        type="text"
                        name="guardian_phone"
                        value={data.guardian_phone}
                        className={inputClass}
                        placeholder="0301-7654321"
                        maxLength={20}
                        onChange={(e) =>
                            setData('guardian_phone', e.target.value)
                        }
                    />
                </Field>

                <Field
                    id="admission_date"
                    label="Admission Date"
                    error={errors.admission_date}
                >
                    <TextInput
                        id="admission_date"
                        type="date"
                        name="admission_date"
                        value={data.admission_date}
                        className={inputClass}
                        onChange={(e) =>
                            setData('admission_date', e.target.value)
                        }
                    />
                </Field>

                <Field
                    id="address"
                    label="Address"
                    error={errors.address}
                    className="sm:col-span-2"
                >
                    <textarea
                        id="address"
                        name="address"
                        rows={2}
                        value={data.address}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy"
                        placeholder="House 12, Mohalla Islampura, Chiniot"
                        maxLength={255}
                        onChange={(e) => setData('address', e.target.value)}
                    />
                </Field>

                <Field
                    id="previous_school"
                    label="Previous School"
                    error={errors.previous_school}
                    className="sm:col-span-2"
                >
                    <TextInput
                        id="previous_school"
                        type="text"
                        name="previous_school"
                        value={data.previous_school}
                        className={inputClass}
                        placeholder="Government High School Chiniot"
                        maxLength={150}
                        onChange={(e) =>
                            setData('previous_school', e.target.value)
                        }
                    />
                </Field>

                <Field
                    id="previous_marks_obtained"
                    label="Previous Marks Obtained"
                    error={errors.previous_marks_obtained}
                >
                    <TextInput
                        id="previous_marks_obtained"
                        type="number"
                        name="previous_marks_obtained"
                        value={data.previous_marks_obtained}
                        className={inputClass}
                        placeholder="850"
                        min={0}
                        onChange={(e) =>
                            setData('previous_marks_obtained', e.target.value)
                        }
                    />
                </Field>

                <Field
                    id="previous_marks_total"
                    label="Previous Marks Total"
                    error={errors.previous_marks_total}
                >
                    <TextInput
                        id="previous_marks_total"
                        type="number"
                        name="previous_marks_total"
                        value={data.previous_marks_total}
                        className={inputClass}
                        placeholder="1100"
                        min={1}
                        onChange={(e) =>
                            setData('previous_marks_total', e.target.value)
                        }
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        The Matric total in Punjab is 1100.
                    </p>
                </Field>

                <Field id="status" label="Status" error={errors.status}>
                    <select
                        id="status"
                        name="status"
                        value={data.status}
                        className={selectClass}
                        onChange={(e) => setData('status', e.target.value)}
                    >
                        {STATUS_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-gray-500">
                        Graduated and withdrawn students stay on the roll.
                    </p>
                </Field>
            </div>
        </Section>
    );
}
