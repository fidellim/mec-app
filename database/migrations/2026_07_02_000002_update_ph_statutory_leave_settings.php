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

        foreach ($this->settings() as $key => $attributes) {
            DB::table('leave_settings')->updateOrInsert(
                ['key' => $key],
                $attributes + [
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_settings')) {
            return;
        }

        DB::table('leave_settings')
            ->where('key', 'maternity_leave_default_days_ph')
            ->update([
                'description' => 'Default L160 maternity leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 0,
                'updated_at' => now(),
            ]);

        DB::table('leave_settings')
            ->where('key', 'parental_leave_default_days_ph')
            ->update([
                'description' => 'Default L170 parental leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 0,
                'updated_at' => now(),
            ]);

        DB::table('leave_settings')
            ->whereIn('key', [
                'paternity_leave_default_days_ph',
                'vawc_leave_default_days_ph',
                'special_women_leave_default_days_ph',
            ])
            ->delete();
    }

    private function settings(): array
    {
        return [
            'maternity_leave_default_days_ph' => [
                'name' => 'Philippines Maternity Leave Default Days',
                'description' => 'Default L160 maternity leave policy allowance for eligible Philippines employees. Qualified solo parents receive 120 days through profile eligibility.',
                'decimal_value' => 105,
            ],
            'parental_leave_default_days_ph' => [
                'name' => 'Philippines Parental Leave Default Days',
                'description' => 'Default L170 solo parent leave allowance for eligible Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 7,
            ],
            'paternity_leave_default_days_ph' => [
                'name' => 'Philippines Paternity Leave Default Days',
                'description' => 'Default L210 paternity leave allowance for eligible married male Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 7,
            ],
            'vawc_leave_default_days_ph' => [
                'name' => 'Philippines VAWC Leave Default Days',
                'description' => 'Default L220 leave for VAWC allowance for eligible Philippines employees with HR-verified certification.',
                'decimal_value' => 10,
            ],
            'special_women_leave_default_days_ph' => [
                'name' => 'Philippines Special Leave for Women Default Days',
                'description' => 'Default L230 special leave for women allowance for eligible Philippines employees following gynecological surgery.',
                'decimal_value' => 60,
            ],
        ];
    }
};
