<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassSubject extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * The model is the pivot between classes and subjects, carrying the
     * teaching assignment itself: which teacher, and how many periods a week.
     */
    protected $table = 'class_subjects';

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'periods_per_week',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'periods_per_week' => 'integer',
        ];
    }

    /**
     * The class this subject is taught in.
     *
     * Named classModel() because "class" is a reserved word in PHP, and the
     * foreign key is given explicitly because it cannot be inferred from
     * that name.
     */
    public function classModel(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    /**
     * The subject that is taught.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The teacher responsible for this subject in this class.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Every mark recorded against this teaching assignment.
     *
     * This is the register for one subject in one class, which is the unit a
     * teacher marks a period by period.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'class_subject_id');
    }

    /**
     * The timetable cells this assignment is scheduled into.
     */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }
}
