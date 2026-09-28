<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTeacherRequest extends FormRequest
{
    /**
     * Only admins can update teachers.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for updating an existing teacher.
     *
     * The password is optional: leaving it blank keeps the current one. The
     * unique checks ignore soft-deleted rows here - the teacher being edited
     * is not deleted, so it cannot be a match - but the database indexes are
     * still full unique indexes, so live rows of any role must be rejected.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teacherId = $this->route('teacher')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($teacherId),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]*$/',
                Rule::unique('users', 'phone')->ignore($teacherId),
            ],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
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
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Text is trimmed, a blank optional phone or password becomes null, and
     * the checkbox becomes a real boolean. Password is only kept when both
     * fields were filled in, so a half-filled form does not wipe it.
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
            'password' => $this->filled('password') ? $this->input('password') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
