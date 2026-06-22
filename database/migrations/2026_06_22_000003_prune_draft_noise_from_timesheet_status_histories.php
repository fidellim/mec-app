<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('timesheet_status_histories')
            ->whereIn('action', ['timesheet_created', 'timesheet_updated'])
            ->delete();
    }

    public function down(): void
    {
        // Historical draft-save noise cannot be safely reconstructed.
    }
};
