<?php

namespace App\Services;

use App\Mail\MissingTimesheetReminderMail;
use App\Models\TimesheetPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MissingTimesheetReminderService
{
    private const BATCH_SIZE = 25;

    public function __construct(private readonly AuditLogService $audit)
    {
    }

    public function missingEmployees(TimesheetPeriod $period, ?int $departmentId = null, ?array $employeeIds = null): Collection
    {
        return $this->missingEmployeesQuery($period, $departmentId, $employeeIds)
            ->orderBy('name')
            ->get();
    }

    public function sendForPeriod(TimesheetPeriod $period, ?int $departmentId = null, string $source = 'manual', ?array $employeeIds = null): int
    {
        $sent = 0;

        $this->missingEmployeesQuery($period, $departmentId, $employeeIds)
            ->chunkById(self::BATCH_SIZE, function (Collection $employees) use ($period, $source, &$sent) {
                foreach ($employees as $employee) {
                    if ($this->sendReminder($employee, $period, $source)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function missingEmployeesQuery(TimesheetPeriod $period, ?int $departmentId = null, ?array $employeeIds = null)
    {
        return User::with('department')
            ->where('role', 'employee')
            ->where('is_active', true)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($employeeIds, fn ($query) => $query->whereIn('id', $employeeIds))
            ->whereDoesntHave('timesheets', function ($query) use ($period) {
                $query->where('timesheet_period_id', $period->id)
                    ->whereIn('status', ['submitted', 'approved']);
            });
    }

    private function sendReminder(User $employee, TimesheetPeriod $period, string $source): bool
    {
        if (! $employee->email) {
            return false;
        }

        try {
            Mail::to($employee->email)->send(new MissingTimesheetReminderMail(
                employee: $employee,
                period: $period,
                actionUrl: route('employee.timesheets.create', ['period_id' => $period->id]),
                sourceLabel: $source === 'automatic_monday' ? config('app.name', 'Timesheet Management System') : 'your Head of Department',
            ));
        } catch (\Throwable $exception) {
            Log::warning('Missing timesheet reminder email failed.', [
                'employee_id' => $employee->id,
                'employee_email' => $employee->email,
                'period_id' => $period->id,
                'source' => $source,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        $this->audit->record('timesheet_missing_reminder_sent', $employee, null, [
            'period_id' => $period->id,
            'week_number' => $period->week_number,
            'year' => $period->year,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'source' => $source,
            'recipient_email' => $employee->email,
        ]);

        return true;
    }
}
