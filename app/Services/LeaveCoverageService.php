<?php

namespace App\Services;

use App\Models\LeavePlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Coverage is calculated from requests, never stored as an incremental deduction. */
class LeaveCoverageService
{
    public function slots(LeavePlan $plan, ?Collection $dates = null): Collection
    {
        $dates ??= app(LeaveEntitlementService::class)->countedLeaveDatesForPlan($plan);
        $periods = $plan->duration_type === 'half_day' ? [$plan->half_day_period] : ['morning', 'afternoon'];

        return $dates->flatMap(fn ($date) => collect($periods)->map(fn ($period) => $date.'|'.$period))->unique()->values();
    }

    public function plans(User $user, ?int $exclude = null): Collection
    {
        return LeavePlan::query()->with('user')->where('user_id', $user->id)
            ->whereIn('status', LeaveEntitlementService::COUNTED_STATUSES)
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude))->get();
    }

    public function coverage(Collection $plans, ?Collection $datesByPlan = null): Collection
    {
        return $plans->flatMap(fn ($plan) => $this->slots($plan, $datesByPlan?->get($plan->id)))->unique()->values();
    }

    public function totals(Collection $slots): Collection
    {
        return $slots->countBy(fn ($slot) => (int) substr($slot, 0, 4))->map(fn ($count) => $count / 2.0);
    }

    public function breakdown(Collection $plans, int $year, ?Collection $datesByPlan = null): array
    {
        $approved = $this->coverage($plans->whereIn('status', [LeavePlan::STATUS_APPROVED, LeavePlan::STATUS_CANCELLATION_REQUESTED]), $datesByPlan);
        $all = $this->coverage($plans, $datesByPlan);

        return [
            'approved_days' => (float) $this->totals($approved)->get($year, 0),
            'pending_days' => (float) $this->totals($all->diff($approved))->get($year, 0),
        ];
    }

    public function preview(User $user, array $attributes, ?int $exclude = null, ?Collection $plans = null): array
    {
        $candidate = new LeavePlan($attributes);
        $candidate->setRelation('user', $user);
        $slots = $this->slots($candidate);
        $plans ??= $this->plans($user, $exclude);
        $sameType = $plans->where('attendance_code', $candidate->attendance_code);
        $approved = $this->coverage($sameType->whereIn('status', [LeavePlan::STATUS_APPROVED, LeavePlan::STATUS_CANCELLATION_REQUESTED]));
        $covered = $this->coverage($sameType);
        $additional = $slots->diff($covered);
        $errors = [];
        $overlaps = [];
        foreach ($plans as $plan) {
            $intersection = $slots->intersect($this->slots($plan));
            if ($intersection->isEmpty()) {
                continue;
            }
            $overlaps[] = [
                'id' => $plan->id,
                'status' => $plan->status,
                'dates_label' => $this->describeSlots($intersection),
            ];
            if ($plan->status === LeavePlan::STATUS_CANCELLATION_REQUESTED) {
                $errors[] = 'These dates have a leave cancellation awaiting approval. Please wait for the cancellation decision before submitting a new request.';
            }
            if ($plan->attendance_code !== $candidate->attendance_code) {
                $errors[] = 'These dates or half-days overlap a different leave type. Resolve the existing request before submitting this leave.';
            }
        }
        if ($candidate->attendance_code === LeaveEntitlementService::ANNUAL_LEAVE_CODE && $slots->isNotEmpty() && $additional->isEmpty()) {
            $errors[] = 'Your selected dates are already covered by existing annual leave requests. Please review those requests.';
        }

        return [
            'selected' => $slots->count() / 2,
            'approved' => $slots->intersect($approved)->count() / 2,
            'pending' => $slots->intersect($covered->diff($approved))->count() / 2,
            'additional' => $additional->count() / 2,
            'additional_dates_label' => $this->describeSlots($additional),
            'additional_by_year' => $this->totals($additional),
            'overlaps' => $overlaps,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function describeSlots(Collection $slots): string
    {
        return $slots->groupBy(fn ($slot) => substr($slot, 0, 10))
            ->map(fn ($periods, $date) => Carbon::parse($date)->format('D, j M Y')
                .' ('.($periods->count() === 2 ? 'full day' : explode('|', $periods->first())[1]).')')
            ->implode(', ');
    }
}
