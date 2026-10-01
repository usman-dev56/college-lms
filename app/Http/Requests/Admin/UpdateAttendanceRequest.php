<?php

namespace App\Http\Requests\Admin;

use App\Models\Attendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
{
    /**
     * Only admins can correct a mark.
     *
     * The teacher who submitted it cannot, which is the whole point of this
     * module: the register is closed to its author and open only to the
     * office, and every correction is written down.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * The new status and why.
     *
     * The reason is required rather than optional: a correction nobody can
     * explain is indistinguishable from a correction nobody should have made.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.implode(',', Attendance::STATUSES)],
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
            'status.in' => 'Status must be present, absent, late, or leave.',
            'reason.required' => 'Give a reason for the correction.',
            'reason.max' => 'The reason may not be longer than 255 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Trimmed, so a reason of nothing but spaces fails the required rule
     * instead of being written to the permanent audit trail as a blank.
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
