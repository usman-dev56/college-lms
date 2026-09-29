import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface EnrolledStudent {
    enrollment_id: number;
    student_profile_id: number;
    roll_number: string | null;
    name: string | null;
    email: string | null;
    phone: string | null;
    gender: string | null;
    batch_name: string | null;
    status: string;
}

interface UnassignedStudent {
    id: number;
    roll_number: string | null;
    name: string | null;
    email: string | null;
    batch_name: string | null;
}

interface ClassInfo {
    id: number;
    display_name: string;
    grade_level: number;
    stream_name: string | null;
    section: string | null;
    room: string | null;
    session_name: string | null;
}

type ClassRosterPageProps = {
    class: ClassInfo;
    students: EnrolledStudent[];
    unassignedStudents: UnassignedStudent[];
    flash?: {
        success?: string;
        error?: string;
    };
};

const thClass =
    'px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600';

const tdClass = 'whitespace-nowrap px-6 py-4 text-sm text-gray-700';

export default function ClassRoster() {
    const { class: classInfo, students, unassignedStudents, flash } =
        usePage<PageProps<ClassRosterPageProps>>().props;

    const [showModal, setShowModal] = useState(false);
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const [errors, setErrors] = useState<{ student_profile_ids?: string }>({});
    const [processing, setProcessing] = useState(false);

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (term === '') {
            return unassignedStudents;
        }

        return unassignedStudents.filter(
            (student) =>
                (student.name ?? '').toLowerCase().includes(term) ||
                (student.roll_number ?? '').toLowerCase().includes(term),
        );
    }, [unassignedStudents, search]);

    const toggle = (id: number) => {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    const toggleAllVisible = () => {
        const visibleIds = filtered.map((student) => student.id);
        const allSelected = visibleIds.every((id) => selected.includes(id));

        setSelected((current) =>
            allSelected
                ? current.filter((id) => !visibleIds.includes(id))
                : [...new Set([...current, ...visibleIds])],
        );
    };

    const closeModal = () => {
        setShowModal(false);
        setSearch('');
        setSelected([]);
        setErrors({});
    };

    const submit = () => {
        if (selected.length === 0) {
            return;
        }

        setProcessing(true);

        router.post(
            route('admin.classes.enrollments.store', classInfo.id),
            { class_id: classInfo.id, student_profile_ids: selected },
            {
                preserveScroll: true,
                onError: (response) => {
                    // A validation failure is shown in place rather than
                    // thrown away with the modal, so the admin can see what
                    // was wrong and correct it without starting over.
                    setErrors(
                        (response.errors ?? {}) as {
                            student_profile_ids?: string;
                        },
                    );
                    setProcessing(false);
                },
                onSuccess: closeModal,
            },
        );
    };

    const unenroll = (student: EnrolledStudent) => {
        if (
            !window.confirm(
                `Remove ${student.name ?? 'this student'} from the class? ` +
                    'They stay on the roll and can be added to another class.',
            )
        ) {
            return;
        }

        router.delete(
            route('admin.enrollments.destroy', student.enrollment_id),
            { preserveScroll: true },
        );
    };

    const allVisibleSelected =
        filtered.length > 0 && filtered.every((s) => selected.includes(s.id));

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Class Roster — {classInfo.display_name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            {students.length} student(s) enrolled
                            {classInfo.session_name
                                ? ` · ${classInfo.session_name}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.classes.index')}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-gray-700 hover:bg-surface"
                        >
                            Back to Classes
                        </Link>
                        <button
                            type="button"
                            onClick={() => setShowModal(true)}
                            className="rounded-md bg-navy px-4 py-2 text-sm font-semibold uppercase tracking-wider text-white hover:bg-navy-dark"
                        >
                            Enroll Students
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={`Roster — ${classInfo.display_name}`} />

            {flash?.success && (
                <div className="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">
                    {flash.error}
                </div>
            )}

            <div className="flex h-full min-h-0 flex-col gap-4">
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    {students.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 p-12 text-center">
                            <p className="text-sm font-medium text-gray-700">
                                No students enrolled in this class yet.
                            </p>
                            <button
                                type="button"
                                onClick={() => setShowModal(true)}
                                className="text-sm font-semibold text-navy hover:text-gold"
                            >
                                Enroll Students
                            </button>
                        </div>
                    ) : (
                        <div className="min-h-0 flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className={thClass}>Roll #</th>
                                        <th className={thClass}>Name</th>
                                        <th className={thClass}>Email</th>
                                        <th className={thClass}>Phone</th>
                                        <th className={thClass}>Batch</th>
                                        <th className={thClass}>Status</th>
                                        <th
                                            className={`${thClass} text-right`}
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {students.map((student) => (
                                        <tr
                                            key={student.enrollment_id}
                                            className="hover:bg-surface"
                                        >
                                            <td
                                                className={`${tdClass} font-semibold text-navy`}
                                            >
                                                {student.roll_number ?? '—'}
                                            </td>
                                            <td
                                                className={`${tdClass} font-medium text-gray-900`}
                                            >
                                                {student.name ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {student.email ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {student.phone ?? '—'}
                                            </td>
                                            <td className={tdClass}>
                                                {student.batch_name ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                <span className="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                                    Active
                                                </span>
                                            </td>
                                            <td
                                                className={`${tdClass} text-right`}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        unenroll(student)
                                                    }
                                                    className="font-medium text-red-600 hover:text-red-800"
                                                >
                                                    Unenroll
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            <Modal show={showModal} onClose={closeModal} maxWidth="2xl">
                <div className="p-6">
                    <h3 className="font-serif text-lg font-semibold text-navy">
                        Enroll Students
                    </h3>
                    <p className="mt-1 text-sm text-gray-600">
                        Students not yet in a class for this session.
                    </p>

                    <div className="mt-4">
                        <label
                            htmlFor="roster-search"
                            className="block text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            Search
                        </label>
                        <input
                            id="roster-search"
                            type="search"
                            value={search}
                            placeholder="Name or roll number"
                            className="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy"
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <div className="mt-4 max-h-72 overflow-auto rounded-md ring-1 ring-gray-200">
                        {filtered.length === 0 ? (
                            <p className="p-6 text-center text-sm text-gray-500">
                                {unassignedStudents.length === 0
                                    ? 'Every active student is already in a class this session.'
                                    : 'No students match the search.'}
                            </p>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="sticky top-0 z-10 bg-surface">
                                    <tr>
                                        <th className="w-10 px-4 py-2">
                                            <input
                                                type="checkbox"
                                                checked={allVisibleSelected}
                                                onChange={toggleAllVisible}
                                                aria-label="Select all visible"
                                                className="h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy"
                                            />
                                        </th>
                                        <th className={thClass}>Roll #</th>
                                        <th className={thClass}>Name</th>
                                        <th className={thClass}>Batch</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {filtered.map((student) => (
                                        <tr key={student.id}>
                                            <td className="px-4 py-2">
                                                <input
                                                    type="checkbox"
                                                    checked={selected.includes(
                                                        student.id,
                                                    )}
                                                    onChange={() =>
                                                        toggle(student.id)
                                                    }
                                                    aria-label={`Select ${student.name ?? 'student'}`}
                                                    className="h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy"
                                                />
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-2 text-sm text-gray-700">
                                                {student.roll_number ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-2 text-sm font-medium text-gray-900">
                                                {student.name ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-2 text-sm text-gray-700">
                                                {student.batch_name ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    <InputError
                        message={errors.student_profile_ids}
                        className="mt-2"
                    />

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={closeModal}
                            className="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-navy"
                        >
                            Cancel
                        </button>
                        <PrimaryButton
                            type="button"
                            disabled={selected.length === 0 || processing}
                            onClick={submit}
                            className="bg-navy px-6 py-2 uppercase tracking-wider hover:bg-navy-dark focus:bg-navy-dark active:bg-navy-dark"
                        >
                            {processing
                                ? 'Enrolling...'
                                : `Enroll Selected (${selected.length})`}
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
