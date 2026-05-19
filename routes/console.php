<?php

use App\Models\AutomationSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about-timesheets', function () {
    $this->info('Timesheet Management System Phase 1');
});

Schedule::command('timesheets:send-missing-reminders')
    ->mondays()
    ->at('07:00')
    ->when(fn () => AutomationSetting::enabled(AutomationSetting::TIMESHEET_MISSING_REMINDERS))
    ->withoutOverlapping(60);

Schedule::command('timesheets:create-weekly-period')
    ->mondays()
    ->at('06:30')
    ->when(fn () => AutomationSetting::enabled(AutomationSetting::TIMESHEET_PERIOD_AUTO_CREATION))
    ->withoutOverlapping(60);
