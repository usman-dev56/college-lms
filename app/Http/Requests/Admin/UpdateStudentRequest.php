<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateStudentRequest extends FormRequest
{
    /**
     * Only admins can update students.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for updating an existing student.
     *
     * The same rules as the store request, with three differences: the unique
     * checks ignore the record being edited, the password is optional because
     * a blank field means "keep the current one", and the roll number is
     * fully editable rather than merely auto-assignable.
     *
     * The roll number uniqueness is scoped by the *submitted* batch_id, not
     * the stored one: if the admin moves a student to another batch, the
     * number has to be free in the batch they are moving to, which may be a
     * different batch from the one it was checked against on load.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $profile = $this->route('student');
        $profileId = $profile?->id;
        $userId = $profile?->user_id;

        return [
            // Account
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]*$/',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
            'is_active' => ['boolean'],

            // Batch and roll number
            'batch_id' => ['required', 'integer', 'exists:student_batches,id'],
            'roll_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('student_profiles', 'roll_number')
                    ->where(fn ($query) => $query
                        ->where('batch_id', $this->input('batch_id'))
                        ->whereNull('deleted_at'))
                    ->ignore($profileId),
            ],

            // Student details
            'father_name' => ['nullable', 'string', 'max:100'],
            'cnic_bform' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('student_profiles', 'cnic_bform')
                    ->whereNull('deleted_at')
                    ->ignore($profileId),
            ],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'guardian_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'admission_date' => ['nullable', 'date'],

            // Previous school record
            'previous_school' => ['nullable', 'string', 'max:150'],
            'previous_marks_obtained' => ['nullable', 'integer', 'min:0'],
            'previous_marks_total' => ['nullable', 'integer', 'min:1', 'gte:previous_marks_obtained'],

            'status' => [
                'nullable',
                Rule::in(['active', 'graduated', 'withdrawn', 'suspended']),
            ],
        ];
    }

    /**
     * Human-readable validation messages.
     *
     * The wording matches the store request so the two forms read the same;
     * only the password rules differ, because here a blank password is valid.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the student\'s name.',
            'name.max' => 'The name may not be longer than 100 characters.',
            'email.required' => 'Enter an email address.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'phone.regex' => 'The phone number may only contain digits, spaces and the + - ( ) characters.',
            'phone.unique' => 'This phone number is already in use.',
            'password.confirmed' => 'The two passwords do not match.',
            'batch_id.required' => 'Select the batch the student was admitted with.',
            'batch_id.exists' => 'The selected batch no longer exists.',
            'roll_number.max' => 'The roll number may not be longer than 20 characters.',
            'roll_number.unique' => 'That roll number is already taken in this batch.',
            'father_name.max' => 'The father\'s name may not be longer than 100 characters.',
            'cnic_bform.max' => 'The CNIC / B-Form number may not be longer than 20 characters.',
            'cnic_bform.unique' => 'This CNIC / B-Form number is already on file for another student.',
            'date_of_birth.before' => 'The date of birth must be in the past.',
            'gender.in' => 'Select male, female or other.',
            'guardian_phone.regex' => 'The guardian phone may only contain digits, spaces and the + - ( ) characters.',
            'admission_date.date' => 'Enter a valid admission date.',
            'previous_marks_obtained.integer' => 'The marks obtained must be a whole number.',
            'previous_marks_obtained.min' => 'The marks obtained cannot be negative.',
            'previous_marks_total.integer' => 'The marks total must be a whole number.',
            'previous_marks_total.min' => 'The marks total must be at least 1.',
            'previous_marks_total.gte' => 'The marks total cannot be lower than the marks obtained.',
            'status.in' => 'Select active, graduated, withdrawn or suspended.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Same as the store request, except that a blank password is kept as null
     * so the controller can tell "leave it alone" from "set this one". A
     * half-filled form is treated the same way: the confirmation is dropped
     * with it, so a mistyped second box cannot silently clear the password.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->trimmed('name'),
            'email' => is_string($this->input('email'))
                ? mb_strtolower(trim((string) $this->input('email')))
                : $this->input('email'),
            'phone' => $this->trimmed('phone'),
            'father_name' => $this->trimmed('father_name'),
            'roll_number' => $this->trimmed('roll_number'),
            'cnic_bform' => $this->trimmed('cnic_bform'),
            'gender' => $this->trimmed('gender'),
            'guardian_phone' => $this->trimmed('guardian_phone'),
            'address' => $this->trimmed('address'),
            'previous_school' => $this->trimmed('previous_school'),
            'previous_marks_obtained' => $this->blankToNull('previous_marks_obtained'),
            'previous_marks_total' => $this->blankToNull('previous_marks_total'),
            'password' => $this->filled('password') ? $this->input('password') : null,
            'password_confirmation' => $this->filled('password') ? $this->input('password_confirmation') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * A trimmed string, or null when nothing was submitted.
     */
    private function trimmed(string $key): ?string
    {
        return $this->filled($key) ? trim((string) $this->input($key)) : null;
    }

    /**
     * The submitted value, or null when the field was left blank.
     */
    private function blankToNull(string $key): mixed
    {
        return $this->filled($key) ? $this->input($key) : null;
    }
}
