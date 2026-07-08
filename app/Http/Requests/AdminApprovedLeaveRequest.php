<?php

namespace App\Http\Requests;

use App\Models\LeavePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminApprovedLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdminLike() ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->whereIn('role', ['employee', 'hod'])
                ->where('is_active', true)
            )],
            'attendance_code' => ['required', Rule::in(config('timesheet.leave_attendance_codes', []))],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'duration_type' => ['required', Rule::in(['full_day', 'half_day'])],
            'half_day_period' => ['nullable', 'required_if:duration_type,half_day', Rule::in(['morning', 'afternoon'])],
            'bereavement_relationship' => ['nullable', Rule::in(LeavePlan::BEREAVEMENT_RELATIONSHIPS)],
            'approved_at' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'policy_exception_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Select an active employee or HOD.',
            'employee_id.exists' => 'Select an active employee or HOD.',
            'attendance_code.in' => 'Select a valid leave attendance code.',
            'half_day_period.required_if' => 'Select morning or afternoon for half-day leave.',
            'bereavement_relationship.in' => 'Select a valid bereavement relationship.',
            'approved_at.required' => 'Enter the original approval date.',
            'approved_at.date_format' => 'Approval date must use YYYY-MM-DD.',
        ];
    }
}
