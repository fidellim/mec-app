<?php

namespace App\Services;

use App\Models\LeavePlan;
use App\Models\LeaveSetting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LeaveEntitlementService
{
    public const ANNUAL_LEAVE_CODE = 'L100';

    public const COUNTED_STATUSES = [
        LeavePlan::STATUS_SUBMITTED,
        LeavePlan::STATUS_APPROVED,
        LeavePlan::STATUS_CANCELLATION_REQUESTED,
    ];

    public function __construct(private readonly HolidayService $holidays)
    {
    }

    public function allowanceFor(User $user, int $year): float
    {
        if ($user->annual_leave_allowance_days !== null) {
            return (float) $user->annual_leave_allowance_days;
        }

        return LeaveSetting::decimalValue(LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS, 30.0);
    }

    public function usedAnnualDaysByYear(User $user, ?int $excludeLeavePlanId = null): Collection
    {
        return LeavePlan::query()
            ->with('user')
            ->where('user_id', $user->id)
            ->where('attendance_code', self::ANNUAL_LEAVE_CODE)
            ->whereIn('status', self::COUNTED_STATUSES)
            ->when($excludeLeavePlanId, fn ($query, $id) => $query->whereKeyNot($id))
            ->get()
            ->reduce(function (Collection $totals, LeavePlan $leavePlan) {
                return $this->addPlanDaysToYearTotals($totals, $leavePlan);
            }, collect());
    }

    public function requestedAnnualDaysByYear(User $user, array $attributes): Collection
    {
        if (($attributes['attendance_code'] ?? null) !== self::ANNUAL_LEAVE_CODE) {
            return collect();
        }

        $leavePlan = new LeavePlan([
            'user_id' => $user->id,
            'attendance_code' => $attributes['attendance_code'],
            'start_date' => $attributes['start_date'],
            'end_date' => $attributes['end_date'],
            'duration_type' => $attributes['duration_type'],
            'half_day_period' => $attributes['half_day_period'] ?? null,
        ]);
        $leavePlan->setRelation('user', $user);

        return $this->annualDaysByYearForPlan($leavePlan);
    }

    public function balanceFor(User $user, int $year, ?int $excludeLeavePlanId = null): array
    {
        $allowance = $this->allowanceFor($user, $year);
        $used = (float) $this->usedAnnualDaysByYear($user, $excludeLeavePlanId)->get($year, 0.0);

        return [
            'year' => $year,
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => max(0.0, $allowance - $used),
            'uses_override' => $user->annual_leave_allowance_days !== null,
        ];
    }

    public function submissionViolations(User $user, array $attributes, ?int $excludeLeavePlanId = null): array
    {
        $requestedByYear = $this->requestedAnnualDaysByYear($user, $attributes);

        if ($requestedByYear->isEmpty()) {
            return [];
        }

        $usedByYear = $this->usedAnnualDaysByYear($user, $excludeLeavePlanId);

        return $requestedByYear
            ->map(function (float $requested, int $year) use ($user, $usedByYear) {
                $allowance = $this->allowanceFor($user, $year);
                $used = (float) $usedByYear->get($year, 0.0);
                $remaining = $allowance - $used;

                if ($requested <= $remaining) {
                    return null;
                }

                return [
                    'year' => $year,
                    'allowance' => $allowance,
                    'used' => $used,
                    'requested' => $requested,
                    'remaining' => max(0.0, $remaining),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function annualDaysByYearForPlan(LeavePlan $leavePlan): Collection
    {
        $countedDates = $this->holidays->countedLeaveDates($leavePlan);

        if ($countedDates->isEmpty()) {
            return collect();
        }

        if ($leavePlan->duration_type === 'half_day') {
            $startDate = $leavePlan->start_date instanceof CarbonInterface
                ? $leavePlan->start_date
                : Carbon::parse($leavePlan->start_date);

            return $countedDates->contains($startDate->toDateString())
                ? collect([(int) $startDate->year => 0.5])
                : collect();
        }

        return $countedDates
            ->map(fn (string $date) => (int) Carbon::parse($date)->year)
            ->countBy()
            ->map(fn (int $count) => (float) $count);
    }

    public function formatDays(float $days): string
    {
        return floor($days) === $days ? (string) (int) $days : rtrim(rtrim(number_format($days, 2), '0'), '.');
    }

    public function violationMessage(array $violation): string
    {
        return 'Annual leave limit exceeded for '.$violation['year'].'. Allowance: '
            .$this->formatDays((float) $violation['allowance'])
            .' days, used: '.$this->formatDays((float) $violation['used'])
            .' days, requested: '.$this->formatDays((float) $violation['requested'])
            .' days, remaining: '.$this->formatDays((float) $violation['remaining']).' days.';
    }

    private function addPlanDaysToYearTotals(Collection $totals, LeavePlan $leavePlan): Collection
    {
        $this->annualDaysByYearForPlan($leavePlan)->each(function (float $days, int $year) use ($totals) {
            $totals[$year] = (float) $totals->get($year, 0.0) + $days;
        });

        return $totals;
    }
}
