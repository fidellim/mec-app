<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about-timesheets', function () {
    $this->info('Timesheet Management System Phase 1');
});

Schedule::command('timesheets:send-missing-reminders')
    ->mondays()
    ->at('07:00')
    ->withoutOverlapping(60);
