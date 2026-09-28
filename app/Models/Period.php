<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Period extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'academic_session_id',
        'number',
        'label',
        'start_time',
        'end_time',
        'is_break',
    ];

    /**
     * Attribute casts.
     *
     * The times are stored as PostgreSQL `time` values, which carry no date.
     * Casting them to datetime lets Laravel parse and re-serialise them, and
     * the H:i format is exactly the HH:MM shape an HTML time input expects.
     * Only the time-of-day part ever reaches the database or the browser.
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'is_break' => 'boolean',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    /**
     * The session whose daily schedule this period belongs to.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The periods of one session, in timetable order.
     *
     * @return Collection<int, static>
     */
    public static function forSession(int $sessionId): Collection
    {
        return static::query()
            ->where('academic_session_id', $sessionId)
            ->orderBy('number')
            ->get();
    }

    /**
     * The teaching periods of one session, in timetable order.
     *
     * Breaks live in the grid so the timetable lines up, but they are never
     * offered when choosing a slot to teach.
     *
     * @return Collection<int, static>
     */
    public static function teachingForSession(int $sessionId): Collection
    {
        return static::query()
            ->where('academic_session_id', $sessionId)
            ->where('is_break', false)
            ->orderBy('number')
            ->get();
    }
}
