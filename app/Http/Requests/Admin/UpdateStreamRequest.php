<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStreamRequest extends FormRequest
{
    /**
     * Only admins can update streams.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Validation rules for updating an existing stream.
     *
     * The unique rules ignore the stream being edited and are scoped to
     * non-deleted rows so they match the partial unique indexes
     * (WHERE deleted_at IS NULL) on the table.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $streamId = $this->route('stream')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('streams', 'name')
                    ->ignore($streamId)
                    ->whereNull('deleted_at'),
            ],
            'code' => [
                'required',
                'string',
                'max:10',
                'uppercase',
                Rule::unique('streams', 'code')
                    ->ignore($streamId)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
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
            'name.unique' => 'A stream with this name already exists.',
            'code.unique' => 'A stream with this code already exists.',
            'code.uppercase' => 'The code must be uppercase letters (e.g., PM, ICS).',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Codes are normalised to uppercase and the checkbox is cast to a
     * real boolean before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code'))
                ? strtoupper(trim($this->input('code')))
                : $this->input('code'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
