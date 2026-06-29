<?php

namespace App\Services;

use App\Models\HolidayDate;
use App\Models\HolidayEvent;
use App\Models\LeavePlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class HolidayService
{
    public function employeeRegion(?string $employeeCode): string
    {
        return match (true) {
            is_string($employeeCode) && str_starts_with($employeeCode, 'MEC-PHIL-HR-') => HolidayEvent::REGION_PH,
            is_string($employeeCode) && (
                str_starts_with($employeeCode, 'MEC-HR-')
                || str_starts_with($employeeCode, 'MCE-HR-')
            ) => HolidayEvent::REGION_UAE,
            default => 'unknown',
        };
    }

    public function applicableRegions(?User $user): array
    {
        $regions = [HolidayEvent::REGION_GLOBAL];
        $region = $this->employeeRegion($user?->employee_code);

        if (in_array($region, [HolidayEvent::REGION_UAE, HolidayEvent::REGION_PH], true)) {
            $regions[] = $region;
        }

        return $regions;
    }

    public function holidayDatesForUser(?User $user, CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        return HolidayDate::query()
            ->whereHas('event', fn ($query) => $query->where('is_active', true))
            ->whereIn('region', $this->applicableRegions($user))
            ->whereDate('holiday_date', '>=', $startDate)
            ->whereDate('holiday_date', '<=', $endDate)
            ->pluck('holiday_date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values();
    }

    public function isCountedLeaveDate(?User $user, CarbonInterface $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! $this->holidayDatesForUser($user, $date, $date)->contains($date->toDateString());
    }

    public function countedLeaveDates(LeavePlan $leavePlan): Collection
    {
        $user = $leavePlan->relationLoaded('user')
            ? $leavePlan->user
            : User::find($leavePlan->user_id);
        $holidayDates = $this->holidayDatesForUser($user, $leavePlan->start_date, $leavePlan->end_date);

        return collect(CarbonPeriod::create($leavePlan->start_date, $leavePlan->end_date))
            ->reject(fn ($date) => $date->isWeekend())
            ->reject(fn ($date) => $holidayDates->contains($date->toDateString()))
            ->map(fn ($date) => $date->toDateString())
            ->values();
    }

    public function countedLeaveDayCount(LeavePlan $leavePlan): float
    {
        if ($leavePlan->duration_type === 'half_day') {
            return $this->countedLeaveDates($leavePlan)->contains($leavePlan->start_date->toDateString()) ? 0.5 : 0.0;
        }

        return (float) $this->countedLeaveDates($leavePlan)->count();
    }
}
