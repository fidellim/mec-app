<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE timesheet_entries MODIFY attendance_code VARCHAR(10) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE timesheet_entries SET attendance_code = 'O100' WHERE attendance_code IS NULL");
        DB::statement("ALTER TABLE timesheet_entries MODIFY attendance_code VARCHAR(10) NOT NULL DEFAULT 'O100'");
    }
};
