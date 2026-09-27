import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        start_date: '',
        end_date: '',
        is_active: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.academic-sessions.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        Create Academic Session
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Add a new academic year, e.g., 2026-2027.
                    </p>
                </div>
            }
        >
            <Head title="Create Academic Session" />

            <div className="mx-auto max-w-2xl">
                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <div>
                        <InputLabel
                            htmlFor="name"
                            value="Session Name"
                            className="text-gray-700"
                        />
                        <TextInput
                            id="name"
                            type="text"
                            name="name"
                            value={data.name}
                            className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                            placeholder="2026-2027"
                            isFocused={true}
                            onChange={(e) =>
                                setData('name', e.target.value)
                            }
                        />
                        <p className="mt-1 text-xs text-gray-500">
                            Format: YYYY-YYYY (e.g., 2026-2027)
                        </p>
                        <InputError
                            message={errors.name}
                            className="mt-2"
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel
                                htmlFor="start_date"
                                value="Start Date"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="start_date"
                                type="date"
                                name="start_date"
                                value={data.start_date}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                onChange={(e) =>
                                    setData('start_date', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.start_date}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="end_date"
                                value="End Date"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="end_date"
                                type="date"
                                name="end_date"
                                value={data.end_date}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                onChange={(e) =>
                                    setData('end_date', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.end_date}
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
                            <span className="font-medium">
                                Set as active session
                            </span>
                            <span className="mt-0.5 block text-xs text-gray-500">
                                Activating this session will deactivate any
                                other active session.
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <Link
                            href={route('admin.academic-sessions.index')}
                            className="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-navy"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton
                            disabled={processing}
                            className="bg-navy px-6 py-2 uppercase tracking-wider hover:bg-navy-dark focus:bg-navy-dark active:bg-navy-dark"
                        >
                            {processing ? 'Creating...' : 'Create Session'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}