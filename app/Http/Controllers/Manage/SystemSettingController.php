<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function index()
    {
        return view('manage.system-settings.index', [
            'setupMode' => SystemSetting::setupMode(),
        ]);
    }

    public function setupMode(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate([
            'setup_mode_enabled' => ['required', 'boolean'],
        ]);

        $setting = SystemSetting::setupMode();
        $old = $setting->toArray();
        $enabled = (bool) $validated['setup_mode_enabled'];

        $setting->update(['boolean_value' => $enabled]);
        $fresh = $setting->fresh();

        if ((bool) $old['boolean_value'] !== $fresh->boolean_value) {
            $audit->record(
                $fresh->boolean_value ? 'setup_mode_enabled' : 'setup_mode_disabled',
                $fresh,
                $old,
                $fresh->toArray(),
            );
        }

        return redirect()
            ->route('manage.system-settings.index')
            ->with('success', $fresh->boolean_value ? 'Setup mode enabled.' : 'Setup mode disabled.');
    }
}
