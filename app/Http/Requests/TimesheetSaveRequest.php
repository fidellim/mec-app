<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\TimesheetPeriod;
use App\Services\LeaveEntitlementService;
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

    public function attributes(): array
    {
        $attributes = [
            'timesheet_period_id' => 'weekly period',
            'entries' => 'daily entries',
        ];

        foreach ($this->input('entries', []) as $index => $entry) {
            $label = $this->entryLabel($index, $entry);

            $attributes["entries.$index.work_date"] = "$label work date";
            $attributes["entries.$index.attendance_code"] = "$label attendance code";
            $attributes["entries.$index.project_id"] = "$label project/job number";
            $attributes["entries.$index.regular_hours"] = "$label regular hours";
            $attributes["entries.$index.overtime_hours"] = "$label overtime hours";
            $attributes["entries.$index.remarks"] = "$label remarks";
        }

        return $attributes;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $period = TimesheetPeriod::find($this->input('timesheet_period_id'));
                $hasHours = false;
                $entitlements = app(LeaveEntitlementService::class);
                $timesheetAttendanceCodes = $entitlements->timesheetAttendanceCodesFor($this->user());
                $projectIds = collect($this->input('entries', []))
                    ->pluck('project_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique();
                $availableProjectIds = Project::query()
                    ->availableForTimesheetsBy($this->user())
                    ->whereKey($projectIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

                foreach ($this->input('entries', []) as $index => $entry) {
                    $entryLabel = ucfirst($this->entryLabel($index, $entry));
                    $regular = (float) ($entry['regular_hours'] ?? 0);
                    $overtime = (float) ($entry['overtime_hours'] ?? 0);
                    $attendanceCode = $entry['attendance_code'] ?? null;
                    $isAvailableTimesheetCode = is_string($attendanceCode) && in_array($attendanceCode, $timesheetAttendanceCodes, true);
                    $isLeaveCode = $isAvailableTimesheetCode && in_array($attendanceCode, config('timesheet.leave_attendance_codes', []), true);
                    $isProjectOptionalCode = $isAvailableTimesheetCode && in_array($attendanceCode, config('timesheet.project_optional_attendance_codes', config('timesheet.leave_attendance_codes', [])), true);
                    $hasHours = $hasHours || $regular > 0 || $overtime > 0;

                    if (! empty($entry['project_id']) && ! $availableProjectIds->contains((int) $entry['project_id'])) {
                        $validator->errors()->add(
                            "entries.$index.project_id",
                            'You are no longer assigned to this project, or the project is inactive. Remove or replace it before saving.',
                        );
                    }

                    if ($period && isset($entry['work_date']) && ($entry['work_date'] < $period->start_date->toDateString() || $entry['work_date'] > $period->end_date->toDateString())) {
                        $validator->errors()->add("entries.$index.work_date", 'Work date must be within the selected weekly period.');
                    }

                    if (($regular > 0 || $overtime > 0) && ! $isProjectOptionalCode && empty($entry['project_id'])) {
                        $validator->errors()->add("entries.$index.project_id", "$entryLabel needs a project/job number when hours are entered.");
                    }

                    if (($regular > 0 || $overtime > 0) && empty($attendanceCode)) {
                        $validator->errors()->add("entries.$index.attendance_code", "$entryLabel needs an attendance code when hours are entered.");
                    }

                    if (($regular > 0 || $overtime > 0) && is_string($attendanceCode) && $attendanceCode !== '' && ! $isAvailableTimesheetCode) {
                        $validator->errors()->add(
                            "entries.$index.attendance_code",
                            "$entryLabel has an attendance code that is not available for your region.",
                        );
                    }

                    if ($isLeaveCode && $overtime > 0) {
                        $validator->errors()->add("entries.$index.overtime_hours", "$entryLabel cannot have overtime hours with a leave attendance code.");
                    }
                }

                if ($this->boolean('submit') && ! $hasHours) {
                    $validator->errors()->add('entries', 'At least one entry must have hours greater than zero before submission.');
                }
            },
        ];
    }

    private function entryLabel(int $index, array $entry): string
    {
        if (! empty($entry['work_date'])) {
            return 'row for '.$entry['work_date'];
        }

        return 'row '.($index + 1);
    }
}
