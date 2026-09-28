import TimetableGrid, {
    TimetablePeriod,
    TimetableSlotView,
} from '@/Components/TimetableGrid';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';

type TeacherTimetablePageProps = {
    periods: TimetablePeriod[];
    days: Record<number, string>;
    slots: TimetableSlotView[];
    teacher: {
        name: string;
    };
    summary: {
        total_periods: number;
        classes_count: number;
    };
    daySummary: Record<number, number>;
};

const cardClass = 'rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200';

const cardLabel =
    'text-xs font-semibold uppercase tracking-wider text-gray-500';

const cardValue = 'mt-1 font-serif text-2xl font-semibold text-navy';

export default function Timetable() {
    const { periods, days, slots, summary, daySummary } =
        usePage<PageProps<TeacherTimetablePageProps>>().props;

    // The busiest day, used for the third summary card. Ties resolve to the
    // earlier day so the card is stable.
    const busiest = Object.entries(daySummary).reduce<
        { day: string; count: number } | null
    >((best, [number, count]) => {
        const value = Number(count);

        if (best === null || value > best.count) {
            return { day: days[Number(number)] ?? `Day ${number}`, count: value };
        }

        return best;
    }, null);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="font-serif text-xl font-semibold text-navy">
                        My Timetable
                    </h2>
                    <p className="mt-1 text-sm text-gray-600">
                        Weekly teaching schedule
                    </p>
                </div>
            }
        >
            <Head title="My Timetable" />

            <div className="flex flex-col gap-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className={cardClass}>
                        <p className={cardLabel}>Total Periods Per Week</p>
                        <p className={cardValue}>
                            {summary.total_periods}
                        </p>
                    </div>
                    <div className={cardClass}>
                        <p className={cardLabel}>Classes</p>
                        <p className={cardValue}>
                            {summary.classes_count}
                        </p>
                    </div>
                    <div className={cardClass}>
                        <p className={cardLabel}>Busiest Day</p>
                        <p className={cardValue}>
                            {busiest?.day ?? '—'}
                        </p>
                        <p className="mt-1 text-xs text-gray-500">
                            {busiest
                                ? `${busiest.count} period${
                                      busiest.count === 1 ? '' : 's'
                                  }`
                                : 'Nothing scheduled'}
                        </p>
                    </div>
                </div>

                <TimetableGrid
                    periods={periods}
                    days={days}
                    slots={slots}
                    mode="teacher"
                />
            </div>
        </AuthenticatedLayout>
    );
}
