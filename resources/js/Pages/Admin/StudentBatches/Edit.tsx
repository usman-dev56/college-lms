import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface StudentBatch {
    id: number;
    name: string;
    start_grade: number;
    expected_graduation_year: number;
    is_active: boolean;
    notes: string | null;
}

type EditStudentBatchPageProps = {
    batch: StudentBatch;
};

const selectClass =
    'block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

// The college is intermediate, so a cohort can start at grade 9 and still
// graduate at 12.
const startGrades = [9, 10, 11, 12];

export default function Edit() {
    const { batch } = usePage<PageProps<EditStudentBatchPageProps>>().props;

    const { data, setData, put, processing, errors } = useForm({
        name: batch.name,
        start_grade: batch.start_grade,
        expected_graduation_year: String(batch.expected_graduation_year),
        is_active: batch.is_active,
        // A null note would render as the string "null" in a textarea, so it
        // is normalised to an empty value the form can bind to.
        notes: batch.notes ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.student-batches.update', batch.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Edit Student Batch
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Update the details of batch {batch.name}.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Edit Batch ${batch.name}`} />

            <div className="mx-auto max-w-2xl">
                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <div>
                        <InputLabel
                            htmlFor="name"
                            value="Batch Name"
                            className="text-gray-700"
                        />
                        <TextInput
                            id="name"
                            type="text"
                            name="name"
                            value={data.name}
                            className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                            placeholder="2026-2028"
                            maxLength={30}
                            isFocused={true}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <p className="mt-1 text-xs text-gray-500">
                            Format: YYYY-YYYY — the admission year and the
                            expected graduation year, e.g., 2026-2028.
                        </p>
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel
                                htmlFor="start_grade"
                                value="Start Grade"
                                className="text-gray-700"
                            />
                            <select
                                id="start_grade"
                                name="start_grade"
                                value={data.start_grade}
                                onChange={(e) =>
                                    setData(
                                        'start_grade',
                                        Number(e.target.value),
                                    )
                                }
                                className={`mt-1 ${selectClass}`}
                            >
                                {startGrades.map((grade) => (
                                    <option key={grade} value={grade}>
                                        Grade {grade}
                                    </option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-gray-500">
                                The grade level this cohort starts in.
                            </p>
                            <InputError
                                message={errors.start_grade}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="expected_graduation_year"
                                value="Expected Graduation Year"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="expected_graduation_year"
                                type="number"
                                name="expected_graduation_year"
                                value={data.expected_graduation_year}
                                min={2020}
                                max={2100}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                placeholder="e.g., 2028"
                                onChange={(e) =>
                                    setData(
                                        'expected_graduation_year',
                                        e.target.value,
                                    )
                                }
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                e.g., 2028
                            </p>
                            <InputError
                                message={errors.expected_graduation_year}
                                className="mt-2"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-3 rounded-md bg-surface p-4 ring-1 ring-gray-200">
                        <input
                            id="is_active"
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) =>
                                setData('is_active', e.target.checked)
                            }
                            className="h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy"
                        />
                        <label
                            htmlFor="is_active"
                            className="text-sm text-gray-700"
                        >
                            <span className="font-medium">Active</span>
                            <span className="mt-0.5 block text-xs text-gray-500">
                                Inactive batches stay on record but are hidden
                                from the choices offered when enrolling a
                                student.
                            </span>
                        </label>
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="notes"
                            value="Notes"
                            className="text-gray-700"
                        />
                        <textarea
                            id="notes"
                            name="notes"
                            rows={3}
                            value={data.notes}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy"
                            placeholder="e.g. Morning shift only"
                            maxLength={255}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        <p className="mt-1 text-xs text-gray-500">
                            Optional. Maximum 255 characters.
                        </p>
                        <InputError message={errors.notes} className="mt-2" />
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <Link
                            href={route('admin.student-batches.index')}
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