<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Period;
use App\Models\StudentBatch;
use App\Models\StudentProfile;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The five attendance reports and their CSV exports.
 *
 * The report pages are the office's evidence, so each is checked for the
 * figures it claims to show rather than only for a 200. The exports matter
 * equally: a CSV that has drifted from the table beside it is worse than no
 * export, because it gets filed.
 */
class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $today;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->today = Carbon::parse('2026-09-21')->startOfDay();
        Carbon::setTestNow($this->today);
        $this->admin = $this->createAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * One class with one enrolled student and a handful of marks.
     *
     * The smallest shape a report can be meaningful on: a class, a subject, a
     * period, a student and some attendance.
     *
     * @return array{session: AcademicSession, class: ClassModel, period: Period, classSubject: ClassSubject, student: StudentProfile}
     */
    private function seededRegister(): array
    {
        $session = $this->createActiveSession();
        $stream = $this->createStream();
        $class = $this->createClass($session, $stream);
        $period = $this->createPeriod($session);
        $teacher = $this->createTeacher();

        $classSubject = ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $this->createSubject()->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 3,
        ]);

        $batch = StudentBatch::firstOrCreate(
            ['name' => '2026-2028'],
            [
                'start_grade' => 11,
                'expected_graduation_year' => 2028,
                'is_active' => true,
            ],
        );

        $student = StudentProfile::create([
            'user_id' => $this->createStudent()->id,
            'batch_id' => $batch->id,
            'roll_number' => StudentProfile::nextRollNumber($batch->id),
            'status' => 'active',
        ]);

        Enrollment::create([
            'student_profile_id' => $student->id,
            'class_id' => $class->id,
            'academic_session_id' => $session->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        // A timetable slot on the Monday the two marks fall on. The daily
        // report is shaped around the timetable rather than around the marks,
        // so without a slot the day has no periods at all.
        TimetableSlot::create([
            'class_id' => $class->id,
            'period_id' => $period->id,
            'day_of_week' => Carbon::parse('2026-09-14')->dayOfWeekIso,
            'class_subject_id' => $classSubject->id,
            'room' => null,
        ]);

        // Two marks on distinct days, so a range report has more than one day
        // to fold together.
        foreach ([
            ['2026-09-14', Attendance::STATUS_PRESENT],
            ['2026-09-15', Attendance::STATUS_ABSENT],
        ] as [$date, $status]) {
            Attendance::create([
                'student_profile_id' => $student->id,
                'class_subject_id' => $classSubject->id,
                'period_id' => $period->id,
                'attendance_date' => $date,
                'status' => $status,
                'marked_by' => $teacher->id,
                'marked_at' => now(),
                'notes' => null,
            ]);
        }

        return compact('session', 'class', 'period', 'classSubject', 'student');
    }

    /**
     * Read a streamed CSV into its rows.
     *
     * @return array<int, array<int, string>>
     */
    private function csvRows(TestResponse $response): array
    {
        $content = $response->streamedContent();

        $this->assertStringContainsString(
            "\xEF\xBB\xBF",
            $content,
            'The export must start with a UTF-8 BOM so Excel opens it correctly.',
        );

        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        $rows = array_map(
            fn (string $line): array => str_getcsv(ltrim($line, "\xEF\xBB\xBF")),
            array_values(array_filter($lines, fn (string $l): bool => trim($l) !== '')),
        );

        return $rows;
    }

    public function test_daily_report_renders_for_a_class_and_date(): void
    {
        $s = $this->seededRegister();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.daily', [
                'class_id' => $s['class']->id,
                'date' => '2026-09-14',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Attendance/Daily')
                ->has('report')
                ->where('report.class.id', $s['class']->id)
                ->where('report.date', '2026-09-14')
                // One period of one student, from the mark seeded on that day.
                ->has('report.periods', 1)
                ->has('report.students', 1)
                ->where('report.students.0.roll_number', $s['student']->roll_number)
                ->where('report.students.0.periods.0.status', Attendance::STATUS_PRESENT)
                ->where('report.overall.total', 1)
                ->where('report.overall.percentage', fn (float $v): bool => abs($v - 100.0) < 0.01)
            );
    }

    public function test_daily_report_returns_null_report_when_no_class_chosen(): void
    {
        $this->seededRegister();

        // An empty state rather than a redirect: the office stays on the
        // report page with its filter visible instead of being bounced to the
        // index.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.daily'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Attendance/Daily')
                ->where('report', null)
                ->where('filters.class_id', null)
                ->has('classes')
            );
    }

    public function test_range_report_renders_for_a_class_and_date_range(): void
    {
        $s = $this->seededRegister();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.range', [
                'class_id' => $s['class']->id,
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Attendance/Range')
                ->has('report')
                ->where('report.from', '2026-09-01')
                ->where('report.to', '2026-09-30')
                ->where('report.class.id', $s['class']->id)
                ->has('report.students', 1)
                // Two marks across two days, one present and one absent.
                ->where('report.students.0.total', 2)
                ->where('report.students.0.present', 1)
                ->where('report.students.0.absent', 1)
                ->where('report.students.0.percentage', fn (float $v): bool => abs($v - 50.0) < 0.01)
                // School days rather than calendar days, so a month-end range
                // cannot be misread as thin data.
                ->where('report.days_count', 26)
                ->has('report.by_subject', 1)
            );
    }

    public function test_range_report_returns_null_report_when_no_class_chosen(): void
    {
        $this->seededRegister();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.range'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Attendance/Range')
                ->where('report', null)
                ->where('filters.class_id', null)
            );
    }

    public function test_subject_report_renders_with_default_filters(): void
    {
        $s = $this->seededRegister();

        // No filters at all: the report falls back to the last thirty days and
        // the whole college, rather than refusing to render.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.subject'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Attendance/Subject')
                ->has('report')
                ->where('report.from', now()->subDays(30)->toDateString())
                ->where('report.to', $this->today->toDateString())
                // The seeded September marks fall inside the default window, so the
                // report picks them up with no filters supplied at all.
                ->has('report.subjects', 1)
                ->where('report.subjects.0.subject_name', 'English')
                ->has('report.by_grade')
                ->has('classes')
                ->has('streams')
                ->has('grades')
            );

        // Narrowed to the seeded month, the subject appears with its figures.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.subject', [
                'class_id' => $s['class']->id,
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('report.subjects', 1)
                ->where('report.subjects.0.subject_name', 'English')
                ->where('report.subjects.0.class_id', $s['class']->id)
                ->where('report.subjects.0.total', 2)
                ->where('report.subjects.0.present', 1)
                ->where('report.subjects.0.absent', 1)
                ->where('report.subjects.0.percentage', fn (float $v): bool => abs($v - 50.0) < 0.01)
                ->has('report.by_grade', 1)
                ->where('report.overall.total', 2)
            );
    }

    public function test_monthly_report_renders_for_a_month(): void
    {
        $s = $this->seededRegister();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.monthly', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Attendance/Monthly')
                ->where('report.month', '2026-09')
                ->where('report.month_label', 'September 2026')
                // September 2026 has 30 days, of which four are Sundays.
                ->where('report.days_in_range', 26)
                ->has('report.students', 1)
                ->where('report.students.0.roll_number', $s['student']->roll_number)
                ->where('report.students.0.total', 2)
                ->where('report.students.0.percentage', fn (float $v): bool => abs($v - 50.0) < 0.01)
                ->where('report.overall.total', 2)
                ->has('classes')
                ->has('batches')
            );
    }

    public function test_trend_report_returns_days_array(): void
    {
        $this->seededRegister();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.trend', ['days' => 30]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Attendance/Trend')
                ->where('report.days', 30)
                ->where('report.from', $this->today->copy()->subDays(29)->toDateString())
                ->where('report.to', $this->today->toDateString())
                // One point per day of the window, so the chart's x-axis is a
                // continuous calendar.
                ->has('report.data', 30)
                ->where('report.data.0.percentage', null)
                ->where('report.class_id', null)
                ->where('report.class', null)
                ->has('classes')
            );
    }

    public function test_trend_report_respects_class_filter(): void
    {
        $s = $this->seededRegister();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.trend', [
                'days' => 30,
                'class_id' => $s['class']->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.class_id', $s['class']->id)
                ->where('report.class.display_name', $s['class']->displayName())
                ->has('report.data', 30)
                // The window covers the seeded days, so at least one point
                // carries a real percentage.
                ->etc()
            );

        // A narrower window changes the number of points, which is what makes
        // the chart narrow with it.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.trend', [
                'days' => 7,
                'class_id' => $s['class']->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.days', 7)
                ->has('report.data', 7)
            );

        // A point with records carries the real figures; one without stays null,
        // which is unknown rather than zero.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.trend', [
                'days' => 30,
                'class_id' => $s['class']->id,
            ]))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $data = $page->toArray()['props']['report']['data'];

                $withRecords = array_values(array_filter(
                    $data,
                    fn (array $d): bool => $d['total'] > 0,
                ));

                $page
                    ->has('report.data', 30)
                    ->where('report.data.0.percentage', null);

                $this->assertNotEmpty(
                    $withRecords,
                    'The seeded days must appear in the window.',
                );

                foreach ($withRecords as $point) {
                    $this->assertNotNull($point['percentage']);
                    $this->assertSame(1, $point['total']);
                    $this->assertSame(
                        $point['total'] - $point['absent'],
                        $point['present'],
                    );
                }
            });
    }

    public function test_daily_report_export_returns_a_csv(): void
    {
        $s = $this->seededRegister();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.daily.export', [
                'class_id' => $s['class']->id,
                'date' => '2026-09-14',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
        // The class id and the date both appear, so two exports of the same
        // day for different classes do not collide on disk.
        $this->assertStringContainsString(
            'daily-attendance-'.$s['class']->id.'-2026-09-14.csv',
            (string) $response->headers->get('content-disposition'),
        );

        $rows = $this->csvRows($response);

        // One row per period of the day, not per student: the daily export is the
        // register sheet for a class-period.
        $this->assertSame(
            [
                'Period',
                'Subject',
                'Teacher',
                'Present',
                'Absent',
                'Late',
                'Leave',
                'Total',
                'Percentage',
            ],
            $rows[0],
        );
        $this->assertCount(2, $rows, 'A header row plus the seeded period.');
    }

    public function test_range_report_export_returns_a_csv(): void
    {
        $s = $this->seededRegister();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.range.export', [
                'class_id' => $s['class']->id,
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $rows = $this->csvRows($response);

        $this->assertContains('Present', $rows[0]);
        $this->assertContains('Percentage', $rows[0]);
    }

    public function test_subject_report_export_returns_a_csv(): void
    {
        $s = $this->seededRegister();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.subject.export', [
                'class_id' => $s['class']->id,
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $rows = $this->csvRows($response);

        $this->assertContains('Subject', $rows[0]);
        $this->assertContains('Teacher', $rows[0]);

        // The seeded subject is in the file, with the figures the page shows.
        $this->assertCount(2, $rows, 'A header row plus one subject.');
        $this->assertContains('English', $rows[1]);
        $this->assertContains('50.00', $rows[1]);
    }

    public function test_monthly_report_export_returns_a_csv(): void
    {
        $s = $this->seededRegister();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.monthly.export', [
                'month' => '2026-09',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString(
            'monthly-attendance-2026-09.csv',
            (string) $response->headers->get('content-disposition'),
        );

        $rows = $this->csvRows($response);

        $this->assertContains('Roll Number', $rows[0]);
        $this->assertContains($s['student']->roll_number, $rows[1]);
    }

    public function test_trend_report_export_returns_a_csv(): void
    {
        $s = $this->seededRegister();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.attendance.trend.export', [
                'days' => 30,
                'class_id' => $s['class']->id,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $rows = $this->csvRows($response);

        $this->assertSame(['Date', 'Total', 'Present', 'Absent', 'Percentage'], $rows[0]);

        // One row per day of the window, ascending by date, so the file and
        // the chart describe the same series.
        $this->assertCount(31, $rows);
        $this->assertSame(
            $this->today->copy()->subDays(29)->toDateString(),
            $rows[1][0],
        );
        $this->assertSame($this->today->toDateString(), $rows[30][0]);
    }

    public function test_non_admin_cannot_access_reports(): void
    {
        $s = $this->seededRegister();

        $pages = [
            'admin.reports.attendance.daily',
            'admin.reports.attendance.range',
            'admin.reports.attendance.subject',
            'admin.reports.attendance.monthly',
            'admin.reports.attendance.trend',
            'admin.attendance.defaulters',
        ];

        $exports = [
            'admin.reports.attendance.daily.export',
            'admin.reports.attendance.range.export',
            'admin.reports.attendance.subject.export',
            'admin.reports.attendance.monthly.export',
            'admin.reports.attendance.trend.export',
            'admin.attendance.defaulters.export',
        ];

        foreach ([$this->createTeacher(), $this->createStudent()] as $user) {
            foreach (array_merge($pages, $exports) as $route) {
                $this->actingAs($user)
                    ->get(route($route, [
                        'class_id' => $s['class']->id,
                        'date' => '2026-09-14',
                    ]))
                    ->assertForbidden();
            }
        }
    }
}
