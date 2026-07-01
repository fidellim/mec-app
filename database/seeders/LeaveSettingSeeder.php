<?php

namespace Database\Seeders;

use App\Models\LeaveSetting;
use Illuminate\Database\Seeder;

class LeaveSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Annual Leave Default Days',
                'description' => 'Default yearly L100 annual leave allowance for UAE employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 22,
            ],
            LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Annual Leave Default Days',
                'description' => 'Default yearly L100 annual leave allowance for Philippines employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 0,
            ],
            LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Sick Leave Maximum Calendar Days',
                'description' => 'Maximum yearly L110 sick leave calendar days for UAE employees. Employee-facing balances show the first 15 full-pay days; additional approved days move to 30 half-pay days and 45 unpaid days.',
                'decimal_value' => 90,
            ],
            LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Sick Leave Default Days',
                'description' => 'Default yearly L110 sick leave allowance for Philippines employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 0,
            ],
            LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Maternity Leave Maximum Calendar Days',
                'description' => 'Maximum L160 maternity leave calendar days for UAE employees. Employee-facing balances show the first 45 full-pay days; additional approved days move to 15 half-pay days.',
                'decimal_value' => 60,
            ],
            LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Maternity Leave Default Days',
                'description' => 'Default L160 maternity leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 0,
            ],
            LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Parental Leave Default Days',
                'description' => 'Default L170 parental leave policy allowance for UAE employees. Eligibility is reviewed manually.',
                'decimal_value' => 5,
            ],
            LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Parental Leave Default Days',
                'description' => 'Default L170 parental leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 0,
            ],
            LeaveSetting::BEREAVEMENT_COMPASSIONATE_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Bereavement / Compassionate Leave Default Days',
                'description' => 'Legacy yearly L180 bereavement / compassionate allowance for UAE employees. UAE submission validation uses the spouse and immediate-family per-request limits below.',
                'decimal_value' => 8,
            ],
            LeaveSetting::BEREAVEMENT_SPOUSE_LEAVE_DAYS_UAE => [
                'name' => 'UAE Bereavement Leave - Spouse Death Days',
                'description' => 'Maximum L180 bereavement / compassionate leave days per UAE spouse-death request.',
                'decimal_value' => 5,
            ],
            LeaveSetting::BEREAVEMENT_IMMEDIATE_FAMILY_LEAVE_DAYS_UAE => [
                'name' => 'UAE Bereavement Leave - Immediate Family Death Days',
                'description' => 'Maximum L180 bereavement / compassionate leave days per UAE immediate-family death request.',
                'decimal_value' => 3,
            ],
            LeaveSetting::BEREAVEMENT_COMPASSIONATE_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Bereavement / Compassionate Leave Default Days',
                'description' => 'Default L180 bereavement / compassionate leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 0,
            ],
            LeaveSetting::SERVICE_INCENTIVE_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Service Incentive Leave Default Days',
                'description' => 'Default yearly L190 service incentive leave allowance for Philippines employees only. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 5,
            ],
        ] as $key => $attributes) {
            LeaveSetting::updateOrCreate(['key' => $key], $attributes);
        }
    }
}
