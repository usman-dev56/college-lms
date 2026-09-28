<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimetableSlot extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'timetable_slots';

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'class_id',
        'period_id',
        'day_of_week',
        'class_subject_id',
        'room',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /**
     * The class this slot belongs to.
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
     * The period of the day this slot occupies.
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * The teaching assignment scheduled in this cell.
     */
    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    /**
     * Every slot of one class, with the period and the subject and teacher it
     * teaches.
     *
     * The three relations are eager loaded together, so rendering a whole
     * timetable costs a fixed number of queries rather than three per cell.
     *
     * @return Collection<int, static>
     */
    public static function forClass(int $classId): Collection
    {
        return static::query()
            ->where('class_id', $classId)
            ->with([
                'period',
                'classSubject.subject',
                'classSubject.teacher',
            ])
            ->orderBy('day_of_week')
            ->orderBy('period_id')
            ->get();
    }
}
