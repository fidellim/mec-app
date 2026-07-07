<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\LeaveSetting;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class LeaveSettingController extends Controller
{
    public function index()
    {
        return view('manage.leave-settings.index', [
            'settings' => $this->settings(),
            'settingDefinitions' => $this->settingDefinitions(),
        ]);
    }

    public function update(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate(collect($this->settingDefinitions())
            ->mapWithKeys(fn (array $attributes, string $key) => [$key => ['required', 'numeric', 'min:0', 'multiple_of:0.5']])
            ->all());

        foreach ($this->settings() as $key => $setting) {
            $old = $setting->toArray();
            $setting->update(['decimal_value' => $validated[$key]]);

            if ($old['decimal_value'] !== $setting->fresh()->decimal_value) {
                $audit->record('leave_setting_updated', $setting, $old, $setting->fresh()->toArray());
            }
        }

        return redirect()
            ->route('manage.leave-settings.index')
            ->with('success', 'Leave settings updated.');
    }

    private function settings()
    {
        return collect($this->settingDefinitions())
            ->mapWithKeys(fn (array $attributes, string $key) => [
                $key => tap(LeaveSetting::firstOrCreate(['key' => $key], $attributes), function (LeaveSetting $setting) use ($attributes) {
                    $setting->update([
                        'name' => $attributes['name'],
                        'description' => $attributes['description'],
                    ]);
                }),
            ]);
    }

    private function settingDefinitions(): array
    {
        return [
            LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Annual Leave Default Days',
                'description' => 'Default yearly L100 annual leave allowance for UAE employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 22,
            ],
            LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Sick Leave Maximum Calendar Days',
                'description' => 'Maximum yearly L110 sick leave calendar days for UAE employees. Employee-facing balances show the first 15 full-pay days; additional approved days move to 30 half-pay days and 45 unpaid days.',
                'decimal_value' => 90,
            ],
            LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Maternity Leave Maximum Calendar Days',
                'description' => 'Maximum L160 maternity leave calendar days for UAE employees. Employee-facing balances show the first 45 full-pay days; additional approved days move to 15 half-pay days.',
                'decimal_value' => 60,
            ],
            LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Maternity Leave Default Days',
                'description' => 'Default L160 maternity leave policy allowance for eligible Philippines employees. Qualified solo parents receive 120 days through profile eligibility.',
                'decimal_value' => 105,
            ],
            LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Parental Leave Default Days',
                'description' => 'Default L170 parental leave policy allowance for UAE employees. Eligibility is reviewed manually.',
                'decimal_value' => 5,
            ],
            LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Parental Leave Default Days',
                'description' => 'Default L170 solo parent leave allowance for eligible Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 7,
            ],
            LeaveSetting::PATERNITY_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Paternity Leave Default Days',
                'description' => 'Default L210 paternity leave allowance for eligible married male Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 7,
            ],
            LeaveSetting::VAWC_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines VAWC Leave Default Days',
                'description' => 'Default L220 leave for VAWC allowance for eligible Philippines employees with HR-verified certification.',
                'decimal_value' => 10,
            ],
            LeaveSetting::SPECIAL_WOMEN_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Special Leave for Women Default Days',
                'description' => 'Default L230 special leave for women allowance for eligible Philippines employees following gynecological surgery.',
                'decimal_value' => 60,
            ],
            LeaveSetting::BEREAVEMENT_SPOUSE_LEAVE_DAYS_UAE => [
                'name' => 'UAE Bereavement Leave - Spouse Death Days',
                'description' => 'Calendar-year L180 bereavement leave allowance for UAE spouse death.',
                'decimal_value' => 5,
            ],
            LeaveSetting::BEREAVEMENT_IMMEDIATE_FAMILY_LEAVE_DAYS_UAE => [
                'name' => 'UAE Bereavement Leave - Immediate Family Death Days',
                'description' => 'Calendar-year L180 bereavement leave allowance for UAE immediate-family death.',
                'decimal_value' => 3,
            ],
            LeaveSetting::SERVICE_INCENTIVE_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Service Incentive Leave Default Days',
                'description' => 'Default yearly L190 service incentive leave allowance for Philippines employees only. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 5,
            ],
        ];
    }
}
