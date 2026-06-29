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
            'annualLeaveDefault' => LeaveSetting::firstOrCreate(
                ['key' => LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS],
                [
                    'name' => 'Annual Leave Default Days',
                    'description' => 'Default yearly L100 annual leave allowance. Unused days expire on December 31 and do not carry over.',
                    'decimal_value' => 30,
                ],
            ),
        ]);
    }

    public function update(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate([
            'annual_leave_default_days' => ['required', 'numeric', 'min:0', 'multiple_of:0.5'],
        ]);

        $setting = LeaveSetting::firstOrCreate(
            ['key' => LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS],
            [
                'name' => 'Annual Leave Default Days',
                'description' => 'Default yearly L100 annual leave allowance. Unused days expire on December 31 and do not carry over.',
                'decimal_value' => 30,
            ],
        );

        $old = $setting->toArray();
        $setting->update(['decimal_value' => $validated['annual_leave_default_days']]);

        $audit->record('leave_setting_updated', $setting, $old, $setting->fresh()->toArray());

        return redirect()
            ->route('manage.leave-settings.index')
            ->with('success', 'Annual leave settings updated.');
    }
}
