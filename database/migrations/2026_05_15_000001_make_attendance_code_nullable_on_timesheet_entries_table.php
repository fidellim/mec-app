<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('timesheet_entries', function ($table) {
                $table->string('attendance_code', 10)->nullable()->default(null)->change();
            });

            return;
        }

        DB::statement('ALTER TABLE timesheet_entries MODIFY attendance_code VARCHAR(10) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::table('timesheet_entries')->whereNull('attendance_code')->update(['attendance_code' => 'O100']);
            Schema::table('timesheet_entries', function ($table) {
                $table->string('attendance_code', 10)->default('O100')->nullable(false)->change();
            });

            return;
        }

        DB::statement("UPDATE timesheet_entries SET attendance_code = 'O100' WHERE attendance_code IS NULL");
        DB::statement("ALTER TABLE timesheet_entries MODIFY attendance_code VARCHAR(10) NOT NULL DEFAULT 'O100'");
    }
};
