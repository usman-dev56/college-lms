<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUpdateAttendanceRequest;
use App\Http\Requests\Admin\UpdateAttendanceRequest;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\AttendanceAudit;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The office's copy of the register.
 *
 * This is the only place a mark can be changed after a teacher has submitted
 * it, and nothing here happens without writing to the audit trail: an
 * attendance record that decides board eligibility is only defensible if
 * every correction to it leaves a trace naming who made it and why.
 */
class AttendanceController extends Controller
{
    /**
     * How many records one page of the register holds.
     */
    private const PER_PAGE = 30;

    /**
     * The new_status written to the audit trail when a record is removed.
     *
     * Not one of the four live marks, and deliberately so: 'deleted' is the
     * terminal state of the record rather than a mark on it.
     */
    private const STATUS_DELETED = 'deleted';

    /**
     * Browse the register.
     *
     * Every filter is optional and the date defaults to today, because the
     * common case is an office checking what was marked this morning rather
     * than reading a term of history.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Admin/Attendance/Index', [
            'records' => $this->filtered($filters)
                ->paginate(self::PER_PAGE)
                ->through(fn (Attendance $attendance): array => $this->summarise($attendance)),

            // The counts come from the same filter without the pagination, so
            // the badge total is the size of the result rather than the size of
            // the page - otherwise "14 present" would contradict "30 rows".
            'counts' => $this->counts($filters),

            'classes' => ClassModel::query()
                ->orderBy('grade_level')
                ->orderBy('section')
                ->get()
                ->map(fn (ClassModel $class): array => [
                    'id' => $class->id,
                    'display_name' => $class->displayName(),
                ])
                ->all(),

            /*
                Only class-subjects that actually have records, newest first,
                and capped: this is a filter for finding the register of a
                period somebody remembers, not a catalogue of every assignment
                the college has ever run.
            */
            'subjects' => ClassSubject::query()
                ->whereIn(
                    'id',
                    Attendance::query()->select('class_subject_id')->distinct(),
                )
                ->with(['subject', 'classModel.stream'])
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (ClassSubject $classSubject): array => [
                    'id' => $classSubject->id,
                    'label' => $classSubject->classModel?->displayName()
                        .' — '
                        .$classSubject->subject?->name,
                ])
                ->all(),

            'periods' => $this->periodOptions(),

            'teachers' => User::query()
                ->where('role', 'teacher')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),

            'filters' => $filters,
        ]);
    }

    /**
     * One record in full, with its whole audit trail.
     */
    public function show(Attendance $attendance): Response
    {
        $attendance->load([
            'studentProfile.user',
            'studentProfile.batch',
            'classSubject.subject',
            'classSubject.classModel.stream',
            'period',
            'markedBy',
            'audits.editedBy',
        ]);

        return Inertia::render('Admin/Attendance/Show', [
            'record' => [
                'id' => $attendance->id,
                'attendance_date' => $attendance->attendance_date->toDateString(),
                'student' => [
                    'id' => $attendance->studentProfile?->id,
                    'name' => $attendance->studentProfile?->user?->name,
                    'roll_number' => $attendance->studentProfile?->roll_number,
                    'batch_name' => $attendance->studentProfile?->batch?->name,
                ],
                'class' => $attendance->classSubject?->classModel?->displayName(),
                'subject' => [
                    'name' => $attendance->classSubject?->subject?->name,
                    'code' => $attendance->classSubject?->subject?->code,
                ],
                'period' => [
                    'number' => $attendance->period?->number,
                    'label' => $attendance->period?->label,
                    'start_time' => $attendance->period?->start_time?->format('H:i'),
                    'end_time' => $attendance->period?->end_time?->format('H:i'),
                ],
                'status' => $attendance->status,
                'marked_by' => [
                    'id' => $attendance->markedBy?->id,
                    'name' => $attendance->markedBy?->name,
                ],
                'marked_at' => $attendance->marked_at?->toDateTimeString(),
                'notes' => $attendance->notes,

                // Oldest first, so the page reads as the story of the record
                // rather than as a reverse-chronological log.
                'audits' => $attendance->audits
                    ->sortBy('created_at')
                    ->values()
                    ->map(fn (AttendanceAudit $audit): array => [
                        'id' => $audit->id,
                        'edited_by_name' => $audit->editedBy?->name,
                        'old_status' => $audit->old_status,
                        'new_status' => $audit->new_status,
                        'reason' => $audit->reason,
                        'created_at' => $audit->created_at?->toDateTimeString(),
                    ])
                    ->all(),
            ],
        ]);
    }

    /**
     * Correct one record's status.
     *
     * The write and its audit entry share a transaction. A mark that changed
     * without a trail - or a trail describing a change that rolled back -
     * would leave the record in exactly the state this module exists to
     * prevent.
     */
    public function update(
        UpdateAttendanceRequest $request,
        Attendance $attendance,
    ): RedirectResponse {
        $newStatus = $request->validated('status');

        // Refused rather than recorded: an unchanged status would add a
        // misleading "correction" to a permanent trail, making it look as
        // though the record had been touched when it had not.
        if ($newStatus === $attendance->status) {
            return back()->with('error', 'The status is unchanged.');
        }

        $oldStatus = $attendance->status;

        DB::transaction(function () use ($attendance, $oldStatus, $newStatus, $request): void {
            $attendance->update(['status' => $newStatus]);

            $this->recordAudit($attendance, $oldStatus, $newStatus, $request->validated('reason'));
        });

        return redirect()
            ->route('admin.attendance.show', $attendance)
            ->with('success', 'Attendance updated.');
    }

    /**
     * Correct a whole period's register in one pass.
     *
     * The scope is carried in the payload and re-checked against every id,
     * so a stale tab holding ids from a different period cannot rewrite it:
     * an id that does not belong to the named class_subject, period and date
     * is skipped rather than trusted.
     */
    public function bulkUpdate(BulkUpdateAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $inScope = Attendance::query()
            ->where('class_subject_id', $data['class_subject_id'])
            ->where('period_id', $data['period_id'])
            ->where('attendance_date', $data['attendance_date'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($inScope === []) {
            return back()->with(
                'error',
                'No attendance records found for the given combination.',
            );
        }

        // Old statuses captured before the writes, so a batch of ids that name
        // the same row twice records the change once rather than writing
        // "absent to present, present to absent" for a single record.
        $current = Attendance::query()
            ->whereIn('id', $inScope)
            ->pluck('status', 'id');

        $updated = 0;

        DB::transaction(function () use ($data, $inScope, $current, &$updated): void {
            foreach ($data['updates'] as $update) {
                $id = (int) $update['attendance_id'];

                if (! in_array($id, $inScope, true)) {
                    continue;
                }

                $oldStatus = (string) $current[$id];
                $newStatus = $update['status'];

                if ($oldStatus === $newStatus) {
                    continue;
                }

                Attendance::query()->whereKey($id)->update([
                    'status' => $newStatus,
                    'updated_at' => now(),
                ]);

                $this->recordAudit(
                    Attendance::query()->find($id),
                    $oldStatus,
                    $newStatus,
                    $data['reason'],
                );

                $updated++;
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'No records were changed.');
        }

        return back()->with('success', "Updated {$updated} records.");
    }

    /**
     * Remove a record created in error.
     *
     * Soft delete rather than a hard one, because the record may still be
     * evidence: the point is to take it off the roll without destroying what
     * it says. The audit row is written first, so a deletion that fails
     * halfway leaves the trail intact.
     */
    public function destroy(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $reason = trim($validated['reason']);

        DB::transaction(function () use ($attendance, $reason): void {
            $this->recordAudit(
                $attendance,
                $attendance->status,
                self::STATUS_DELETED,
                $reason,
            );

            $attendance->delete();
        });

        return back()->with('success', 'Attendance record deleted.');
    }

    /**
     * Write one audit row naming the admin who made a change.
     */
    private function recordAudit(
        Attendance $attendance,
        string $oldStatus,
        string $newStatus,
        string $reason,
    ): void {
        AttendanceAudit::create([
            'attendance_id' => $attendance->id,
            'edited_by' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);
    }

    /**
     * The filter values from the query string, normalised for the form.
     *
     * Ids are cast only when numeric, so a hand-edited URL cannot turn a
     * filter into a query against an unexpected value. The status is checked
     * against the model's list and falls back to 'all' rather than being
     * passed through to the column.
     *
     * @return array{class_id:int|null, date:string|null, class_subject_id:int|null, period_id:int|null, teacher_id:int|null, status:string, search:string}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', 'all');
        $date = $request->query('date');

        return [
            'class_id' => $this->numericId($request->query('class_id')),
            // Null rather than today, so the page can tell "no date chosen"
            // apart from "today"; the query itself defaults to today.
            'date' => is_string($date) && $date !== '' && $this->isDate($date)
                ? $date
                : null,
            'class_subject_id' => $this->numericId($request->query('class_subject_id')),
            'period_id' => $this->numericId($request->query('period_id')),
            'teacher_id' => $this->numericId($request->query('teacher_id')),
            'status' => in_array($status, Attendance::STATUSES, true) ? $status : 'all',
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    /**
     * The register as the filters describe it.
     *
     * Soft-deleted rows are already excluded by the model's global scope, so
     * a deleted record leaves the office's list exactly as it leaves the
     * teacher's.
     *
     * @param  array<string, mixed>  $filters
     */
    private function filtered(array $filters): Builder
    {
        return Attendance::query()
            ->with([
                'studentProfile.user',
                'classSubject.subject',
                'classSubject.classModel.stream',
                'period',
                'markedBy',
                'audits',
            ])
            // Defaults to today when no date was chosen: the office is nearly
            // always checking this morning, not the whole term.
            ->forDate($filters['date'] ?? Carbon::today()->toDateString())
            ->when(
                $filters['class_subject_id'],
                fn (Builder $query, int $id) => $query->where('class_subject_id', $id),
            )
            ->when(
                $filters['period_id'],
                fn (Builder $query, int $id) => $query->where('period_id', $id),
            )
            // The teacher who marked it, not the teacher who owns the
            // subject: an admin asking "who marked this" wants the author of
            // the mark, which is the only person accountable for it.
            ->when(
                $filters['teacher_id'],
                fn (Builder $query, int $id) => $query->where('marked_by', $id),
            )
            ->when(
                $filters['status'] !== 'all',
                fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->when(
                $filters['class_id'],
                fn (Builder $query, int $id) => $query->whereHas(
                    'classSubject',
                    fn (Builder $inner) => $inner->where('class_id', $id),
                ),
            )
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                // ILIKE, not LIKE: PostgreSQL's LIKE is case-sensitive, so
                // searching "ahmed" would never find "Ahmed Khan". The term is
                // bound rather than interpolated, and the orWhere stays inside
                // this closure so it cannot escape the filters around it.
                $term = '%'.$filters['search'].'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->whereHas(
                        'studentProfile.user',
                        fn (Builder $user) => $user->where('name', 'ilike', $term),
                    )->orWhereHas(
                        'studentProfile',
                        fn (Builder $profile) => $profile->where(
                            'roll_number',
                            'ilike',
                            $term,
                        ),
                    );
                });
            })
            // Newest day first, then roll number, so one period reads as a
            // register rather than as an arbitrary ordering.
            ->orderByDesc('attendance_date')
            ->orderBy('class_subject_id')
            ->orderBy('period_id');
    }

    /**
     * How many records the current filter matches, by status.
     *
     * Rebuilt from the same filters rather than counted in PHP over the page,
     * because the page holds thirty rows and the answer has to describe the
     * whole result.
     *
     * SUM(CASE ...) rather than COUNT(*) FILTER, for the same reason as in
     * AttendanceService: the FILTER form needs a bound parameter per status,
     * and the SoftDeletes global scope contributes a positional binding of
     * its own. PDO rejects a statement carrying both, and the statuses here
     * are the model's own constants rather than user input.
     *
     * @param  array<string, mixed>  $filters
     * @return array{present: int, absent: int, late: int, leave: int, total: int}
     */
    private function counts(array $filters): array
    {
        $row = $this->filtered($filters)
            // Drop the ordering: a grouped count has no row order to apply and
            // keeping it would only make PostgreSQL sort a set it is about to
            // collapse to one row.
            ->reorder()
            ->selectRaw(sprintf(
                '
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN status = %1$s THEN 1 ELSE 0 END), 0) as present,
                COALESCE(SUM(CASE WHEN status = %2$s THEN 1 ELSE 0 END), 0) as absent,
                COALESCE(SUM(CASE WHEN status = %3$s THEN 1 ELSE 0 END), 0) as late,
                COALESCE(SUM(CASE WHEN status = %4$s THEN 1 ELSE 0 END), 0) as leave_
            ',
                $this->quote(Attendance::STATUS_PRESENT),
                $this->quote(Attendance::STATUS_ABSENT),
                $this->quote(Attendance::STATUS_LATE),
                $this->quote(Attendance::STATUS_LEAVE),
            ))
            ->first();

        return [
            'present' => (int) ($row->present ?? 0),
            'absent' => (int) ($row->absent ?? 0),
            'late' => (int) ($row->late ?? 0),
            'leave' => (int) ($row->leave ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    /**
     * A status wrapped in single quotes for a SQL literal.
     *
     * The values come from the Attendance model's own constants, never from
     * the request, so this is formatting rather than escaping - and inlining
     * them is what keeps the statement free of bound parameters.
     */
    private function quote(string $value): string
    {
        return "'".$value."'";
    }

    /**
     * One register row, shaped for the table.
     *
     * @return array<string, mixed>
     */
    private function summarise(Attendance $attendance): array
    {
        $student = $attendance->studentProfile;

        return [
            'id' => $attendance->id,
            'attendance_date' => $attendance->attendance_date->toDateString(),
            'student' => [
                'id' => $student?->id,
                'name' => $student?->user?->name,
                'roll_number' => $student?->roll_number,
            ],
            'class' => $attendance->classSubject?->classModel?->displayName(),
            'subject' => [
                'name' => $attendance->classSubject?->subject?->name,
                'code' => $attendance->classSubject?->subject?->code,
            ],
            'period' => [
                'number' => $attendance->period?->number,
                'label' => $attendance->period?->label,
            ],
            'status' => $attendance->status,
            'marked_by' => $attendance->markedBy?->name,

            // Time only, not the date: the date is its own column, and
            // repeating it here would make every row twice as wide.
            'marked_at' => $attendance->marked_at?->format('H:i'),

            // The scope keys the bulk edit needs, so the page can check that
            // a selection is one period before offering to save it.
            'class_subject_id' => $attendance->class_subject_id,
            'period_id' => $attendance->period_id,

            'audit_count' => $attendance->audits->count(),
        ];
    }

    /**
     * The periods of the active session for the filter drop-down.
     *
     * @return array<int, array{id: int, label: string}>
     */
    private function periodOptions(): array
    {
        $sessionId = AcademicSession::current()?->id;

        if ($sessionId === null) {
            return [];
        }

        return Period::teachingForSession($sessionId)
            ->map(fn (Period $period): array => [
                'id' => $period->id,
                'label' => $period->label,
            ])
            ->values()
            ->all();
    }

    /**
     * A positive integer id from a query value, or null.
     */
    private function numericId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Whether a string is a real calendar date in Y-m-d.
     *
     * Checked rather than parsed and trusted, because a hand-edited URL
     * carrying "2026-13-45" would otherwise reach the date column.
     */
    private function isDate(string $value): bool
    {
        return Carbon::hasFormat($value, 'Y-m-d')
            && Carbon::parse($value)->format('Y-m-d') === $value;
    }
}
