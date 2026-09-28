<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentBatchRequest extends FormRequest
{
    /**
     * Only admins can create student batches.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for creating a new student batch.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The name is the cohort's identity, and the admission year is
            // read back out of it, so the YYYY-YYYY shape is enforced rather
            // than suggested. The unique rule is scoped to non-deleted rows so
            // it lines up with the partial index on the table.
            'name' => [
                'required',
                'string',
                'max:30',
                'regex:/^\d{4}-\d{4}$/',
                Rule::unique('student_batches', 'name')->whereNull('deleted_at'),
            ],
            'start_grade' => ['required', 'integer', 'in:9,10,11,12'],
            'expected_graduation_year' => [
                'required',
                'integer',
                'min:2020',
                'max:2100',
            ],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
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
            'name.regex' => 'The name must be in the format YYYY-YYYY (e.g., 2026-2028).',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // Trimming matters here: a stray space would fail the regex with
            // a message that reads as though the year itself were wrong.
            'name' => is_string($this->input('name'))
                ? trim($this->input('name'))
                : $this->input('name'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
