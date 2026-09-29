<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkUnenrollRequest extends FormRequest
{
    /**
     * Only admins can unenroll students.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for removing several students at once.
     *
     * Ids rather than student ids, because the thing being removed is the
     * enrollment, not the student: the student stays on the roll and can be
     * put in another class.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'exists:enrollments,id'],
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
            'enrollment_ids.required' => 'Select at least one student to remove.',
            'enrollment_ids.min' => 'Select at least one student to remove.',
            'enrollment_ids.*.exists' => 'One of the selected enrollments no longer exists.',
        ];
    }
}
