<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about-timesheets', function () {
    $this->info('Timesheet Management System Phase 1');
});
