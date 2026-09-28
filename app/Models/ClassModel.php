<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassModel extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * The model is called ClassModel because "Class" is a reserved word in
     * PHP, so the table cannot follow the usual snake_case plural of the
     * class name - it is named "classes" explicitly.
     */
    protected $table = 'classes';

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'academic_session_id',
        'stream_id',
        'grade_level',
        'section',
        'capacity',
        'room',
        'is_active',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'grade_level' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The academic session this class runs in.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The stream this class belongs to.
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * The subjects taught in this class, with their teaching assignment.
     *
     * The pivot carries the teacher and the weekly period count, so those
     * columns are readable on each subject. Soft-deleted assignments are
     * excluded, which keeps a removed subject from looking like it is still
     * taught here.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subjects', 'class_id', 'subject_id')
            ->withPivot(['id', 'teacher_id', 'periods_per_week'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * Every teaching assignment in this class.
     *
     * Includes soft-deleted rows, so the admin can still see a subject that
     * was removed and restore it instead of creating a duplicate.
     */
    public function classSubjects(): HasMany
    {
        // The foreign key is given explicitly: Laravel would derive
        // "class_model_id" from the ClassModel class name.
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    /**
     * The weekly timetable cells of this class.
     */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class, 'class_id');
    }

    /**
     * Scope: only active classes.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: classes of one academic session.
     */
    public function scopeForSession(Builder $query, int $sessionId): Builder
    {
        return $query->where('academic_session_id', $sessionId);
    }

    /**
     * Scope: classes of a grade level (11 or 12).
     */
    public function scopeForGrade(Builder $query, int $grade): Builder
    {
        return $query->where('grade_level', $grade);
    }

    /**
     * Human-readable label for lists, e.g. "11th Pre-Medical A".
     *
     * The stream relationship is null-checked because a stream can be soft
     * deleted while classes still point at it, and because this method is
     * also usable on a class that was not loaded with the relationship.
     */
    public function displayName(): string
    {
        $ordinal = match ($this->grade_level) {
            11 => '11th',
            12 => '12th',
            default => (string) $this->grade_level,
        };

        $streamName = $this->stream?->name ?? 'Unknown stream';

        return trim("{$ordinal} {$streamName} {$this->section}");
    }
}
