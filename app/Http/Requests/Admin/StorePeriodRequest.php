<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeriodRequest extends FormRequest
{
    /**
     * Only admins can create periods.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for creating a period.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_session_id' => [
                'required',
                'integer',
                Rule::exists('academic_sessions', 'id')->whereNull('deleted_at'),
            ],
            // One row per grid position per session, and only among rows that
            // have not been soft deleted - mirroring the partial unique index.
            'number' => [
                'required',
                'integer',
                'min:1',
                'max:20',
                Rule::unique('periods')
                    ->where(fn ($query) => $query
                        ->where('academic_session_id', $this->input('academic_session_id')))
                    ->whereNull('deleted_at'),
            ],
            'label' => ['required', 'string', 'max:30'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_break' => ['boolean'],
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
            'academic_session_id.required' => 'Choose the academic session this period belongs to.',
            'number.required' => 'Enter the period number.',
            'number.min' => 'The period number must be at least 1.',
            'number.max' => 'The period number may not be greater than 20.',
            'label.required' => 'Enter a label, e.g. "Period 1" or "Break".',
            'label.max' => 'The label may not be longer than 30 characters.',
            'start_time.required' => 'Enter a start time.',
            'start_time.date_format' => 'The start time must be in 24-hour HH:MM format.',
            'end_time.required' => 'Enter an end time.',
            'end_time.date_format' => 'The end time must be in 24-hour HH:MM format.',
            'end_time.after' => 'The end time must be after the start time.',
            'number.unique' => 'This session already has a period in that grid row.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * The label is trimmed and the checkbox becomes a real boolean. Times are
     * left exactly as typed: they are already HH:MM, and normalising them
     * here would hide what the browser actually submitted.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => is_string($this->input('label'))
                ? trim((string) $this->input('label'))
                : $this->input('label'),
            'is_break' => $this->boolean('is_break'),
        ]);
    }
}
