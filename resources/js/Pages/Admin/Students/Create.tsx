import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import {
    AccountFields,
    ProfileFields,
    StudentFormValues,
} from './StudentFormFields';

interface BatchOption {
    id: number;
    name: string;
    is_active: boolean;
    current_grade: number | null;
}

type CreateStudentPageProps = {
    batches: BatchOption[];
    /** The batch the form starts on: the newest active cohort. */
    defaultBatchId: number | null;
    /** A preview of the roll number, for the default batch only. */
    nextRollNumber: string | null;
};

export default function Create() {
    const { batches, defaultBatchId, nextRollNumber } =
        usePage<PageProps<CreateStudentPageProps>>().props;

    const { data, setData, post, processing, errors } = useForm<StudentFormValues>({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        is_active: true,
        batch_id: defaultBatchId === null ? '' : String(defaultBatchId),
        roll_number: '',
        father_name: '',
        cnic_bform: '',
        date_of_birth: '',
        gender: '',
        guardian_phone: '',
        address: '',
        admission_date: '',
        previous_school: '',
        previous_marks_obtained: '',
        previous_marks_total: '',
        status: 'active',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.students.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            New Student
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Admit a student and set up their portal login.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="New Student" />

            <div className="mx-auto max-w-3xl">
                <form
                    onSubmit={submit}
                    className="space-y-8 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <AccountFields
                        data={data}
                        errors={errors}
                        setData={setData}
                        isEdit={false}
                    />

                    <ProfileFields
                        data={data}
                        errors={errors}
                        setData={setData}
                        batches={batches}
                        nextRollNumber={nextRollNumber}
                        isEdit={false}
                    />

                    <div className="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <Link
                            href={route('admin.students.index')}
                            className="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-navy"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton
                            disabled={processing}
                            className="bg-navy px-6 py-2 uppercase tracking-wider hover:bg-navy-dark focus:bg-navy-dark active:bg-navy-dark"
                        >
                            {processing ? 'Saving...' : 'Create Student'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
