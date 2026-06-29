<?php

namespace App\Http\Requests;

use App\Services\LeaveEntitlementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeavePlanSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'attendance_code' => ['required', Rule::in(config('timesheet.leave_attendance_codes', []))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_type' => ['required', Rule::in(['full_day', 'half_day'])],
            'half_day_period' => ['nullable', 'required_if:duration_type,half_day', Rule::in(['morning', 'afternoon'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('duration_type') === 'half_day'
                && $this->filled('start_date')
                && $this->filled('end_date')
                && $this->input('start_date') !== $this->input('end_date')) {
                $validator->errors()->add('end_date', 'Half-day leave must use the same start and end date.');
            }

            if ($validator->errors()->isNotEmpty() || ! $this->boolean('submit')) {
                return;
            }

            $leavePlan = $this->route('leavePlan');
            $violations = app(LeaveEntitlementService::class)->submissionViolations(
                $this->user(),
                [
                    'attendance_code' => $this->input('attendance_code'),
                    'start_date' => $this->input('start_date'),
                    'end_date' => $this->input('end_date'),
                    'duration_type' => $this->input('duration_type'),
                    'half_day_period' => $this->input('half_day_period'),
                ],
                $leavePlan?->id,
            );

            foreach ($violations as $violation) {
                $validator->errors()->add('attendance_code', app(LeaveEntitlementService::class)->violationMessage($violation));
            }
        });
    }

    public function messages(): array
    {
        return [
            'attendance_code.in' => 'Select a valid leave attendance code.',
            'half_day_period.required_if' => 'Select morning or afternoon for half-day leave.',
        ];
    }
}
