import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, ReactNode } from 'react';

interface BatchOption {
    id: number;
    name: string;
    start_grade: number;
}

interface StreamOption {
    id: number;
    name: string;
    code: string | null;
}

type CreateAdmissionPageProps = {
    batches: BatchOption[];
    streams: StreamOption[];
    /** True when no batch is accepting applications. */
    admissionsClosed: boolean;
};

const inputClass =
    'mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy';

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

/** One labelled field, with its validation error underneath. */
function Field({
    id,
    label,
    required = false,
    error,
    children,
}: {
    id: string;
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <InputLabel
                htmlFor={id}
                value={label + (required ? ' *' : '')}
                className="text-gray-700"
            />
            {children}
            <InputError message={error} className="mt-1.5" />
        </div>
    );
}

export default function Create() {
    const { batches, streams, admissionsClosed } =
        usePage<PageProps<CreateAdmissionPageProps>>().props;

    const { data, setData, post, processing, errors } = useForm({
        applicant_name: '',
        father_name: '',
        cnic_bform: '',
        date_of_birth: '',
        phone: '',
        guardian_phone: '',
        address: '',
        previous_school: '',
        previous_marks_obtained: '',
        previous_marks_total: '',
        stream_applied_id: '',
        batch_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admissions.store'));
    };

    return (
        <GuestLayout title="Admission Application">
            <div>
                <h1 className="font-serif text-2xl font-bold text-navy">
                    Admission Application
                </h1>
                <div className="mt-2 h-1 w-16 bg-gold" />
                <p className="mt-3 text-sm text-gray-600">
                    Government College Chiniot &mdash; Intermediate (Grade 11)
                </p>

                {/*
                    The closed state is a real answer rather than an error:
                    the college simply is not taking applications right now.
                    Rendering the form anyway would mean a visitor fills in
                    twelve fields only to be told at the end that it cannot be
                    sent, so the notice replaces the form entirely.
                */}
                {admissionsClosed ? (
                    <div className="mt-8 rounded-md bg-amber-50 p-6 text-center ring-1 ring-amber-200">
                        <h2 className="font-serif text-lg font-semibold text-amber-900">
                            Admissions are currently closed
                        </h2>
                        <p className="mt-2 text-sm text-amber-800">
                            Please check back later.
                        </p>
                    </div>
                ) : (
                    <form onSubmit={submit} className="mt-6 space-y-4">

                        <Field
                            id="applicant_name"
                            label="Full Name"
                            required
                            error={errors.applicant_name}
                        >
                            <TextInput
                                id="applicant_name"
                                type="text"
                                name="applicant_name"
                                value={data.applicant_name}
                                className={inputClass}
                                placeholder="Ahmed Khan"
                                maxLength={100}
                                autoFocus
                                required
                                onChange={(e) =>
                                    setData('applicant_name', e.target.value)
                                }
                            />
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
                            required
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
                                required
                                onChange={(e) =>
                                    setData('cnic_bform', e.target.value)
                                }
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                B-Form number if the applicant is a minor. One
                                application per CNIC is accepted.
                            </p>
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

                        <Field
                            id="phone"
                            label="Phone"
                            error={errors.phone}
                        >
                            <TextInput
                                id="phone"
                                type="text"
                                name="phone"
                                value={data.phone}
                                className={inputClass}
                                placeholder="0300-1234567"
                                maxLength={20}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />
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
                            id="address"
                            label="Address"
                            error={errors.address}
                        >
                            <textarea
                                id="address"
                                name="address"
                                rows={2}
                                value={data.address}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy"
                                placeholder="House 12, Mohalla Islampura, Chiniot"
                                maxLength={255}
                                onChange={(e) =>
                                    setData('address', e.target.value)
                                }
                            />
                        </Field>

                        <Field
                            id="previous_school"
                            label="Previous School"
                            error={errors.previous_school}
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

                        <div className="grid grid-cols-2 gap-4">
                            <Field
                                id="previous_marks_obtained"
                                label="Marks Obtained"
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
                                        setData(
                                            'previous_marks_obtained',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                id="previous_marks_total"
                                label="Marks Total"
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
                                        setData(
                                            'previous_marks_total',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>

                        <Field
                            id="stream_applied_id"
                            label="Stream Applied For"
                            required
                            error={errors.stream_applied_id}
                        >
                            <select
                                id="stream_applied_id"
                                name="stream_applied_id"
                                value={data.stream_applied_id}
                                className={selectClass}
                                required
                                onChange={(e) =>
                                    setData('stream_applied_id', e.target.value)
                                }
                            >
                                <option value="">Select a stream</option>
                                {streams.map((stream) => (
                                    <option key={stream.id} value={stream.id}>
                                        {stream.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            id="batch_id"
                            label="Batch"
                            required
                            error={errors.batch_id}
                        >
                            <select
                                id="batch_id"
                                name="batch_id"
                                value={data.batch_id}
                                className={selectClass}
                                required
                                onChange={(e) =>
                                    setData('batch_id', e.target.value)
                                }
                            >
                                <option value="">Select a batch</option>
                                {batches.map((batch) => (
                                    <option key={batch.id} value={batch.id}>
                                        {batch.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <div className="pt-2">
                            <PrimaryButton
                                type="submit"
                                disabled={processing}
                                className="w-full bg-navy px-6 py-3 uppercase tracking-wider hover:bg-navy-dark focus:bg-navy-dark active:bg-navy-dark"
                            >
                                {processing
                                    ? 'Submitting...'
                                    : 'Submit Application'}
                            </PrimaryButton>
                        </div>

                        <p className="text-xs text-gray-500">
                            You will be given an application number to keep.
                            Results are announced by the college office.
                        </p>
                    </form>
                )}
            </div>
        </GuestLayout>
    );
}
