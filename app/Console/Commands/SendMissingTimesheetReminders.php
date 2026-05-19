<?php

namespace App\Console\Commands;

use App\Models\AutomationSetting;
use App\Models\TimesheetPeriod;
use App\Services\MissingTimesheetReminderService;
use Illuminate\Console\Command;

class SendMissingTimesheetReminders extends Command
{
    protected $signature = 'timesheets:send-missing-reminders
        {--period_id= : Send reminders for a specific period ID}
        {--force : Send even when the automation is disabled}';

    protected $description = 'Send email reminders to active employees missing submitted or approved timesheets.';

    public function handle(MissingTimesheetReminderService $reminders): int
    {
        if (! $this->option('force') && ! AutomationSetting::enabled(AutomationSetting::TIMESHEET_MISSING_REMINDERS)) {
            $this->info('Missing timesheet reminders automation is disabled.');

            return self::SUCCESS;
        }

        $period = $this->period();

        if (! $period) {
            $this->info('No eligible open period found for missing timesheet reminders.');
            AutomationSetting::markRan(AutomationSetting::TIMESHEET_MISSING_REMINDERS);

            return self::SUCCESS;
        }

        $sent = $reminders->sendForPeriod($period, source: 'automatic_monday');
        AutomationSetting::markRan(AutomationSetting::TIMESHEET_MISSING_REMINDERS);

        $this->info("Sent {$sent} missing timesheet reminder(s) for Week {$period->week_number}, {$period->year}.");

        return self::SUCCESS;
    }

    private function period(): ?TimesheetPeriod
    {
        if ($periodId = $this->option('period_id')) {
            return TimesheetPeriod::where('status', 'open')->find($periodId);
        }

        return TimesheetPeriod::where('status', 'open')
            ->whereDate('end_date', '<', today())
            ->latest('end_date')
            ->first();
    }
}
