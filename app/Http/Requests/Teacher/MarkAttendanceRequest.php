<?php

namespace App\Http\Requests\Teacher;

use App\Models\Attendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkAttendanceRequest extends FormRequest
{
    /**
     * True, deliberately.
     *
     * The role middleware already refuses anyone who is not a teacher, and
     * the controller refuses any class-subject this teacher does not own.
     * Neither of those is expressible as a field rule, so repeating it here
     * would only give the same answer twice.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The shape of one submitted register.
     *
     * The whole roster travels in one payload rather than one request per
     * student: a teacher marking thirty names should not produce thirty round
     * trips, and a half-saved register is not a state the college has any way
     * to display.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'class_subject_id' => ['required', 'integer', 'exists:class_subjects,id'],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'attendance_date' => ['required', 'date_format:Y-m-d'],

            // At least one row, or the form would report "marked for 0
            // students" and look as though it had done something.
            'records' => ['required', 'array', 'min:1'],

            'records.*.student_profile_id' => [
                'required',
                'integer',
                'exists:student_profiles,id',
            ],

            // Read from the model rather than written out here, so the four
            // statuses cannot drift apart from the CHECK constraint that
            // enforces them in the database.
            'records.*.status' => [
                'required',
                'string',
                'in:'.implode(',', Attendance::STATUSES),
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
            'records.*.status.in' => 'Status must be present, absent, late, or leave.',
        ];
    }
}
