<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionApplicationRequest extends FormRequest
{
    /**
     * The form is public, so anyone may submit it.
     *
     * There is no auth check to make: an applicant has no account. What
     * protects the endpoint is that everything here is validated, and nothing
     * an applicant sends can set a status, a rank or a reviewer.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for a public application.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'applicant_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],

            // Required, unlike on a student profile, because the CNIC or
            // B-Form is what the office uses to spot an applicant who has
            // already applied - twice to the same batch, or to two at once.
            //
            // The unique check is scoped to non-deleted rows to match the
            // partial index behind it, so a withdrawn application genuinely
            // frees the number up for a fresh one. The regex keeps letters
            // out before the unique check can be fooled by them.
            'cnic_bform' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9-]+$/',
                Rule::unique('admissions', 'cnic_bform')->whereNull('deleted_at'),
            ],

            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]*$/'],
            'guardian_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]*$/'],
            'address' => ['nullable', 'string', 'max:255'],

            'previous_school' => ['nullable', 'string', 'max:150'],
            'previous_marks_obtained' => ['nullable', 'integer', 'min:0'],
            'previous_marks_total' => [
                'nullable',
                'integer',
                'min:1',
                // Obtained cannot exceed the total. Skipped when obtained is
                // absent, which is the one case with nothing to compare to.
                'gte:previous_marks_obtained',
            ],

            'stream_applied_id' => ['required', 'integer', 'exists:streams,id'],

            /*
             * The batch check is deliberately stronger than a plain exists.
             * A form that only listed open batches would still accept a
             * hand-typed id for a closed one, because nothing checked what
             * the browser had been shown. Constraining the exists to
             * admissions_open rows closes that gap: this rule is the thing
             * that enforces "admissions are closed", not the drop-down.
             *
             * whereNull on deleted_at is belt and braces - SoftDeletes already
             * hides those rows from the controller, but the rule should not
             * depend on the caller having scoped its own query correctly.
             */
            'batch_id' => [
                'required',
                'integer',
                Rule::exists('student_batches', 'id')
                    ->where('admissions_open', true)
                    ->whereNull('deleted_at'),
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
            'applicant_name.required' => 'Enter the applicant\'s full name.',
            'applicant_name.max' => 'The name may not be longer than 100 characters.',
            'father_name.max' => 'The father\'s name may not be longer than 100 characters.',
            'cnic_bform.required' => 'Enter the CNIC or B-Form number.',
            'cnic_bform.max' => 'The CNIC / B-Form number may not be longer than 20 characters.',
            'cnic_bform.regex' => 'The CNIC / B-Form number may only contain digits and dashes.',
            'cnic_bform.unique' => 'An application with this CNIC/B-Form already exists.',
            'date_of_birth.before' => 'The date of birth must be in the past.',
            'phone.regex' => 'The phone number may only contain digits, spaces and the + - ( ) characters.',
            'guardian_phone.regex' => 'The guardian phone may only contain digits, spaces and the + - ( ) characters.',
            'previous_marks_obtained.integer' => 'The marks obtained must be a whole number.',
            'previous_marks_obtained.min' => 'The marks obtained cannot be negative.',
            'previous_marks_total.integer' => 'The marks total must be a whole number.',
            'previous_marks_total.min' => 'The marks total must be at least 1.',
            'previous_marks_total.gte' => 'The marks total cannot be lower than the marks obtained.',
            'stream_applied_id.required' => 'Choose the stream you are applying for.',
            'stream_applied_id.exists' => 'The chosen stream is not available.',
            'batch_id.required' => 'Choose the batch you are applying to.',
            // The one an applicant sees when they submit against a batch
            // whose admissions closed after the page was rendered.
            'batch_id.exists' => 'Admissions are not currently open for that batch.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Everything a public visitor types is trimmed, and a blank optional
     * field becomes null rather than an empty string. The two marks fields
     * need that especially: a browser sends '' for an empty number input,
     * and '' is not a valid integer, so leaving it alone would reject a form
     * the applicant had filled in perfectly well.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'applicant_name' => $this->trimmed('applicant_name'),
            'father_name' => $this->trimmed('father_name'),
            'cnic_bform' => $this->trimmed('cnic_bform'),
            'phone' => $this->trimmed('phone'),
            'guardian_phone' => $this->trimmed('guardian_phone'),
            'address' => $this->trimmed('address'),
            'previous_school' => $this->trimmed('previous_school'),
            'previous_marks_obtained' => $this->filled('previous_marks_obtained')
                ? $this->input('previous_marks_obtained')
                : null,
            'previous_marks_total' => $this->filled('previous_marks_total')
                ? $this->input('previous_marks_total')
                : null,
        ]);
    }

    /**
     * A trimmed string, or null when nothing was submitted.
     */
    private function trimmed(string $key): ?string
    {
        return $this->filled($key) ? trim((string) $this->input($key)) : null;
    }
}
