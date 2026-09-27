<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateClassRequest extends FormRequest
{
    /**
     * Only admins can update classes.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for updating an existing class.
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
            'stream_id' => [
                'required',
                'integer',
                Rule::exists('streams', 'id')->whereNull('deleted_at'),
            ],
            'grade_level' => ['required', 'integer', 'in:11,12'],
            'section' => [
                'required',
                'string',
                'max:10',
                'uppercase',
                $this->uniqueSectionRule(),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'room' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
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
            'academic_session_id.exists' => 'The selected academic session does not exist.',
            'stream_id.exists' => 'The selected stream does not exist.',
            'grade_level.in' => 'The grade level must be either 11 or 12.',
            'section.unique' => 'This section already exists for the selected session, grade level and stream.',
            'section.uppercase' => 'The section must be uppercase (e.g., A, B, C).',
            'capacity.min' => 'The capacity must be at least 1.',
            'capacity.max' => 'The capacity may not be greater than 500.',
        ];
    }

    /**
     * One class per session + grade level + stream + section, and only among
     * rows that have not been soft deleted - mirroring the partial unique
     * index on the classes table. The class being edited is ignored.
     */
    protected function uniqueSectionRule(): Unique
    {
        return Rule::unique('classes', 'section')
            ->ignore($this->route('class')?->id)
            ->where('academic_session_id', $this->input('academic_session_id'))
            ->where('grade_level', $this->input('grade_level'))
            ->where('stream_id', $this->input('stream_id'))
            ->whereNull('deleted_at');
    }

    /**
     * Prepare the data for validation.
     *
     * Sections are normalised to uppercase, optional text is trimmed, empty
     * optional values become null and the checkbox becomes a real boolean.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'section' => is_string($this->input('section'))
                ? strtoupper(trim($this->input('section')))
                : $this->input('section'),
            'room' => $this->filled('room')
                ? trim((string) $this->input('room'))
                : null,
            'capacity' => $this->filled('capacity') ? $this->input('capacity') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
