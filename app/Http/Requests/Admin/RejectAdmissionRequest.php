<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectAdmissionRequest extends FormRequest
{
    /**
     * Only admins can reject an application.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for rejecting an application.
     *
     * A reason is the only field, and it is not optional: an application that
     * is turned down with nothing recorded against it is unusable, because
     * there is nobody to tell the applicant why when they phone the office.
     *
     * Review and accept have no request of their own because they take no
     * input at all.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:255'],
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
            'rejection_reason.required' => 'Give a reason for rejecting this application.',
            'rejection_reason.max' => 'The reason may not be longer than 255 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Trimmed, so a reason of nothing but spaces fails the required rule
     * instead of being stored as a blank-looking value.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'rejection_reason' => is_string($this->input('rejection_reason'))
                ? trim((string) $this->input('rejection_reason'))
                : $this->input('rejection_reason'),
        ]);
    }
}
