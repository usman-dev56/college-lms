<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreSubjectRequest extends FormRequest
{
    /**
     * Only admins can create subjects.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for creating a new subject.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                $this->uniqueNameRule(),
            ],
            'code' => ['nullable', 'string', 'max:20'],
            'grade_level' => ['required', 'integer', 'in:11,12'],
            'stream_id' => [
                'nullable',
                'integer',
                Rule::exists('streams', 'id')->whereNull('deleted_at'),
            ],
            'has_practical' => ['boolean'],
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
            'name.unique' => 'This subject already exists for the selected grade level and stream.',
            'grade_level.in' => 'The grade level must be either 11 or 12.',
            'stream_id.exists' => 'The selected stream does not exist.',
        ];
    }

    /**
     * A subject name is unique per grade level and stream, and only among
     * rows that have not been soft deleted - mirroring the partial unique
     * index on the subjects table.
     */
    protected function uniqueNameRule(): Unique
    {
        $rule = Rule::unique('subjects', 'name')
            ->where('grade_level', $this->input('grade_level'))
            ->whereNull('deleted_at');

        $streamId = $this->input('stream_id');

        return $streamId === null || $streamId === ''
            ? $rule->whereNull('stream_id')
            : $rule->where('stream_id', $streamId);
    }

    /**
     * Prepare the data for validation.
     *
     * The stream selector sends an empty string for "compulsory", and the
     * checkboxes have to become real booleans before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? $this->input('code') : null,
            'stream_id' => $this->filled('stream_id') ? $this->input('stream_id') : null,
            'has_practical' => $this->boolean('has_practical'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
