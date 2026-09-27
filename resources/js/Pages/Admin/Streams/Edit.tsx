import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Stream {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
}

type StreamEditPageProps = {
    stream: Stream;
};

export default function Edit() {
    const { stream } = usePage<PageProps<StreamEditPageProps>>().props;

    const { data, setData, put, processing, errors } = useForm({
        name: stream.name,
        code: stream.code,
        description: stream.description ?? '',
        is_active: stream.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.streams.update', stream.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Edit Stream
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Update details for stream {stream.name}.
                    </p>
                </div>
            }
        >
            <Head title={`Edit Stream ${stream.name}`} />

            <div className="mx-auto max-w-2xl">
                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <div>
                        <InputLabel
                            htmlFor="name"
                            value="Stream Name"
                            className="text-gray-700"
                        />
                        <TextInput
                            id="name"
                            type="text"
                            name="name"
                            value={data.name}
                            className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                            placeholder="Pre-Medical"
                            isFocused={true}
                            onChange={(e) =>
                                setData('name', e.target.value)
                            }
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

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
                            placeholder="PM"
                            maxLength={10}
                            onChange={(e) =>
                                setData('code', e.target.value)
                            }
                        />
                        <p className="mt-1 text-xs text-gray-500">
                            Short uppercase code, e.g., PM, PE, ICS. Codes are
                            always saved in uppercase.
                        </p>
                        <InputError message={errors.code} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="description"
                            value="Description"
                            className="text-gray-700"
                        />
                        <textarea
                            id="description"
                            name="description"
                            rows={3}
                            value={data.description}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy"
                            placeholder="FSc Pre-Medical — Biology, Physics, Chemistry"
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                        <p className="mt-1 text-xs text-gray-500">
                            Optional. Maximum 255 characters.
                        </p>
                        <InputError
                            message={errors.description}
                            className="mt-2"
                        />
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
                                Inactive streams stay on record but are hidden
                                from future student enrolment choices.
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <Link
                            href={route('admin.streams.index')}
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
