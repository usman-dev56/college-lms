<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTimetableRequest extends FormRequest
{
    /**
     * Only admins can rebuild a timetable.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for a whole timetable.
     *
     * Each row is one grid cell. Which class the assignment belongs to,
     * whether the period is a break, and whether the teacher is free are all
     * things the payload itself cannot prove, so those are checked in the
     * controller before anything is written.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slots' => ['present', 'array'],
            'slots.*.day_of_week' => ['required', 'integer', 'between:1,6'],
            'slots.*.period_id' => ['required', 'integer', 'exists:periods,id'],
            'slots.*.class_subject_id' => [
                'required',
                'integer',
                'exists:class_subjects,id',
            ],
            'slots.*.room' => ['nullable', 'string', 'max:50'],
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
            'slots.present' => 'The timetable could not be read. Please try again.',
            'slots.array' => 'The timetable must be a list of periods.',
            'slots.*.day_of_week.required' => 'Every period needs a day of the week.',
            'slots.*.day_of_week.between' => 'The day of the week must be between 1 (Monday) and 6 (Saturday).',
            'slots.*.period_id.required' => 'Every period needs a period.',
            'slots.*.period_id.exists' => 'One of the selected periods no longer exists.',
            'slots.*.class_subject_id.required' => 'Every period needs a subject.',
            'slots.*.class_subject_id.exists' => 'One of the selected subjects no longer exists.',
            'slots.*.room.max' => 'The room may not be longer than 50 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * The grid posts one row per filled cell, so anything that is not an
     * array (including a missing key) is normalised to an empty list rather
     * than being rejected as malformed.
     */
    protected function prepareForValidation(): void
    {
        $slots = $this->input('slots');

        $this->merge([
            'slots' => is_array($slots) ? array_values($slots) : [],
        ]);
    }
}
