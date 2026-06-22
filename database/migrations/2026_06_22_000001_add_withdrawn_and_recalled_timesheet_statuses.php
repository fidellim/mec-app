<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE timesheets MODIFY status ENUM('draft', 'submitted', 'approved', 'rejected', 'withdrawn', 'recalled', 'voided') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        DB::table('timesheets')
            ->where('status', 'withdrawn')
            ->update(['status' => 'draft']);

        DB::table('timesheets')
            ->where('status', 'recalled')
            ->update(['status' => 'rejected']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE timesheets MODIFY status ENUM('draft', 'submitted', 'approved', 'rejected', 'voided') NOT NULL DEFAULT 'draft'");
        }
    }
};
