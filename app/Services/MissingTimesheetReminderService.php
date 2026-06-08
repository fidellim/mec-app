<?php

namespace App\Services;

use App\Mail\MissingTimesheetReminderMail;
use App\Models\TimesheetPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MissingTimesheetReminderService
{
    private const BATCH_SIZE = 25;

    public function __construct(private readonly AuditLogService $audit)
    {
    }

    public function missingEmployees(TimesheetPeriod $period, ?int $departmentId = null, ?array $employeeIds = null, ?array $departmentIds = null, ?array $roles = null): Collection
    {
        return $this->missingEmployeesQuery($period, $departmentId, $employeeIds, $departmentIds, $roles)
            ->orderBy('name')
            ->get();
    }

    public function sendForPeriod(TimesheetPeriod $period, ?int $departmentId = null, string $source = 'manual', ?array $employeeIds = null, ?array $departmentIds = null, ?array $roles = null): int
    {
        return $this->sendForPeriodDetailed($period, $departmentId, $source, $employeeIds, $departmentIds, $roles)['sent'];
    }

    public function sendForPeriodDetailed(TimesheetPeriod $period, ?int $departmentId = null, string $source = 'manual', ?array $employeeIds = null, ?array $departmentIds = null, ?array $roles = null): array
    {
        $sent = 0;
        $skippedCooldown = 0;

        $this->missingEmployeesQuery($period, $departmentId, $employeeIds, $departmentIds, $roles)
            ->chunkById(self::BATCH_SIZE, function (Collection $employees) use ($period, $source, &$sent, &$skippedCooldown) {
                foreach ($employees as $employee) {
                    if ($this->usesManualCooldown($source) && $this->reminderCooldownUntil($employee, $period)) {
                        $skippedCooldown++;
                        continue;
                    }

                    if ($this->sendReminder($employee, $period, $source)) {
                        $sent++;
                    }
                }
            });

        return [
            'sent' => $sent,
            'skipped_cooldown' => $skippedCooldown,
        ];
    }

    public function reminderCooldownUntil(User $employee, TimesheetPeriod $period): ?Carbon
    {
        $expiresAt = Cache::get($this->cooldownKey($employee, $period));

        if (! $expiresAt) {
            return null;
        }

        $expiresAt = Carbon::parse($expiresAt);

        if ($expiresAt->isPast()) {
            Cache::forget($this->cooldownKey($employee, $period));

            return null;
        }

        return $expiresAt;
    }

    public function reminderCooldownLabel(User $employee, TimesheetPeriod $period): ?string
    {
        $expiresAt = $this->reminderCooldownUntil($employee, $period);

        if (! $expiresAt) {
            return null;
        }

        $minutes = max(1, (int) ceil(now()->diffInSeconds($expiresAt) / 60));
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours}h {$remainingMinutes}m";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$remainingMinutes}m";
    }

    private function missingEmployeesQuery(TimesheetPeriod $period, ?int $departmentId = null, ?array $employeeIds = null, ?array $departmentIds = null, ?array $roles = null)
    {
        $departmentIds = $departmentId
            ? [$departmentId]
            : (is_array($departmentIds) ? collect($departmentIds)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all() : null);
        $roles = collect($roles ?: ['employee'])->filter()->unique()->values()->all();

        return User::with('department')
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->when(is_array($departmentIds), fn ($query) => $query->whereIn('department_id', $departmentIds))
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
            Mail::to($employee->email)->queue(new MissingTimesheetReminderMail(
                employee: $employee,
                period: $period,
                actionUrl: route('employee.timesheets.create', ['period_id' => $period->id]),
                sourceLabel: $this->sourceLabel($source),
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

        if ($this->usesManualCooldown($source)) {
            $this->startReminderCooldown($employee, $period);
        }

        return true;
    }

    private function startReminderCooldown(User $employee, TimesheetPeriod $period): void
    {
        $hours = (int) config('timesheet.manual_reminder_cooldown_hours', 24);

        if ($hours <= 0) {
            return;
        }

        $expiresAt = now()->addHours($hours);

        Cache::put(
            $this->cooldownKey($employee, $period),
            $expiresAt->toIso8601String(),
            $expiresAt
        );
    }

    private function cooldownKey(User $employee, TimesheetPeriod $period): string
    {
        return "missing-timesheet-reminder:period:{$period->id}:employee:{$employee->id}";
    }

    private function usesManualCooldown(string $source): bool
    {
        return in_array($source, ['manual_hod', 'manual_admin'], true);
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'automatic_monday' => config('app.name', 'Company Portal'),
            'manual_admin' => 'your Admin team',
            default => 'your Head of Department',
        };
    }
}
