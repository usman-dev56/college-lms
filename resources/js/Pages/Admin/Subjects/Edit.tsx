import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface StreamOption {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
}

interface Subject {
    id: number;
    name: string;
    code: string | null;
    grade_level: number;
    stream_id: number | null;
    has_practical: boolean;
    is_active: boolean;
}

type EditSubjectPageProps = {
    subject: Subject;
    streams: StreamOption[];
};

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

export default function Edit() {
    const { subject, streams } =
        usePage<PageProps<EditSubjectPageProps>>().props;

    const { data, setData, put, processing, errors } = useForm({
        name: subject.name,
        code: subject.code ?? '',
        grade_level: subject.grade_level,
        stream_id: (subject.stream_id ?? '') as number | '',
        has_practical: subject.has_practical,
        is_active: subject.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.subjects.update', subject.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Edit Subject
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Update {subject.name} for Grade {subject.grade_level}.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Edit Subject ${subject.name}`} />

            <div className="mx-auto max-w-2xl">
                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <div>
                        <InputLabel
                            htmlFor="name"
                            value="Subject Name"
                            className="text-gray-700"
                        />
                        <TextInput
                            id="name"
                            type="text"
                            name="name"
                            value={data.name}
                            className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                            placeholder="Biology"
                            isFocused={true}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <InputLabel
                                htmlFor="code"
                                value="Code"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="code"
                                type="text"
                                name="code"
                                value={data.code}
                                className="mt-1 block w-full border-gray-300 uppercase focus:border-navy focus:ring-navy"
                                placeholder="BIO"
                                maxLength={20}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                            />
                            <InputError message={errors.code} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="grade_level"
                                value="Grade Level"
                                className="text-gray-700"
                            />
                            <select
                                id="grade_level"
                                name="grade_level"
                                value={data.grade_level}
                                className={selectClass}
                                onChange={(e) =>
                                    setData(
                                        'grade_level',
                                        Number(e.target.value),
                                    )
                                }
                            >
                                <option value={11}>Grade 11</option>
                                <option value={12}>Grade 12</option>
                            </select>
                            <InputError
                                message={errors.grade_level}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="stream_id"
                                value="Stream"
                                className="text-gray-700"
                            />
                            <select
                                id="stream_id"
                                name="stream_id"
                                value={data.stream_id}
                                className={selectClass}
                                onChange={(e) =>
                                    setData(
                                        'stream_id',
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            >
                                <option value="">Compulsory (no stream)</option>
                                {streams.map((stream) => (
                                    <option key={stream.id} value={stream.id}>
                                        {stream.name} ({stream.code})
                                        {stream.is_active ? '' : ' — inactive'}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={errors.stream_id}
                                className="mt-2"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-3 rounded-md bg-surface p-4 ring-1 ring-gray-200">
                        <input
                            id="has_practical"
                            type="checkbox"
                            checked={data.has_practical}
                            onChange={(e) =>
                                setData('has_practical', e.target.checked)
                            }
                            className="h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy"
                        />
                        <label
                            htmlFor="has_practical"
                            className="text-sm text-gray-700"
                        >
                            <span className="font-medium">Has practical</span>
                            <span className="mt-0.5 block text-xs text-gray-500">
                                Science subjects with a lab component, e.g.,
                                Physics, Chemistry, Biology, Computer Science.
                            </span>
                        </label>
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
                                Inactive subjects stay on record but are hidden
                                from future enrolment choices.
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <Link
                            href={route('admin.subjects.index')}
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
