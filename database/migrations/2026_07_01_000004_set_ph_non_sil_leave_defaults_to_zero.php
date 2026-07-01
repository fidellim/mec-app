<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PREVIOUS_DEFAULTS = [
        'annual_leave_default_days_ph' => 5,
        'sick_leave_default_days_ph' => 5,
        'maternity_leave_default_days_ph' => 60,
        'parental_leave_default_days_ph' => 5,
        'bereavement_compassionate_leave_default_days_ph' => 8,
    ];

    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('leave_settings')) {
            DB::table('leave_settings')
                ->whereIn('key', array_keys(self::PREVIOUS_DEFAULTS))
                ->update([
                    'decimal_value' => 0,
                    'updated_at' => $now,
                ]);
        }

        if (! Schema::hasTable('leave_entitlements')) {
            return;
        }

        DB::table('leave_entitlements')
            ->where('region', 'ph')
            ->where('source', 'regional_default')
            ->whereIn('setting_key', array_keys(self::PREVIOUS_DEFAULTS))
            ->update([
                'allowance_days' => 0,
                'claimable_allowance_days' => 0,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $now = now();

        if (Schema::hasTable('leave_settings')) {
            foreach (self::PREVIOUS_DEFAULTS as $key => $days) {
                DB::table('leave_settings')
                    ->where('key', $key)
                    ->update([
                        'decimal_value' => $days,
                        'updated_at' => $now,
                    ]);
            }
        }

        if (! Schema::hasTable('leave_entitlements')) {
            return;
        }

        foreach (self::PREVIOUS_DEFAULTS as $key => $days) {
            DB::table('leave_entitlements')
                ->where('region', 'ph')
                ->where('source', 'regional_default')
                ->where('setting_key', $key)
                ->update([
                    'allowance_days' => $days,
                    'claimable_allowance_days' => $days,
                    'updated_at' => $now,
                ]);
        }
    }
};
