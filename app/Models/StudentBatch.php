<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cohort of students admitted together, named for the span it covers:
 * "2026-2028" was admitted in 2026 and is expected to graduate in 2028.
 *
 * A batch is not the same thing as an academic session. A session is the
 * operational year the college is currently running, and exactly one of those
 * is active; a batch spans several sessions, and several batches are active
 * at the same time, because each one is a different year group.
 */
class StudentBatch extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'name',
        'start_grade',
        'expected_graduation_year',
        'is_active',
        'notes',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'start_grade' => 'integer',
            'expected_graduation_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope: only active batches.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The batch that a student admitted in a given year belongs to.
     *
     * The admission year is the first half of the name, so 2026 matches
     * "2026-2028". Null when no batch has that intake year yet.
     */
    public static function forAdmissionYear(int $year): ?self
    {
        return static::query()
            ->where('name', 'like', $year.'-%')
            ->first();
    }

    /**
     * The grade level this batch sits in during the active session, or null
     * when there is nothing meaningful to show.
     *
     * A batch gains a grade level for every year after the one it was
     * admitted in, so a cohort admitted in 2026 at grade 11 is in grade 12
     * during the 2027-2028 session. The year is taken from the start of the
     * active session's name, because the academic year opens in August and a
     * cohort is promoted in August too.
     *
     * A batch that has climbed past grade 12 has graduated, and an inactive
     * batch is not counted at all.
     */
    public function getCurrentGradeAttribute(): ?int
    {
        if (! $this->is_active) {
            return null;
        }

        $session = AcademicSession::current();

        if ($session === null) {
            return null;
        }

        $admissionYear = (int) substr($this->name, 0, 4);
        $sessionYear = (int) substr($session->name, 0, 4);

        $currentGrade = $this->start_grade + ($sessionYear - $admissionYear);

        return $currentGrade > 12 ? null : $currentGrade;
    }

    /*
     * Sub-stage 3.2 adds the students on this batch:
     *
     *     public function studentProfiles(): HasMany
     *     {
     *         return $this->hasMany(StudentProfile::class, 'batch_id');
     *     }
     *
     * It stays a comment until then: the student_profiles table does not
     * exist yet, so a real method could only fail later. The delete guard in
     * StudentBatchController looks for it with method_exists().
     */
}
