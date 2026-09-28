<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'name',
        'code',
        'grade_level',
        'stream_id',
        'has_practical',
        'is_active',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'grade_level' => 'integer',
            'has_practical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The stream that offers this subject.
     *
     * Null for compulsory subjects, which every student takes.
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * Every stream that offers this subject.
     *
     * Elective subjects are linked to their stream(s) through the
     * stream_subject pivot table, so a subject such as Physics can be
     * shared by Pre-Medical, Pre-Engineering, ICS and so on.
     */
    public function streams(): BelongsToMany
    {
        return $this->belongsToMany(Stream::class, 'stream_subject')->withTimestamps();
    }

    /**
     * Every teaching assignment for this subject, across classes.
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    /**
     * Scope: only active subjects.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: subjects taught in a given grade level (11 or 12).
     */
    public function scopeForGrade(Builder $query, int $grade): Builder
    {
        return $query->where('grade_level', $grade);
    }

    /**
     * Scope: compulsory subjects, shared by every stream.
     */
    public function scopeCompulsory(Builder $query): Builder
    {
        return $query->whereNull('stream_id');
    }

    /**
     * Scope: elective subjects, offered by a specific stream.
     */
    public function scopeElective(Builder $query): Builder
    {
        return $query->whereNotNull('stream_id');
    }
}
