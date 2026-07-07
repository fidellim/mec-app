<?php

namespace App\Services;

use App\Models\LeavePlan;
use App\Models\LeavePlanApproverSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LeavePlanApprovalService
{
    public function initialApprovalStageFor(User $user): string
    {
        return $user->role === 'hod'
            ? LeavePlan::APPROVAL_STAGE_DIRECTOR
            : LeavePlan::APPROVAL_STAGE_HOD;
    }

    public function director(): ?User
    {
        return $this->approver(LeavePlanApproverSetting::DIRECTOR);
    }

    public function hrFor(LeavePlan $leavePlan): ?User
    {
        return match ($this->regionFor($leavePlan)) {
            'ph' => $this->approver(LeavePlanApproverSetting::HR_PH),
            'uae' => $this->approver(LeavePlanApproverSetting::HR_UAE),
            default => null,
        };
    }

    public function currentStageApprover(LeavePlan $leavePlan): ?User
    {
        return match ($leavePlan->approval_stage) {
            LeavePlan::APPROVAL_STAGE_DIRECTOR => $this->director(),
            LeavePlan::APPROVAL_STAGE_HR => $this->hrFor($leavePlan),
            default => null,
        };
    }

    public function isAssignedCurrentStageApprover(User $user, LeavePlan $leavePlan): bool
    {
        if ($leavePlan->approval_stage === LeavePlan::APPROVAL_STAGE_HOD) {
            return $user->role === 'hod'
                && $user->managedDepartmentIds()->contains((int) $leavePlan->department_id);
        }

        $approver = $this->currentStageApprover($leavePlan);

        return $approver && (int) $approver->id === (int) $user->id;
    }

    public function currentStageMissingMessage(LeavePlan $leavePlan): ?string
    {
        if (! in_array($leavePlan->status, [LeavePlan::STATUS_SUBMITTED, LeavePlan::STATUS_CANCELLATION_REQUESTED], true)) {
            return null;
        }

        if ($leavePlan->approval_stage === LeavePlan::APPROVAL_STAGE_DIRECTOR && ! $this->director()) {
            return 'Director of Engineering & Project Management approver is not configured. Super Admin must assign one before approval can continue.';
        }

        if ($leavePlan->approval_stage === LeavePlan::APPROVAL_STAGE_HR) {
            $region = $this->regionFor($leavePlan);

            if (! $region) {
                return 'Regional HR approver could not be selected because the employee number does not match UAE or Philippines prefixes.';
            }

            if (! $this->hrFor($leavePlan)) {
                return ($region === 'ph' ? 'Philippines' : 'UAE').' HR approver is not configured. Super Admin must assign one before approval can continue.';
            }
        }

        return null;
    }

    public function regionFor(LeavePlan $leavePlan): ?string
    {
        $employeeCode = $leavePlan->user?->employee_code;

        return match (true) {
            is_string($employeeCode) && str_starts_with($employeeCode, 'MEC-PHIL-HR-') => 'ph',
            is_string($employeeCode) && (
                str_starts_with($employeeCode, 'MEC-HR-')
                || str_starts_with($employeeCode, 'MCE-HR-')
            ) => 'uae',
            default => null,
        };
    }

    public function approver(string $key): ?User
    {
        try {
            $user = LeavePlanApproverSetting::with('user')
                ->where('key', $key)
                ->first()
                ?->user;

            return $user?->is_active ? $user : null;
        } catch (\Throwable $exception) {
            Log::warning('Leave plan approver setting check failed.', [
                'key' => $key,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
