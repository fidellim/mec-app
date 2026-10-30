<?php

use App\Models\AutomationSetting;
use App\Services\HistoricalTimesheetImportStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('timesheets:prune-imports', function (HistoricalTimesheetImportStore $store) {
    $this->info('Removed '.$store->prune().' expired import files.');
})->purpose('Remove expired timesheet previews and abandoned temporary workbook uploads');

Schedule::command('timesheets:prune-imports')->everyTenMinutes()->withoutOverlapping();

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
