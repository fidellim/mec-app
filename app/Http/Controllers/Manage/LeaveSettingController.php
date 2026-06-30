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
                $key => LeaveSetting::firstOrCreate(['key' => $key], $attributes),
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
            LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Annual Leave Default Days',
                'description' => 'Default yearly L100 annual leave allowance for Philippines employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 5,
            ],
            LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Sick Leave Default Days',
                'description' => 'Default yearly L110 sick leave allowance for UAE employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 15,
            ],
            LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Sick Leave Default Days',
                'description' => 'Default yearly L110 sick leave allowance for Philippines employees. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 5,
            ],
            LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Maternity Leave Default Days',
                'description' => 'Default L160 maternity leave policy allowance for UAE employees. Eligibility is reviewed manually.',
                'decimal_value' => 60,
            ],
            LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Maternity Leave Default Days',
                'description' => 'Default L160 maternity leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 60,
            ],
            LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Parental Leave Default Days',
                'description' => 'Default L170 parental leave policy allowance for UAE employees. Eligibility is reviewed manually.',
                'decimal_value' => 5,
            ],
            LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Parental Leave Default Days',
                'description' => 'Default L170 parental leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 5,
            ],
            LeaveSetting::BEREAVEMENT_COMPASSIONATE_LEAVE_DEFAULT_DAYS_UAE => [
                'name' => 'UAE Bereavement / Compassionate Leave Default Days',
                'description' => 'Default L180 bereavement / compassionate leave policy allowance for UAE employees. Eligibility is reviewed manually.',
                'decimal_value' => 5,
            ],
            LeaveSetting::BEREAVEMENT_COMPASSIONATE_LEAVE_DEFAULT_DAYS_PH => [
                'name' => 'Philippines Bereavement / Compassionate Leave Default Days',
                'description' => 'Default L180 bereavement / compassionate leave policy allowance for Philippines employees. Eligibility is reviewed manually.',
                'decimal_value' => 5,
            ],
        ];
    }
}
