<?php

namespace App\Services;

use App\Models\AnnualLeaveCarryOver;
use App\Models\User;
use Illuminate\Support\Collection;

class AnnualLeaveCarryOverService
{
    public function __construct(private readonly LeaveEntitlementService $entitlements)
    {
    }

    public function generatePendingForYear(int $fromYear, ?User $actor = null): Collection
    {
        $toYear = $fromYear + 1;
        $generated = collect();

        User::query()
            ->with('department')
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $this->entitlements->userIsEligibleFor($user, LeaveEntitlementService::ANNUAL_LEAVE_CODE))
            ->each(function (User $user) use ($fromYear, $toYear, $actor, $generated) {
                $balance = $this->entitlements->balanceFor($user, $fromYear, attendanceCode: LeaveEntitlementService::ANNUAL_LEAVE_CODE);
                $suggestedDays = max(0.0, (float) $balance['remaining']);

                if ($suggestedDays <= 0) {
                    return;
                }

                $carryOver = AnnualLeaveCarryOver::firstOrNew([
                    'user_id' => $user->id,
                    'from_year' => $fromYear,
                    'to_year' => $toYear,
                    'attendance_code' => LeaveEntitlementService::ANNUAL_LEAVE_CODE,
                ]);

                if ($carryOver->exists && $carryOver->status !== AnnualLeaveCarryOver::STATUS_PENDING) {
                    return;
                }

                $carryOver->fill([
                    'suggested_days' => $suggestedDays,
                    'approved_days' => null,
                    'status' => AnnualLeaveCarryOver::STATUS_PENDING,
                    'source' => AnnualLeaveCarryOver::SOURCE_YEAR_END_GENERATED,
                    'notes' => $carryOver->notes,
                    'generated_by' => $actor?->id,
                    'generated_at' => now(),
                    'created_by' => $carryOver->exists ? $carryOver->created_by : $actor?->id,
                    'updated_by' => $actor?->id,
                ])->save();

                $generated->push($carryOver->fresh('user.department'));
            });

        return $generated;
    }

    public function approve(AnnualLeaveCarryOver $carryOver, float $approvedDays, ?User $actor = null, ?string $notes = null): AnnualLeaveCarryOver
    {
        $carryOver->update([
            'approved_days' => $approvedDays,
            'status' => AnnualLeaveCarryOver::STATUS_APPROVED,
            'notes' => $notes ?? $carryOver->notes,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'voided_by' => null,
            'voided_at' => null,
            'void_reason' => null,
            'updated_by' => $actor?->id,
        ]);

        return $carryOver->fresh('user.department');
    }

    public function reject(AnnualLeaveCarryOver $carryOver, ?User $actor = null, ?string $notes = null): AnnualLeaveCarryOver
    {
        $carryOver->update([
            'approved_days' => null,
            'status' => AnnualLeaveCarryOver::STATUS_REJECTED,
            'notes' => $notes ?? $carryOver->notes,
            'rejected_by' => $actor?->id,
            'rejected_at' => now(),
            'updated_by' => $actor?->id,
        ]);

        return $carryOver->fresh('user.department');
    }

    public function void(AnnualLeaveCarryOver $carryOver, string $reason, ?User $actor = null): AnnualLeaveCarryOver
    {
        $carryOver->update([
            'status' => AnnualLeaveCarryOver::STATUS_VOIDED,
            'void_reason' => $reason,
            'voided_by' => $actor?->id,
            'voided_at' => now(),
            'updated_by' => $actor?->id,
        ]);

        return $carryOver->fresh('user.department');
    }
}
