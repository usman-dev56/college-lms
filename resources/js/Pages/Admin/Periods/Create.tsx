import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface SessionOption {
    id: number;
    name: string;
    is_active: boolean;
}

type CreatePeriodPageProps = {
    sessions: SessionOption[];
    selectedSessionId: number;
    suggestedNumber: number;
};

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

export default function Create() {
    const { sessions, selectedSessionId, suggestedNumber } =
        usePage<PageProps<CreatePeriodPageProps>>().props;

    const { data, setData, post, processing, errors } = useForm({
        academic_session_id: selectedSessionId,
        number: suggestedNumber,
        label: '',
        // 45-minute teaching blocks, matching the seeded grid.
        start_time: '08:00',
        end_time: '08:45',
        is_break: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.periods.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        New Period
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Add a row to the daily timetable grid.
                    </p>
                </div>
            }
        >
            <Head title="New Period" />

            <div className="mx-auto max-w-2xl">
                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <InputLabel
                                htmlFor="academic_session_id"
                                value="Academic Session"
                                className="text-gray-700"
                            />
                            <select
                                id="academic_session_id"
                                name="academic_session_id"
                                value={data.academic_session_id}
                                className={selectClass}
                                onChange={(e) =>
                                    setData(
                                        'academic_session_id',
                                        Number(e.target.value),
                                    )
                                }
                            >
                                {sessions.map((session) => (
                                    <option
                                        key={session.id}
                                        value={session.id}
                                    >
                                        {session.name}
                                        {session.is_active
                                            ? ' (active)'
                                            : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={errors.academic_session_id}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="number"
                                value="Period Number"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="number"
                                type="number"
                                name="number"
                                min={1}
                                max={20}
                                value={data.number}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                onChange={(e) =>
                                    setData('number', Number(e.target.value))
                                }
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Position in the grid, 1 to 20.
                            </p>
                            <InputError
                                message={errors.number}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="label"
                                value="Label"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="label"
                                type="text"
                                name="label"
                                value={data.label}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                placeholder="Period 1"
                                maxLength={30}
                                onChange={(e) =>
                                    setData('label', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.label}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="start_time"
                                value="Start Time"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="start_time"
                                type="time"
                                name="start_time"
                                value={data.start_time}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                onChange={(e) =>
                                    setData('start_time', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.start_time}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="end_time"
                                value="End Time"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="end_time"
                                type="time"
                                name="end_time"
                                value={data.end_time}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                onChange={(e) =>
                                    setData('end_time', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.end_time}
                                className="mt-2"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-3 rounded-md bg-surface p-4 ring-1 ring-gray-200">
                        <input
                            id="is_break"
                            type="checkbox"
                            checked={data.is_break}
                            onChange={(e) =>
                                setData('is_break', e.target.checked)
                            }
                            className="h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy"
                        />
                        <label
                            htmlFor="is_break"
                            className="text-sm text-gray-700"
                        >
                            <span className="font-medium">Break</span>
                            <span className="mt-0.5 block text-xs text-gray-500">
                                Mark as break to exclude this row from
                                teaching slot selection in the timetable.
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <Link
                            href={route('admin.periods.index', {
                                session_id: data.academic_session_id,
                            })}
                            className="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-navy"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton
                            disabled={processing}
                            className="bg-navy px-6 py-2 uppercase tracking-wider hover:bg-navy-dark focus:bg-navy-dark active:bg-navy-dark"
                        >
                            {processing ? 'Saving...' : 'Create Period'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}