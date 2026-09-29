<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student sitting in a class, in a particular academic session.
 *
 * The session is stored on the row as well as being reachable through the
 * class, because "which class is this student in *now*" is a question asked
 * constantly and it should not need a join to answer. It is always the same
 * value as the class's own session - there is no code path that writes a
 * different one - and the unique index on (student, session) depends on that
 * being true.
 *
 * A student has at most one live row per session. Moving class soft deletes
 * the old row and writes a new one, which is why the unique index is partial
 * on deleted_at.
 */
class Enrollment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'student_profile_id',
        'class_id',
        'academic_session_id',
        'enrolled_at',
        'status',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
        ];
    }

    /**
     * The enrolled student.
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * The class they sit in.
     *
     * Named classModel rather than class because "class" is a reserved word
     * in PHP, the same reason the model itself is called ClassModel.
     */
    public function classModel(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    /**
     * The session this enrollment belongs to.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Scope: only live enrollments.
     *
     * A transferred or withdrawn student is not on the roll, and the
     * SoftDeletes scope means a soft-deleted one is not either.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
