<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('timesheet_entries')
            ->whereNull('department_id')
            ->orderBy('id')
            ->chunkById(500, function ($entries) {
                $departmentsByTimesheet = DB::table('timesheets')
                    ->whereIn('id', $entries->pluck('timesheet_id')->unique())
                    ->pluck('department_id', 'id');

                $entries->groupBy('timesheet_id')->each(function ($timesheetEntries, $timesheetId) use ($departmentsByTimesheet) {
                    $departmentId = $departmentsByTimesheet->get($timesheetId);

                    if ($departmentId) {
                        DB::table('timesheet_entries')
                            ->whereIn('id', $timesheetEntries->pluck('id'))
                            ->update(['department_id' => $departmentId]);
                    }
                });
            });
    }

    public function down(): void
    {
        // Historical entry disciplines cannot be distinguished from later edits.
    }
};
