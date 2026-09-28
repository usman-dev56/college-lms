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
};

export default function Timetable() {
    const { periods, days, slots, noClass } =
        usePage<PageProps<StudentTimetablePageProps>>().props;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        My Timetable
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Your weekly class schedule
                    </p>
                </div>
            }
        >
            <Head title="My Timetable" />

            <div className="flex flex-col gap-4">
                {noClass ? (
                    <div className="rounded-lg bg-white p-6 text-center shadow-sm ring-1 ring-gray-200">
                        <p className="text-sm text-gray-600">
                            You are not yet enrolled in a class. Please
                            contact the college administration.
                        </p>
                    </div>
                ) : (
                    <TimetableGrid
                        periods={periods}
                        days={days}
                        slots={slots}
                        mode="student"
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
