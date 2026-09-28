<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassSubjectsRequest extends FormRequest
{
    /**
     * Only admins can change the teaching assignments of a class.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for the whole assignment form.
     *
     * The request carries one row per subject the admin chose to keep, and
     * only those rows are sent - a subject that is missing from the payload
     * is removed from the class.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array'],
            'assignments.*.subject_id' => [
                'required',
                'integer',
                // A subject may only be listed once per class; the database
                // enforces the same rule with a partial unique index.
                'distinct',
                Rule::exists('subjects', 'id')->whereNull('deleted_at'),
            ],
            'assignments.*.teacher_id' => [
                'required',
                'integer',
                // Only active teachers can be assigned, which keeps admins
                // and students from being scheduled by accident.
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Teacher->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'assignments.*.periods_per_week' => ['required', 'integer', 'between:1,20'],
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
            'assignments.required' => 'Assign at least one subject to this class before saving.',
            'assignments.array' => 'The assignments must be a list of subjects.',
            'assignments.*.subject_id.required' => 'Each assignment needs a subject.',
            'assignments.*.subject_id.exists' => 'One of the selected subjects no longer exists.',
            'assignments.*.subject_id.distinct' => 'A subject can only be assigned once to a class.',
            'assignments.*.teacher_id.required' => 'Choose a teacher for every subject you are keeping.',
            'assignments.*.teacher_id.exists' => 'The selected teacher is not an active teacher.',
            'assignments.*.periods_per_week.required' => 'Enter how many periods per week this subject gets.',
            'assignments.*.periods_per_week.between' => 'Periods per week must be between 1 and 20.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Numbers arrive as strings from the form, so they are cast before the
     * integer rules run. Rows without a teacher are dropped, because on this
     * form an empty teacher drop-down means "do not teach this subject here".
     */
    protected function prepareForValidation(): void
    {
        $assignments = $this->input('assignments');

        if (! is_array($assignments)) {
            return;
        }

        $normalised = [];

        foreach ($assignments as $assignment) {
            if (! is_array($assignment)) {
                continue;
            }

            if (! is_numeric($assignment['teacher_id'] ?? null)) {
                continue;
            }

            $normalised[] = [
                'subject_id' => $this->toIntegerOrNull($assignment['subject_id'] ?? null),
                'teacher_id' => (int) $assignment['teacher_id'],
                'periods_per_week' => $this->toIntegerOrNull($assignment['periods_per_week'] ?? null),
            ];
        }

        $this->merge(['assignments' => $normalised]);
    }

    /**
     * Cast a numeric string to an integer, leaving anything else untouched so
     * the validation rules can report it.
     */
    private function toIntegerOrNull(mixed $value): int|string|null
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
