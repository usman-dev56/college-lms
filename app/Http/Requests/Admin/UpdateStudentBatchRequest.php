<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentBatchRequest extends FormRequest
{
    /**
     * Only admins can update student batches.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for updating an existing student batch.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $batchId = $this->route('student_batch')?->id;

        return [
            // The batch being edited must not collide with itself when it
            // keeps its own name, but must still reject a different one.
            'name' => [
                'required',
                'string',
                'max:30',
                'regex:/^\d{4}-\d{4}$/',
                Rule::unique('student_batches', 'name')
                    ->ignore($batchId)
                    ->whereNull('deleted_at'),
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
            'name' => is_string($this->input('name'))
                ? trim($this->input('name'))
                : $this->input('name'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
