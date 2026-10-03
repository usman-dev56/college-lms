import { Link } from '@inertiajs/react';

export type ReportKey = 'daily' | 'range' | 'subject' | 'monthly' | 'trend';

/**
 * The five attendance reports, in the order an office works through them:
 * today, then a range, then by subject, then the month's summary, then the
 * trend.
 *
 * Shared by all five pages rather than defined in each, because a tab bar that
 * is copied five times drifts the moment a sixth report is added - and it
 * would drift invisibly, because a missing tab still renders.
 */
const TABS: { key: ReportKey; label: string; routeName: string }[] = [
    { key: 'daily', label: 'Daily', routeName: 'admin.reports.attendance.daily' },
    { key: 'range', label: 'Range', routeName: 'admin.reports.attendance.range' },
    {
        key: 'subject',
        label: 'Subject',
        routeName: 'admin.reports.attendance.subject',
    },
    {
        key: 'monthly',
        label: 'Monthly',
        routeName: 'admin.reports.attendance.monthly',
    },
    { key: 'trend', label: 'Trend', routeName: 'admin.reports.attendance.trend' },
];

export default function ReportsTabs({ active }: { active: ReportKey }) {
    return (
        <div className="shrink-0 flex items-center gap-1 border-b border-gray-200">
            {TABS.map((tab) => (
                <Link
                    key={tab.key}
                    href={route(tab.routeName)}
                    aria-current={tab.key === active ? 'page' : undefined}
                    className={
                        'px-4 py-2 text-sm font-medium transition ' +
                        (tab.key === active
                            ? 'border-b-2 border-navy text-navy'
                            : 'border-b-2 border-transparent text-gray-600 hover:text-navy')
                    }
                >
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}