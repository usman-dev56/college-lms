<?php

namespace App\Http\Requests\Admin;

use App\Models\Attendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateAttendanceRequest extends FormRequest
{
    /**
     * Only admins can correct marks in bulk.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * The shape of one bulk correction.
     *
     * The class_subject, period and date travel alongside the rows, not just
     * the row ids. The controller checks every submitted id against that
     * scope before writing, so a stale browser tab cannot rewrite a different
     * period by holding on to ids from the one the admin was looking at.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'class_subject_id' => ['required', 'integer', 'exists:class_subjects,id'],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'attendance_date' => ['required', 'date_format:Y-m-d'],

            'updates' => ['required', 'array', 'min:1'],

            'updates.*.attendance_id' => ['required', 'integer', 'exists:attendances,id'],

            'updates.*.status' => [
                'required',
                'string',
                'in:'.implode(',', Attendance::STATUSES),
            ],

            // One reason for the whole batch, because a bulk edit is one
            // decision - "the register was mislaid for this period" - and
            // asking for it thirty times would produce thirty slightly
            // different excuses for one event.
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Human-readable validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'updates.*.status.in' => 'Status must be present, absent, late, or leave.',
            'reason.required' => 'Give a reason for the correction.',
            'reason.max' => 'The reason may not be longer than 255 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Trimmed for the same reason as the single-record request: a blank is not
     * a reason, and this one is written to a permanent trail.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => is_string($this->input('reason'))
                ? trim((string) $this->input('reason'))
                : $this->input('reason'),
        ]);
    }
}
