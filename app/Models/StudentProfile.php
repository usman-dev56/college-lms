<?php

namespace App\Models;

use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Everything about a student that is not their login.
 *
 * A student is a User with the Student role, exactly as a teacher is a User
 * with the Teacher role, so the account half lives in users and this table
 * holds the rest: the roll number, the batch they were admitted with, and the
 * personal and academic detail a school office records.
 *
 * A profile is soft deleted alongside its user, never on its own, so that a
 * withdrawn or expelled student leaves no half-record behind.
 */
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'user_id',
        'batch_id',
        'roll_number',
        'board_registration_number',
        'cnic_bform',
        'date_of_birth',
        'gender',
        'father_name',
        'guardian_phone',
        'address',
        'photo_path',
        'admission_date',
        'previous_school',
        'previous_marks_obtained',
        'previous_marks_total',
        'status',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'previous_marks_obtained' => 'integer',
            'previous_marks_total' => 'integer',
        ];
    }

    /**
     * The login account this profile belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The cohort the student was admitted with.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StudentBatch::class, 'batch_id');
    }

    /**
     * Every enrollment this student has ever had.
     *
     * Includes soft-deleted and non-active rows: this is the history, and a
     * transferred or withdrawn enrollment is exactly the sort of record a
     * student's page wants to show. The live one is currentEnrollment().
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_profile_id');
    }

    /**
     * The class this student sits in during the active session, or null.
     *
     * A method rather than a hasOne because "the active session" is a runtime
     * fact, not a column on the student. A hasOne would be resolved once when
     * the relation was loaded and would keep answering for whichever session
     * happened to be active at that moment.
     *
     * Null is a normal answer: a student is enrolled into a class for the
     * coming session, and until the office puts them in one they are on the
     * roll but not in a class.
     */
    public function currentEnrollment(): ?Enrollment
    {
        $activeSession = AcademicSession::current();

        if (! $activeSession) {
            return null;
        }

        return $this->enrollments()
            ->where('academic_session_id', $activeSession->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Scope: only students still at the college.
     *
     * The mirror of a 'graduated' or 'withdrawn' status, which keeps the
     * record but takes the student out of the current roll.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * The student's name, for display next to the profile.
     *
     * Falls back to a placeholder rather than null so a list row or a table
     * cell never renders an empty box. The fallback is for the rare case of a
     * soft-deleted user still attached to a live profile.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->user?->name ?? 'Unknown Student';
    }

    /**
     * The next free roll number within a batch, as a zero-padded string.
     *
     * Roll numbers are per batch, not global, so this asks what is already
     * taken on one batch only: '001' is free on every batch at once.
     *
     * Only digits are counted. An admin may type a roll number by hand
     * ("12-A", "007"), and Postgres would refuse to cast those to an integer
     * and fail the whole query, so they are filtered out rather than assumed
     * away. That can hand out a number an admin chose deliberately, which is
     * the lesser evil next to a failed insert; the unique index still has the
     * final say on what can actually be saved.
     */
    public static function nextRollNumber(int $batchId): string
    {
        $highest = static::query()
            ->where('batch_id', $batchId)
            ->whereNotNull('roll_number')
            ->whereRaw("roll_number ~ '^[0-9]+$'")
            ->max(DB::raw('CAST(roll_number AS INTEGER)'));

        return str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
    }
}
