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

interface StudentData {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    batch_id: number;
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
}

interface BatchOption {
    id: number;
    name: string;
    is_active: boolean;
    current_grade: number | null;
}

type EditStudentPageProps = {
    student: StudentData;
    batches: BatchOption[];
};

/**
 * A number comes from the database as an integer and an input holds a
 * string, so every numeric field is stringified on the way in. A null
 * becomes an empty box rather than the text "null".
 */
const asInput = (value: number | string | null): string =>
    value === null || value === undefined ? '' : String(value);

export default function Edit() {
    const { student, batches } =
        usePage<PageProps<EditStudentPageProps>>().props;

    const { data, setData, put, processing, errors } = useForm<StudentFormValues>({
        name: student.name ?? '',
        email: student.email ?? '',
        phone: student.phone ?? '',
        // Always empty: the password is never sent to the browser, and a
        // blank box means "leave the current one alone".
        password: '',
        password_confirmation: '',
        is_active: student.is_active,
        batch_id: String(student.batch_id),
        roll_number: student.roll_number,
        father_name: student.father_name ?? '',
        cnic_bform: student.cnic_bform ?? '',
        date_of_birth: student.date_of_birth ?? '',
        gender: student.gender ?? '',
        guardian_phone: student.guardian_phone ?? '',
        address: student.address ?? '',
        admission_date: student.admission_date ?? '',
        previous_school: student.previous_school ?? '',
        previous_marks_obtained: asInput(student.previous_marks_obtained),
        previous_marks_total: asInput(student.previous_marks_total),
        status: student.status,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.students.update', student.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Edit Student
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Update {student.name ?? 'this student'}
                            &apos;s record.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Edit Student" />

            <div className="mx-auto max-w-3xl">
                <form
                    onSubmit={submit}
                    className="space-y-8 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <AccountFields
                        data={data}
                        errors={errors}
                        setData={setData}
                        isEdit={true}
                    />

                    <ProfileFields
                        data={data}
                        errors={errors}
                        setData={setData}
                        batches={batches}
                        nextRollNumber={null}
                        isEdit={true}
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
                            {processing ? 'Saving...' : 'Save Changes'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
