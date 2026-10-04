import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { BRANDING } from '@/branding';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout title="Log in">
            <div className="mb-6">
                <h2 className="font-serif text-2xl font-bold text-navy">
                    Sign In
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    Enter your credentials to access the LMS.
                </p>
            </div>

            {status && (
                <div className="mb-4 rounded-md bg-green-50 p-3 text-sm font-medium text-green-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
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
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="you@college.test"
                    />

                    <InputError message={errors.email} className="mt-2" />
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
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="••••••••"
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center justify-between">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                            className="border-gray-300 text-navy focus:ring-navy"
                        />
                        <span className="ms-2 text-sm text-gray-600">
                            Remember me
                        </span>
                    </label>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-sm font-medium text-navy underline-offset-2 hover:text-gold hover:underline focus:outline-none"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>

                <PrimaryButton
                    className="w-full justify-center bg-navy py-3 text-sm font-semibold uppercase tracking-wider hover:bg-navy-dark focus:bg-navy-dark active:bg-navy-dark"
                    disabled={processing}
                >
                    {processing ? 'Signing in...' : 'Sign In'}
                </PrimaryButton>
            </form>

            <div className="mt-6 border-t border-gray-200 pt-6 text-center text-xs text-gray-500">
                Access is restricted to registered students, teachers, and
                administrators of {BRANDING.name}.
            </div>
        </GuestLayout>
    );
}