<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_settings')) {
            Schema::create('leave_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('decimal_value', 6, 2)->default(0);
                $table->timestamps();
            });
        }

        foreach ([
            'annual_leave_default_days_uae' => [
                'name' => 'UAE Annual Leave Default Days',
                'description' => 'Default yearly L100 annual leave allowance for UAE employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 22,
            ],
            'annual_leave_default_days_ph' => [
                'name' => 'Philippines Annual Leave Default Days',
                'description' => 'Default yearly L100 annual leave allowance for Philippines employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 5,
            ],
            'sick_leave_default_days_uae' => [
                'name' => 'UAE Sick Leave Maximum Calendar Days',
                'description' => 'Maximum yearly L110 sick leave calendar days for UAE employees. Employee-facing balances show the first 15 full-pay days; additional approved days move to 30 half-pay days and 45 unpaid days.',
                'decimal_value' => 90,
            ],
            'sick_leave_default_days_ph' => [
                'name' => 'Philippines Sick Leave Default Days',
                'description' => 'Default yearly L110 sick leave allowance for Philippines employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 5,
            ],
            'maternity_leave_default_days_uae' => [
                'name' => 'UAE Maternity Leave Maximum Calendar Days',
                'description' => 'Maximum L160 maternity leave calendar days for UAE employees. Employee-facing balances show the first 45 full-pay days; additional approved days move to 15 half-pay days.',
                'decimal_value' => 60,
            ],
            'maternity_leave_default_days_ph' => [
                'name' => 'Philippines Maternity Leave Default Days',
                'description' => 'Default L160 maternity leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 60,
            ],
            'parental_leave_default_days_uae' => [
                'name' => 'UAE Parental Leave Default Days',
                'description' => 'Default L170 parental leave policy allowance for UAE employees. Eligibility is reviewed manually.',
                'decimal_value' => 5,
            ],
            'parental_leave_default_days_ph' => [
                'name' => 'Philippines Parental Leave Default Days',
                'description' => 'Default L170 parental leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 5,
            ],
            'bereavement_compassionate_leave_default_days_uae' => [
                'name' => 'UAE Bereavement / Compassionate Leave Default Days',
                'description' => 'Default L180 bereavement / compassionate leave policy allowance for UAE employees. Eligibility is reviewed manually.',
                'decimal_value' => 8,
            ],
            'bereavement_compassionate_leave_default_days_ph' => [
                'name' => 'Philippines Bereavement / Compassionate Leave Default Days',
                'description' => 'Default L180 bereavement / compassionate leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 8,
            ],
            'service_incentive_leave_default_days_ph' => [
                'name' => 'Philippines Service Incentive Leave Default Days',
                'description' => 'Default yearly L190 service incentive leave allowance for Philippines employees only. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 5,
            ],
        ] as $key => $attributes) {
            DB::table('leave_settings')->updateOrInsert(
                ['key' => $key],
                $attributes + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        if (! Schema::hasColumn('users', 'annual_leave_allowance_days')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('annual_leave_allowance_days', 6, 2)->nullable()->after('receives_hod_timesheet_submission_emails');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'annual_leave_allowance_days')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('annual_leave_allowance_days');
            });
        }

        Schema::dropIfExists('leave_settings');
    }
};
