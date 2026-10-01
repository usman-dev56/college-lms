<?php

namespace App\Models;

use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One student's status for one class-subject, in one period, on one date.
 *
 * This is the register. Unlike most of the schema it is a legal record: it is
 * the evidence behind a student's board eligibility, and a college that
 * cannot produce it cannot answer a challenge. Two consequences run through
 * the whole design. The foreign keys all restrict, so an attendance row is
 * never destroyed as a side effect of deleting something else. And the row is
 * soft deleted rather than removed when a mark has to be undone, so the
 * original stays on file alongside the correction.
 *
 * marked_by is stored even though the teacher is reachable through the
 * class-subject: the question "who marked this disputed period" has to be
 * answerable after that teacher has been reassigned or has left.
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The student sat the period.
     */
    public const STATUS_PRESENT = 'present';

    /**
     * The student did not sit the period.
     */
    public const STATUS_ABSENT = 'absent';

    /**
     * The student arrived after the period started but was taught.
     */
    public const STATUS_LATE = 'late';

    /**
     * Approved leave. Counted as attended for the attendance percentage,
     * because the college sanctioned it.
     */
    public const STATUS_LEAVE = 'leave';

    /**
     * Every status a mark can have, in the order a register column shows
     * them: the four marks a teacher picks between, best to worst.
     *
     * A single source of truth, so the form request, the seeder and the
     * summary all read the same list and a new status cannot be added to one
     * of them and forgotten in another.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_LATE,
        self::STATUS_LEAVE,
    ];

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'student_profile_id',
        'class_subject_id',
        'period_id',
        'attendance_date',
        'status',
        'marked_by',
        'marked_at',
        'notes',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'marked_at' => 'datetime',
        ];
    }

    /**
     * The student this mark belongs to.
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * The teaching assignment the period belonged to, which is what names
     * the subject, the class and the teacher.
     */
    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    /**
     * The period of the day this mark is for.
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * The teacher who wrote the mark.
     *
     * The foreign key is marked_by, which is not the conventional user_id, so
     * it is given explicitly.
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * Scope: marks for one calendar day.
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('attendance_date', $date);
    }

    /**
     * Scope: marks that count as attended.
     *
     * Late and leave are in here and absent is not, which is the whole
     * definition of an attendance percentage: a student who arrived late was
     * in the room, and a student on sanctioned leave was not at fault.
     */
    public function scopePresent(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PRESENT,
            self::STATUS_LATE,
            self::STATUS_LEAVE,
        ]);
    }

    /**
     * Scope: marks that do not count as attended.
     */
    public function scopeAbsent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ABSENT);
    }

    /**
     * Scope: one subject's register.
     */
    public function scopeForClassSubject(Builder $query, int $classSubjectId): Builder
    {
        return $query->where('class_subject_id', $classSubjectId);
    }

    /**
     * Scope: one student's attendance history.
     */
    public function scopeForStudent(Builder $query, int $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }

    /**
     * The human-readable name of a status, for a register header or a badge.
     *
     * An unknown value returns the raw string rather than an empty cell: the
     * database CHECK makes one impossible, but a caller passing a typo should
     * see the typo rather than a blank.
     */
    public static function statusLabel(string $status): string
    {
        return ucfirst($status);
    }

    /**
     * The Tailwind classes that colour a status badge.
     *
     * Leave is deliberately not grey: an approved leave is a positive outcome
     * and should not read as a problem.
     *
     * Unknown values fall back to the neutral badge rather than throwing, so a
     * status added later renders as grey instead of breaking the page.
     */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_PRESENT => 'bg-green-100 text-green-800',
            self::STATUS_ABSENT => 'bg-red-100 text-red-800',
            self::STATUS_LATE => 'bg-amber-100 text-amber-800',
            self::STATUS_LEAVE => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
