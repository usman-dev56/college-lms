<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    /**
     * Only admins can create students.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for creating a student.
     *
     * The request spans two tables: the login half goes to users and the
     * rest to student_profiles, so the rules cover both.
     *
     * Email and phone are checked against every user row, including
     * soft-deleted ones, because the unique indexes on those columns are not
     * partial - a soft-deleted account still holds its email and phone at the
     * database level, so ignoring them here would let the form pass and then
     * fail on insert.
     *
     * cnic_bform is the opposite case: that index *is* partial on
     * (deleted_at IS NULL AND cnic_bform IS NOT NULL), so the check matches
     * exactly that and a deleted student's CNIC is genuinely free again.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Account
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

            // Batch and roll number
            'batch_id' => ['required', 'integer', 'exists:student_batches,id'],
            'roll_number' => [
                'nullable',
                'string',
                'max:20',
                // Unique within the chosen batch only, and only among live
                // rows - the same shape as the partial index behind it. The
                // batch_id is the submitted value rather than a looked-up one,
                // because the row does not exist yet.
                Rule::unique('student_profiles', 'roll_number')
                    ->where(fn ($query) => $query
                        ->where('batch_id', $this->input('batch_id'))
                        ->whereNull('deleted_at')),
            ],

            // Student details
            'father_name' => ['nullable', 'string', 'max:100'],
            'cnic_bform' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('student_profiles', 'cnic_bform')
                    ->whereNull('deleted_at'),
            ],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'guardian_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'admission_date' => ['nullable', 'date'],

            // Previous school record
            'previous_school' => ['nullable', 'string', 'max:150'],
            'previous_marks_obtained' => ['nullable', 'integer', 'min:0'],
            'previous_marks_total' => [
                'nullable',
                'integer',
                'min:1',
                // Obtained cannot exceed the total. Skipped when obtained is
                // absent, which is the one case where the comparison has
                // nothing to compare against.
                'gte:previous_marks_obtained',
            ],

            'status' => [
                'nullable',
                Rule::in(['active', 'graduated', 'withdrawn', 'suspended']),
            ],
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
            'name.required' => 'Enter the student\'s name.',
            'name.max' => 'The name may not be longer than 100 characters.',
            'email.required' => 'Enter an email address.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'phone.regex' => 'The phone number may only contain digits, spaces and the + - ( ) characters.',
            'phone.unique' => 'This phone number is already in use.',
            'password.required' => 'Enter a password.',
            'password.min' => 'The password must be at least 8 characters.',
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
     * Text is trimmed, a blank optional field becomes null rather than an
     * empty string (which would collide with the unique indexes on email,
     * phone and cnic_bform), and the checkbox becomes a real boolean. The two
     * marks fields are blanked to null for the same reason: a browser sends
     * '' for an empty input, and '' is not a valid integer.
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
