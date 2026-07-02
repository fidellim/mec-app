<?php

namespace App\Http\Requests;

use App\Models\LeavePlan;
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
            'bereavement_relationship' => ['nullable', Rule::in(LeavePlan::BEREAVEMENT_RELATIONSHIPS)],
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

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $entitlements = app(LeaveEntitlementService::class);
            $attendanceCode = $this->input('attendance_code');

            if (is_string($attendanceCode) && ! $entitlements->userIsEligibleFor($this->user(), $attendanceCode)) {
                $validator->errors()->add('attendance_code', $entitlements->eligibilityMessage($attendanceCode, $this->user()) ?? 'This leave type is not available for your profile.');

                return;
            }

            if (! $this->boolean('submit')) {
                return;
            }

            $leavePlan = $this->route('leavePlan');
            if ($attendanceCode === LeaveEntitlementService::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE
                && $entitlements->regionFor($this->user()) === 'uae'
                && ! $this->filled('bereavement_relationship')) {
                $validator->errors()->add('bereavement_relationship', 'Select the bereavement relationship for this leave request.');

                return;
            }

            $violations = $entitlements->submissionViolations(
                $this->user(),
                [
                    'attendance_code' => $attendanceCode,
                    'start_date' => $this->input('start_date'),
                    'end_date' => $this->input('end_date'),
                    'duration_type' => $this->input('duration_type'),
                    'half_day_period' => $this->input('half_day_period'),
                    'bereavement_relationship' => $this->input('bereavement_relationship'),
                ],
                $leavePlan?->id,
            );

            foreach ($violations as $violation) {
                $validator->errors()->add('attendance_code', $entitlements->violationMessage($violation));
            }

            $bereavementViolations = $entitlements->bereavementSubmissionViolations(
                $this->user(),
                [
                    'attendance_code' => $attendanceCode,
                    'start_date' => $this->input('start_date'),
                    'end_date' => $this->input('end_date'),
                    'duration_type' => $this->input('duration_type'),
                    'half_day_period' => $this->input('half_day_period'),
                    'bereavement_relationship' => $this->input('bereavement_relationship'),
                ],
                $leavePlan?->id,
            );

            foreach ($bereavementViolations as $violation) {
                $validator->errors()->add('bereavement_relationship', $entitlements->bereavementViolationMessage($violation));
            }
        });
    }

    public function messages(): array
    {
        return [
            'attendance_code.in' => 'Select a valid leave attendance code.',
            'bereavement_relationship.in' => 'Select a valid bereavement relationship.',
            'half_day_period.required_if' => 'Select morning or afternoon for half-day leave.',
        ];
    }
}
