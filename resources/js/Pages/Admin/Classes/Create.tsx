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

interface StreamOption {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
}

type CreateClassPageProps = {
    sessions: SessionOption[];
    streams: StreamOption[];
};

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

export default function Create() {
    const { sessions, streams } =
        usePage<PageProps<CreateClassPageProps>>().props;

    const activeSession = sessions.find((session) => session.is_active);

    const { data, setData, post, processing, errors } = useForm({
        academic_session_id: (activeSession?.id ?? '') as number | '',
        grade_level: 11,
        stream_id: '' as number | '',
        section: '',
        capacity: '' as number | '',
        room: '',
        is_active: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.classes.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Create Class
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Add a section of students for one session, grade
                            level and stream.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Create Class" />

            <div className="mx-auto max-w-2xl">
                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200"
                >
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
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
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            >
                                <option value="">Select a session</option>
                                {sessions.map((session) => (
                                    <option key={session.id} value={session.id}>
                                        {session.name}
                                        {session.is_active ? ' (active)' : ''}
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
                                <option value="">Select a stream</option>
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

                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <InputLabel
                                htmlFor="section"
                                value="Section"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="section"
                                type="text"
                                name="section"
                                value={data.section}
                                className="mt-1 block w-full border-gray-300 uppercase focus:border-navy focus:ring-navy"
                                placeholder="A"
                                maxLength={10}
                                isFocused={true}
                                onChange={(e) =>
                                    setData('section', e.target.value)
                                }
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Examples: A, B, C
                            </p>
                            <InputError message={errors.section} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="capacity"
                                value="Capacity"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="capacity"
                                type="number"
                                name="capacity"
                                value={data.capacity}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                placeholder="50"
                                min={1}
                                max={500}
                                onChange={(e) =>
                                    setData(
                                        'capacity',
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Optional. Maximum number of students.
                            </p>
                            <InputError
                                message={errors.capacity}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="room"
                                value="Room"
                                className="text-gray-700"
                            />
                            <TextInput
                                id="room"
                                type="text"
                                name="room"
                                value={data.room}
                                className="mt-1 block w-full border-gray-300 focus:border-navy focus:ring-navy"
                                placeholder="R-101"
                                maxLength={50}
                                onChange={(e) => setData('room', e.target.value)}
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Optional. Room or location.
                            </p>
                            <InputError message={errors.room} className="mt-2" />
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
                                Inactive classes stay on record but are hidden
                                from future enrolment choices.
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <Link
                            href={route('admin.classes.index')}
                            className="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-navy"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton
                            disabled={processing}
                            className="bg-navy px-6 py-2 uppercase tracking-wider hover:bg-navy-dark focus:bg-navy-dark active:bg-navy-dark"
                        >
                            {processing ? 'Creating...' : 'Create Class'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
