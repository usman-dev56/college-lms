<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherRequest extends FormRequest
{
    /**
     * Only admins can create teachers.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for creating a teacher.
     *
     * Email and phone are checked against every user row, including
     * soft-deleted ones, because the unique indexes on those columns are not
     * partial: a soft-deleted teacher still holds their email and phone at
     * the database level, so ignoring them here would let the form pass and
     * then fail on insert.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]*$/',
                Rule::unique('users', 'phone'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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
            'name.required' => 'Enter the teacher\'s name.',
            'name.max' => 'The name may not be longer than 100 characters.',
            'email.required' => 'Enter an email address.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'phone.regex' => 'The phone number may only contain digits, spaces and the + - ( ) characters.',
            'phone.unique' => 'This phone number is already in use.',
            'password.required' => 'Enter a password.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Text is trimmed, a blank optional phone becomes null rather than an
     * empty string (which would collide with the unique index), and the
     * checkbox becomes a real boolean.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name'))
                ? trim((string) $this->input('name'))
                : $this->input('name'),
            'email' => is_string($this->input('email'))
                ? mb_strtolower(trim((string) $this->input('email')))
                : $this->input('email'),
            'phone' => $this->filled('phone')
                ? trim((string) $this->input('phone'))
                : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
