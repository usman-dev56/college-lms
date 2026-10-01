<?php

namespace App\Models;

use Database\Factories\AttendanceAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One correction made to one attendance record.
 *
 * The audit trail. Deliberately has no soft deletes: a row here means "this
 * change happened", and if it was wrong the answer is another row saying so,
 * not removing the first.
 *
 * old_status and new_status are plain strings rather than the four live marks,
 * because one of them is 'deleted' - the terminal state of a removed record.
 */
class AttendanceAudit extends Model
{
    /** @use HasFactory<AttendanceAuditFactory> */
    use HasFactory;

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'attendance_id',
        'edited_by',
        'old_status',
        'new_status',
        'reason',
    ];

    /**
     * The record this correction was made to.
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * The admin who made the correction.
     *
     * The foreign key is edited_by, which is not the conventional user_id, so
     * it is given explicitly.
     */
    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
