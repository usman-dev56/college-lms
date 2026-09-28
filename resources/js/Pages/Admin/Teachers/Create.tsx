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
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        is_active: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.teachers.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            New Teacher
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Add a member of the teaching staff.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="New Teacher" />

            <div className="mx-auto max-w-2xl">
                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <InputLabel
                                htmlFor="name"
                                value="Full Name"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="name"
                                type="text"
                                name="name"
                                value={data.name}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                placeholder="Ahmed Khan"
                                maxLength={100}
                                autoFocus
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.name}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="email"
                                value="Email"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                placeholder="ahmed.khan@college.test"
                                maxLength={150}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.email}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="phone"
                                value="Phone"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="phone"
                                type="text"
                                name="phone"
                                value={data.phone}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                placeholder="0300-1234567"
                                maxLength={20}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Optional. Must be unique.
                            </p>
                            <InputError
                                message={errors.phone}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="password"
                                value="Password"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                placeholder="At least 8 characters"
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Minimum 8 characters.
                            </p>
                            <InputError
                                message={errors.password}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="password_confirmation"
                                value="Confirm Password"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={errors.password_confirmation}
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
                                Inactive teachers stay on record but cannot
                                be assigned to new classes.
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <Link
                            href={route('admin.teachers.index')}
                            className="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-navy"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton
                            disabled={processing}
                            className="bg-navy px-6 py-2 uppercase tracking-wider hover:bg-navy-dark focus:bg-navy-dark active:bg-navy-dark"
                        >
                            {processing ? 'Saving...' : 'Create Teacher'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
