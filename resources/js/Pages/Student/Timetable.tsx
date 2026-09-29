import TimetableGrid, {
    TimetablePeriod,
    TimetableSlotView,
} from '@/Components/TimetableGrid';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';

type StudentTimetablePageProps = {
    periods: TimetablePeriod[];
    days: Record<number, string>;
    slots: TimetableSlotView[];
    student: {
        name: string;
    };
    noClass: boolean;

    /**
     * Why there is nothing to show, when there is nothing to show. Sent
     * rather than written into the page so the controller decides the
     * wording - "no profile" and "no class" are different problems and
     * should not read the same to a student.
     */
    message: string | null;
    classInfo: {
        display_name: string;
        grade_level: number;
        section: string | null;
        stream_name: string | null;
    } | null;
};

export default function Timetable() {
    const { periods, days, slots, noClass, message, classInfo } =
        usePage<PageProps<StudentTimetablePageProps>>().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex w-full items-center justify-between gap-4">
                    <div>
                        <h2 className="font-serif text-xl font-semibold text-navy">
                            My Timetable
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">
                            {classInfo
                                ? `Class: ${classInfo.display_name}`
                                : 'Your weekly class schedule'}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="My Timetable" />

            <div className="flex flex-col gap-4">
                {noClass ? (
                    <div className="rounded-lg bg-white p-6 text-center shadow-sm ring-1 ring-gray-200">
                        <p className="text-sm text-gray-600">
                            {message ??
                                'You are not yet enrolled in a class. Please contact the college administration.'}
                        </p>
                    </div>
                ) : (
                    <>
                        {/*
                            The class is named above the grid as well as in
                            the page header: the header scrolls out of reach,
                            and a student reading a six-column grid needs to
                            know whose week they are looking at.
                        */}
                        {classInfo && (
                            <div className="rounded-lg bg-white px-6 py-4 shadow-sm ring-1 ring-gray-200">
                                <p className="text-sm text-gray-700">
                                    <span className="font-semibold text-navy">
                                        Class:{' '}
                                    </span>
                                    {classInfo.display_name}
                                    {classInfo.stream_name && (
                                        <span className="text-gray-500">
                                            {' '}
                                            · {classInfo.stream_name}
                                        </span>
                                    )}
                                </p>
                            </div>
                        )}

                        <TimetableGrid
                            periods={periods}
                            days={days}
                            slots={slots}
                            mode="student"
                        />
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
