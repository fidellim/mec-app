<?php

namespace App\Http\Requests;

use App\Models\TimesheetPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TimesheetSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'timesheet_period_id' => ['required', 'exists:timesheet_periods,id'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.work_date' => ['required', 'date'],
            'entries.*.attendance_code' => ['nullable', Rule::in(array_keys(config('timesheet.attendance_codes')))],
            'entries.*.project_id' => ['nullable', 'exists:projects,id'],
            'entries.*.regular_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entries.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entries.*.remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $period = TimesheetPeriod::find($this->input('timesheet_period_id'));
                $hasHours = false;

                foreach ($this->input('entries', []) as $index => $entry) {
                    $regular = (float) ($entry['regular_hours'] ?? 0);
                    $overtime = (float) ($entry['overtime_hours'] ?? 0);
                    $hasHours = $hasHours || $regular > 0 || $overtime > 0;

                    if ($period && isset($entry['work_date']) && ($entry['work_date'] < $period->start_date->toDateString() || $entry['work_date'] > $period->end_date->toDateString())) {
                        $validator->errors()->add("entries.$index.work_date", 'Work date must be within the selected weekly period.');
                    }

                    if (($regular > 0 || $overtime > 0) && empty($entry['project_id'])) {
                        $validator->errors()->add("entries.$index.project_id", 'Project/job number is required when hours are entered.');
                    }

                    if (($regular > 0 || $overtime > 0) && empty($entry['attendance_code'])) {
                        $validator->errors()->add("entries.$index.attendance_code", 'Attendance code is required when hours are entered.');
                    }

                    // Overtime rows are valid with or without regular hours; remarks are enforced on final submission.
                    if ($this->boolean('submit') && $overtime > 0 && blank($entry['remarks'] ?? null)) {
                        $validator->errors()->add("entries.$index.remarks", 'Remarks are required for overtime before submitting for approval.');
                    }
                }

                if ($this->boolean('submit') && ! $hasHours) {
                    $validator->errors()->add('entries', 'At least one entry must have hours greater than zero before submission.');
                }
            },
        ];
    }
}
