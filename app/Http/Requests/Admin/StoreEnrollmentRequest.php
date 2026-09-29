<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    /**
     * Only admins can enroll students.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for enrolling students into a class.
     *
     * One class, many students: the office adds a whole section's worth in a
     * single pass, which is the only way this is done in practice.
     *
     * The class is also carried on the route as {class}, and is validated
     * here as well. The form post has to be able to name its own target - the
     * modal is built from one class but the payload does not have to trust
     * the page it was rendered on.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],

            // At least one student, or the form would report "Enrolled 0
            // students" and look like it had done something.
            'student_profile_ids' => ['required', 'array', 'min:1'],

            // Per-element: this is the rule that stops a crafted payload
            // putting a non-existent id in the array. The wildcard applies to
            // every element, not just the first.
            'student_profile_ids.*' => ['integer', 'exists:student_profiles,id'],
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
            'class_id.required' => 'Select a class to enroll students into.',
            'class_id.exists' => 'The selected class no longer exists.',
            'student_profile_ids.required' => 'Select at least one student to enroll.',
            'student_profile_ids.min' => 'Select at least one student to enroll.',
            'student_profile_ids.*.exists' => 'One of the selected students no longer exists.',
        ];
    }
}
