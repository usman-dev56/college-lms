<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * An application to study at the college.
 *
 * This is not a student. Somebody can hold a row here without ever having
 * enrolled, which is the whole point: the public form collects applications
 * from people who have no account, and the office works through them
 * afterwards. A student is only created from an accepted application, in
 * sub-stage 3.5, which is when enrolled_student_profile_id gets filled in.
 *
 * Because there is no login behind a row, the application number is the only
 * handle on it. That is why it is unique, why the public form prints it, and
 * why the success page can be reached with nothing but that number.
 */
class Admission extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'admissions';

    /**
     * Mass-assignable attributes.
     *
     * Notably absent is nothing - all the columns are listed, but the ones a
     * public applicant could never be trusted with (status, merit_rank,
     * reviewed_at, reviewed_by, enrolled_student_profile_id) are only ever
     * filled in by staff actions, and the controller builds its create
     * payload by hand so a crafted POST cannot drop itself straight into
     * 'accepted'.
     */
    protected $fillable = [
        'application_number',
        'applicant_name',
        'father_name',
        'cnic_bform',
        'date_of_birth',
        'phone',
        'guardian_phone',
        'address',
        'previous_school',
        'previous_marks_obtained',
        'previous_marks_total',
        'stream_applied_id',
        'batch_id',
        'status',
        'merit_rank',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'enrolled_student_profile_id',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
            'previous_marks_obtained' => 'integer',
            'previous_marks_total' => 'integer',
            'merit_rank' => 'integer',
        ];
    }

    /**
     * The stream the applicant asked for.
     */
    public function streamApplied(): BelongsTo
    {
        return $this->belongsTo(Stream::class, 'stream_applied_id');
    }

    /**
     * The cohort the applicant would join if accepted.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StudentBatch::class);
    }

    /**
     * The admin who last looked at this application.
     *
     * Null if that account has been deleted, which the foreign key allows on
     * purpose - the review happened whether or not the person who did it
     * still works here.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The student this application became, once it has been enrolled.
     *
     * The reverse link on StudentProfile is deliberately not defined here:
     * one application produces at most one student, but a student might
     * have come from somewhere else entirely, so nothing here is guaranteed.
     */
    public function enrolledStudentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'enrolled_student_profile_id');
    }

    /**
     * Scope: applications nobody has looked at yet.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: applications that have been seen but not decided on.
     */
    public function scopeReviewed(Builder $query): Builder
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Scope: applications offered a place.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope: applications turned down.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope: accepted applications that have become students.
     */
    public function scopeEnrolled(Builder $query): Builder
    {
        return $query->where('status', 'enrolled');
    }

    /**
     * The applicant's matric percentage, or null when it cannot be worked out.
     *
     * Null rather than zero when either half of the pair is missing, and
     * also when the total is zero or negative: a zero total is a data-entry
     * slip, and reporting 0% for it would rank the applicant last instead of
     * saying "unknown".
     */
    public function getMeritPercentageAttribute(): ?float
    {
        if ($this->previous_marks_obtained === null || $this->previous_marks_total === null) {
            return null;
        }

        if ($this->previous_marks_total <= 0) {
            return null;
        }

        return round(($this->previous_marks_obtained / $this->previous_marks_total) * 100, 2);
    }

    /**
     * The next application number for a given year.
     *
     * The shape is ADM-YYYY-NNNN, with the year first so the numbers sort
     * chronologically as strings, which is how the office reads a printed
     * list of applications.
     *
     * Only numbers for the requested year are counted, so numbering restarts
     * at 0001 each January. The suffix is cast to an integer and the max
     * taken, rather than a lexical MAX() on the column: a string max happens
     * to work on zero-padded numbers today and stops working the moment one
     * runs past four digits or a row is edited by hand.
     *
     * The soft-delete scope applies, so withdrawing an application returns
     * its number to the pool - and because the unique index is partial on
     * the same condition, that number really is free again.
     */
    public static function generateApplicationNumber(int $year): string
    {
        $prefix = sprintf('ADM-%d-', $year);

        // SUBSTRING counts from 1, so the first digit of the suffix is one
        // past the end of the prefix.
        $suffix = "SUBSTRING(application_number FROM ".(strlen($prefix) + 1).')';

        $highest = static::query()
            ->where('application_number', 'like', $prefix.'%')
            // The LIKE above would also match "ADM-2026-ABC"; this narrows it
            // to a purely numeric suffix before the cast, which would
            // otherwise fail the whole query on one hand-edited row.
            ->whereRaw("application_number ~ '^".$prefix.'[0-9]+$\'')
            ->max(DB::raw("CAST({$suffix} AS INTEGER)"));

        return $prefix.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}


