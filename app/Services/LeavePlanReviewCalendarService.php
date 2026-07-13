<?php

namespace App\Services;

use App\Models\HolidayDate;
use App\Models\LeavePlan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeavePlanReviewCalendarService
{
    private const VISIBLE_STATUSES = [
        LeavePlan::STATUS_SUBMITTED,
        LeavePlan::STATUS_APPROVED,
        LeavePlan::STATUS_CANCELLATION_REQUESTED,
    ];

    public function build(LeavePlan $leavePlan, Builder $visibleLeavePlans): Collection
    {
        $months = collect(CarbonPeriod::create(
            $leavePlan->start_date->copy()->startOfMonth(),
            '1 month',
            $leavePlan->end_date->copy()->startOfMonth()
        ))->map(fn (Carbon $month) => $month->copy()->startOfMonth());

        $calendarStart = $months->first()->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $months->last()->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $visiblePlans = (clone $visibleLeavePlans)
            ->with(['user', 'department'])
            ->whereKeyNot($leavePlan->id)
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->whereDate('start_date', '<=', $calendarEnd)
            ->whereDate('end_date', '>=', $calendarStart)
            ->orderBy('start_date')
            ->get();

        $countingHolidays = HolidayDate::query()
            ->with('event')
            ->whereHas('event', fn ($query) => $query->where('is_active', true))
            ->whereDate('holiday_date', '>=', $calendarStart)
            ->whereDate('holiday_date', '<=', $calendarEnd)
            ->orderBy('holiday_date')
            ->get();
        $holidays = $countingHolidays
            ->whereIn('region', app(HolidayService::class)->applicableRegions($leavePlan->user))
            ->values();

        $calendarPlans = $visiblePlans
            ->push($leavePlan->loadMissing(['user', 'department']))
            ->sortBy([
                fn (LeavePlan $first, LeavePlan $second) => $first->start_date <=> $second->start_date,
                fn (LeavePlan $first, LeavePlan $second) => $first->id <=> $second->id,
            ])
            ->values();
        $countedLeaveDates = $this->countedLeaveDatesByPlan($calendarPlans, $countingHolidays);

        return $months->map(fn (Carbon $month) => [
            'month' => $month,
            'weeks' => $this->weeks($month, $calendarPlans, $countedLeaveDates, $holidays, $leavePlan),
        ]);
    }

    private function weeks(Carbon $month, Collection $leavePlans, Collection $countedLeaveDates, Collection $holidays, LeavePlan $currentLeavePlan): Collection
    {
        $calendarStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        return collect(CarbonPeriod::create($calendarStart, $calendarEnd))
            ->map(fn (Carbon $date) => [
                'date' => $date->copy(),
                'in_month' => $date->isSameMonth($month),
                'is_requested_range' => ($countedLeaveDates->get($currentLeavePlan->id) ?? collect())->contains($date->toDateString()),
                'events' => $this->eventsForDate($date, $leavePlans, $countedLeaveDates, $holidays, $currentLeavePlan),
            ])
            ->chunk(7);
    }

    private function eventsForDate(Carbon $date, Collection $leavePlans, Collection $countedLeaveDates, Collection $holidays, LeavePlan $currentLeavePlan): Collection
    {
        $dateString = $date->toDateString();

        $leaveEvents = $leavePlans
            ->filter(fn (LeavePlan $plan) => ($countedLeaveDates->get($plan->id) ?? collect())->contains($dateString))
            ->map(fn (LeavePlan $plan) => [
                'type' => 'leave',
                'is_current' => (int) $plan->id === (int) $currentLeavePlan->id,
                'employee' => $plan->user?->name ?: '-',
                'department' => $plan->department?->name ?: '-',
                'label' => ((int) $plan->id === (int) $currentLeavePlan->id ? 'This request - ' : '').($plan->user?->name ?: '-'),
                'status' => $plan->status,
                'attendance_code' => $plan->attendance_code,
                'leave_type_label' => config('timesheet.attendance_codes')[$plan->attendance_code] ?? $plan->attendance_code,
                'leave_type' => $plan->leaveLabel(),
                'duration' => $plan->leaveLengthLabel($this->countedDays($plan, $countedLeaveDates->get($plan->id, collect()))),
            ])
            ->values();

        $isClashing = $leaveEvents->count() > 1;

        $holidayEvents = $holidays
            ->filter(fn (HolidayDate $holiday) => $holiday->holiday_date->isSameDay($date))
            ->map(fn (HolidayDate $holiday) => [
                'type' => 'holiday',
                'is_current' => false,
                'employee' => null,
                'department' => $holiday->event?->regionLabel() ?? '-',
                'label' => 'Holiday - '.$holiday->event?->name,
                'status' => 'holiday',
                'attendance_code' => null,
                'leave_type_label' => null,
                'leave_type' => ($holiday->event?->regionLabel() ?? '-').' holiday',
                'duration' => $holiday->holiday_date->toDateString(),
            ]);

        return $holidayEvents
            ->concat($leaveEvents->map(function (array $event) use ($isClashing) {
                $event['is_clashing'] = $isClashing && ! $event['is_current'];

                return $event;
            }))
            ->values();
    }

    private function countedLeaveDatesByPlan(Collection $leavePlans, Collection $holidayDates): Collection
    {
        return app(LeaveEntitlementService::class)->countedLeaveDatesForPlans($leavePlans, $holidayDates);
    }

    private function countedDays(LeavePlan $leavePlan, Collection $countedDates): float
    {
        if ($leavePlan->duration_type === 'half_day') {
            return $countedDates->contains($leavePlan->start_date->toDateString()) ? 0.5 : 0.0;
        }

        return (float) $countedDates->count();
    }
}
