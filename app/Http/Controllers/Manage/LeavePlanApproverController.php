<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\LeavePlanApproverSetting;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeavePlanApproverController extends Controller
{
    public function index()
    {
        return view('manage.leave-plan-approvers.index', [
            'settings' => LeavePlanApproverSetting::with('user')->get()->keyBy('key'),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, AuditLogService $audit)
    {
        $data = $request->validate([
            'director_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'hr_uae_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'hr_ph_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true))],
        ]);

        $old = LeavePlanApproverSetting::with('user')->get()->mapWithKeys(fn ($setting) => [
            $setting->key => $setting->user_id,
        ])->all();

        $map = [
            LeavePlanApproverSetting::DIRECTOR => $data['director_user_id'] ?? null,
            LeavePlanApproverSetting::HR_UAE => $data['hr_uae_user_id'] ?? null,
            LeavePlanApproverSetting::HR_PH => $data['hr_ph_user_id'] ?? null,
        ];

        foreach ($map as $key => $userId) {
            LeavePlanApproverSetting::updateOrCreate(
                ['key' => $key],
                ['user_id' => $userId ?: null],
            );
        }

        $new = LeavePlanApproverSetting::get()->mapWithKeys(fn ($setting) => [
            $setting->key => $setting->user_id,
        ])->all();

        $audit->record('leave_plan_approvers_updated', null, $old, $new);

        return redirect()
            ->route('manage.leave-plan-approvers.index')
            ->with('success', 'Leave plan approvers updated.');
    }
}
