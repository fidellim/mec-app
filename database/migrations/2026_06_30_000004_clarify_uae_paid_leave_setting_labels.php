<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_settings')) {
            return;
        }

        $now = now();

        DB::table('leave_settings')
            ->where('key', 'sick_leave_default_days_uae')
            ->update([
                'name' => 'UAE Sick Leave Maximum Calendar Days',
                'description' => 'Maximum yearly L110 sick leave calendar days for UAE employees. Employee-facing balances show the first 15 full-pay days; additional approved days move to 30 half-pay days and 45 unpaid days.',
                'updated_at' => $now,
            ]);

        DB::table('leave_settings')
            ->where('key', 'maternity_leave_default_days_uae')
            ->update([
                'name' => 'UAE Maternity Leave Maximum Calendar Days',
                'description' => 'Maximum L160 maternity leave calendar days for UAE employees. Employee-facing balances show the first 45 full-pay days; additional approved days move to 15 half-pay days.',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_settings')) {
            return;
        }

        DB::table('leave_settings')
            ->where('key', 'sick_leave_default_days_uae')
            ->update([
                'name' => 'UAE Sick Leave Default Days',
                'description' => 'Default yearly L110 sick leave calendar-day allowance for UAE employees. Pay is split as 15 full-pay days, 30 half-pay days, and 45 unpaid days.',
                'updated_at' => now(),
            ]);

        DB::table('leave_settings')
            ->where('key', 'maternity_leave_default_days_uae')
            ->update([
                'name' => 'UAE Maternity Leave Default Days',
                'description' => 'Default L160 maternity leave policy allowance for UAE employees. Eligibility is reviewed manually.',
                'updated_at' => now(),
            ]);
    }
};
