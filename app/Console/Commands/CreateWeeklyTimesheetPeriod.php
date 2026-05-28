<?php

namespace App\Console\Commands;

use App\Models\AutomationSetting;
use App\Models\TimesheetPeriod;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateWeeklyTimesheetPeriod extends Command
{
    protected $signature = 'timesheets:create-weekly-period
        {--date= : Date inside the week to create, formatted as YYYY-MM-DD}
        {--force : Create or check the period even when the automation is disabled}';

    protected $description = 'Create the current Monday-to-Sunday timesheet period if it does not exist.';

    public function handle(AuditLogService $audit): int
    {
        $automation = AutomationSetting::where('key', AutomationSetting::TIMESHEET_PERIOD_AUTO_CREATION)->first();

        if (! $this->option('force') && ! AutomationSetting::enabled(AutomationSetting::TIMESHEET_PERIOD_AUTO_CREATION)) {
            $audit->record('timesheet_period_auto_creation_failed', $automation, null, [
                'reason' => 'automation_disabled',
            ]);
            $this->info('Weekly period auto creation automation is disabled.');

            return self::SUCCESS;
        }

        $start = $this->startDate();
        $end = $start->copy()->endOfWeek();
        $weekNumber = (int) $start->isoWeek();
        $year = (int) $start->isoWeekYear();

        $existingPeriod = TimesheetPeriod::where('week_number', $weekNumber)
            ->where('year', $year)
            ->first();

        if ($existingPeriod) {
            $audit->record('timesheet_period_auto_creation_succeeded', $automation, null, [
                'result' => 'skipped',
                'reason' => 'period_already_exists',
                'period_id' => $existingPeriod->id,
                'week_number' => $weekNumber,
                'year' => $year,
            ]);
            $audit->record('timesheet_period_auto_create_skipped', $existingPeriod, null, [
                'reason' => 'period_already_exists',
                'week_number' => $weekNumber,
                'year' => $year,
                'start_date' => $existingPeriod->start_date->toDateString(),
                'end_date' => $existingPeriod->end_date->toDateString(),
                'status' => $existingPeriod->status,
            ]);
            AutomationSetting::markRan(AutomationSetting::TIMESHEET_PERIOD_AUTO_CREATION);
            $this->info("Week {$weekNumber}, {$year} already exists.");

            return self::SUCCESS;
        }

        $period = TimesheetPeriod::create([
            'week_number' => $weekNumber,
            'year' => $year,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'open',
        ]);

        $audit->record('timesheet_period_auto_creation_succeeded', $automation, null, [
            'result' => 'created',
            'period_id' => $period->id,
            'week_number' => $period->week_number,
            'year' => $period->year,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
        ]);
        $audit->record('timesheet_period_auto_created', $period, null, $period->toArray());
        AutomationSetting::markRan(AutomationSetting::TIMESHEET_PERIOD_AUTO_CREATION);
        $this->info("Created Week {$weekNumber}, {$year}: {$period->start_date->toDateString()} to {$period->end_date->toDateString()}.");

        return self::SUCCESS;
    }

    private function startDate(): Carbon
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : today();

        return $date->startOfWeek();
    }
}
