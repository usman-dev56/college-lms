<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicSessionRequest extends FormRequest
{
    /**
     * Only admins can update academic sessions.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for updating an existing academic session.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sessionId = $this->route('academic_session')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d{4}-\d{4}$/',
                Rule::unique('academic_sessions', 'name')->ignore($sessionId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
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
            'name.regex' => 'The name must be in the format YYYY-YYYY (e.g., 2026-2027).',
            'name.unique' => 'A session with this name already exists.',
            'end_date.after' => 'The end date must be after the start date.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}