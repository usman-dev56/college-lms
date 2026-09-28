import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';

interface SubjectOption {
    id: number;
    name: string;
    code: string;
    stream_id: number | null;
    stream_name: string | null;
    has_practical: boolean;
}

interface TeacherOption {
    id: number;
    name: string;
    email: string;
}

interface ExistingAssignment {
    subject_id: number;
    teacher_id: number;
    periods_per_week: number;
}

interface ClassInfo {
    id: number;
    display_name: string;
    grade_level: number;
    stream: {
        id: number;
        name: string;
        code: string;
    };
}

type AssignmentsPageProps = {
    class: ClassInfo;
    subjects: SubjectOption[];
    teachers: TeacherOption[];
    existingAssignments: ExistingAssignment[];
    flash?: {
        success?: string;
        error?: string;
    };
};

/**
 * One row of the form. `enabled` is a UI-only flag: a row is submitted only
 * when it is ticked and a teacher is chosen, so unticking a subject is how
 * the admin removes it from the class.
 */
interface AssignmentRow {
    subject_id: number;
    teacher_id: string;
    periods_per_week: number;
    enabled: boolean;
}

const selectClass =
    'mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

const inputClass =
    'mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-navy focus:ring-navy';

const errorInputClass =
    'mt-1 block w-full h-10 rounded-md border-red-400 shadow-sm focus:border-red-500 focus:ring-red-500';

export default function Assignments() {
    // "class" is a reserved word in JavaScript/TypeScript, so the prop is
    // destructured into classData.
    const {
        class: classData,
        subjects,
        teachers,
        existingAssignments,
        flash,
    } = usePage<PageProps<AssignmentsPageProps>>().props;

    const { data, setData, put, processing, errors, transform } =
        useForm<{
            assignments: AssignmentRow[];
        }>({
            assignments: subjects.map((subject) => {
                const existing = existingAssignments.find(
                    (assignment) => assignment.subject_id === subject.id,
                );

                return {
                    subject_id: subject.id,
                    teacher_id: existing ? String(existing.teacher_id) : '',
                    periods_per_week: existing?.periods_per_week ?? 5,
                    enabled: Boolean(existing),
                };
            }),
        });

    // Compulsory subjects first, then the stream's electives - the same
    // grouping the controller uses when it orders the subjects.
    const groups = useMemo(() => {
        const compulsory = subjects.filter(
            (subject) => subject.stream_id === null,
        );
        const streamSubjects = subjects.filter(
            (subject) => subject.stream_id === classData.stream.id,
        );

        return [
            { heading: 'Compulsory Subjects', subjects: compulsory },
            {
                heading: `${classData.stream.name} Electives`,
                subjects: streamSubjects,
            },
        ].filter((group) => group.subjects.length > 0);
    }, [subjects, classData.stream]);

    const updateRow = (
        index: number,
        changes: Partial<AssignmentRow>,
    ) => {
        setData(
            'assignments',
            data.assignments.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...changes } : row,
            ),
        );
    };

    // Inertia sends the entire form state, so the payload is trimmed to the
    // rows that are both ticked and have a teacher before it leaves the page.
    // Everything else is a subject the admin is removing from this class.
    transform((formData) => ({
        assignments: formData.assignments
            .filter((row) => row.enabled && row.teacher_id !== '')
            .map((row) => ({
                subject_id: Number(row.subject_id),
                teacher_id: Number(row.teacher_id),
                periods_per_week: Number(row.periods_per_week),
            })),
    }));

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        put(route('admin.classes.assignments.update', classData.id));
    };

    // The form row belonging to a subject, if it is on this page's form.
    const rowFor = (subjectId: number): AssignmentRow | undefined =>
        data.assignments.find((row) => row.subject_id === subjectId);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            Assign Subjects &amp; Teachers
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            {classData.display_name} — Grade{' '}
                            {classData.grade_level} · {classData.stream.name}{' '}
                            ({classData.stream.code})
                        </p>
                    </div>
                    <Link
                        href={route('admin.classes.index')}
                        className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-gray-700 hover:bg-surface"
                    >
                        Back to Classes
                    </Link>
                </div>
            }
        >
            <Head title="Assign Subjects" />

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
            {errors.assignments && (
                <div className="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">
                    {errors.assignments}
                </div>
            )}

            <form
                onSubmit={submit}
                className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200"
            >
                <div className="hidden border-b border-gray-200 bg-surface px-6 py-3 sm:grid sm:grid-cols-12 sm:gap-4">
                    <p className="col-span-5 text-xs font-semibold uppercase tracking-wider text-gray-600">
                        Subject
                    </p>
                    <p className="col-span-4 text-xs font-semibold uppercase tracking-wider text-gray-600">
                        Teacher
                    </p>
                    <p className="col-span-2 text-xs font-semibold uppercase tracking-wider text-gray-600">
                        Periods / Week
                    </p>
                    <p className="col-span-1 text-xs font-semibold uppercase tracking-wider text-gray-600">
                        Teach
                    </p>
                </div>

                {groups.map((group) => (
                    <div key={group.heading}>
                        <h3 className="border-b border-gray-100 bg-gray-50 px-6 py-2 text-xs font-semibold uppercase tracking-wider text-navy">
                            {group.heading}
                        </h3>

                        {group.subjects.map((subject) => {
                            const index = data.assignments.findIndex(
                                (row) => row.subject_id === subject.id,
                            );

                            return (
                                <SubjectRow
                                    key={subject.id}
                                    subject={subject}
                                    row={rowFor(subject.id)}
                                    teachers={teachers}
                                    error={
                                        errors[
                                            `assignments.${index}.periods_per_week`
                                        ]
                                    }
                                    onChange={(changes) =>
                                        updateRow(index, changes)
                                    }
                                />
                            );
                        })}
                    </div>
                ))}

                <div className="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
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
                        {processing ? 'Saving...' : 'Save Assignments'}
                    </PrimaryButton>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

interface SubjectRowProps {
    subject: SubjectOption;
    row: AssignmentRow | undefined;
    teachers: TeacherOption[];
    error?: string;
    onChange: (changes: Partial<AssignmentRow>) => void;
}

/**
 * One subject on the form: who teaches it, how many periods a week, and
 * whether it is taught in this class at all.
 */
function SubjectRow({
    subject,
    row,
    teachers,
    error,
    onChange,
}: SubjectRowProps) {
    if (!row) {
        return null;
    }

    return (
        <div className="border-b border-gray-100 px-6 py-4 last:border-b-0 sm:grid sm:grid-cols-12 sm:items-end sm:gap-4">
            <div className="col-span-5">
                <label
                    htmlFor={`teacher-${subject.id}`}
                    className="block text-sm font-medium text-navy"
                >
                    {subject.name}{' '}
                    <span className="text-xs font-normal text-gray-500">
                        ({subject.code})
                    </span>
                </label>
                {subject.has_practical && (
                    <p className="mt-0.5 text-xs text-gray-500">
                        Practical included
                    </p>
                )}
            </div>

            <div className="col-span-4 mt-4 sm:mt-0">
                <select
                    id={`teacher-${subject.id}`}
                    value={row.teacher_id}
                    className={selectClass}
                    onChange={(event) =>
                        onChange({ teacher_id: event.target.value })
                    }
                >
                    <option value="">Select teacher</option>
                    {teachers.map((teacher) => (
                        <option key={teacher.id} value={teacher.id}>
                            {teacher.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="col-span-2 mt-4 sm:mt-0">
                <input
                    id={`periods-${subject.id}`}
                    type="number"
                    min={1}
                    max={20}
                    aria-label={`Periods per week for ${subject.name}`}
                    aria-invalid={error ? true : undefined}
                    value={row.periods_per_week}
                    className={error ? errorInputClass : inputClass}
                    onChange={(event) =>
                        onChange({
                            periods_per_week: Number(event.target.value),
                        })
                    }
                />
                {error && (
                    <p className="mt-1 text-xs text-red-600">{error}</p>
                )}
            </div>

            <div className="col-span-1 mt-4 flex items-center sm:mt-0 sm:justify-center">
                <input
                    id={`enabled-${subject.id}`}
                    type="checkbox"
                    aria-label={`Teach ${subject.name} in this class`}
                    checked={row.enabled}
                    className="h-4 w-4 rounded border-gray-300 text-navy focus:ring-navy"
                    onChange={(event) =>
                        onChange({ enabled: event.target.checked })
                    }
                />
            </div>
        </div>
    );
}

