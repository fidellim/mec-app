<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\AutomationSetting;
use App\Services\AuditLogService;

class AutomationSettingController extends Controller
{
    public function index()
    {
        return view('manage.automations.index', [
            'automations' => AutomationSetting::orderBy('name')->get(),
        ]);
    }

    public function toggle(AutomationSetting $automation, AuditLogService $audit)
    {
        $old = $automation->toArray();
        $automation->update(['is_enabled' => ! $automation->is_enabled]);

        $audit->record(
            $automation->is_enabled ? 'automation_enabled' : 'automation_disabled',
            $automation,
            $old,
            $automation->fresh()->toArray(),
        );

        return redirect()
            ->route('manage.automations.index')
            ->with('success', $automation->is_enabled ? 'Automation enabled.' : 'Automation disabled.');
    }
}
