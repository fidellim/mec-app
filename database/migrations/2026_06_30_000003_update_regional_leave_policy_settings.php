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

        DB::table('leave_settings')
            ->where('key', 'sick_leave_default_days_uae')
            ->where('decimal_value', 15)
            ->update([
                'decimal_value' => 90,
                'updated_at' => $now,
            ]);

        DB::table('leave_settings')->updateOrInsert(
            ['key' => 'service_incentive_leave_default_days_ph'],
            [
                'name' => 'Philippines Service Incentive Leave Default Days',
                'description' => 'Default yearly L190 service incentive leave allowance for Philippines employees only. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_settings')) {
            return;
        }

        DB::table('leave_settings')
            ->where('key', 'sick_leave_default_days_uae')
            ->where('decimal_value', 90)
            ->update([
                'description' => 'Default yearly L110 sick leave allowance for UAE employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 15,
                'updated_at' => now(),
            ]);

        DB::table('leave_settings')
            ->where('key', 'service_incentive_leave_default_days_ph')
            ->delete();
    }
};
