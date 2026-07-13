<?php

namespace App\Services;

use App\Models\HolidayDate;
use App\Models\HolidayEvent;
use App\Models\LeavePlan;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LeavePlanCalendarService
{
    public const DEFAULT_STATUSES = [
        LeavePlan::STATUS_SUBMITTED,
        LeavePlan::STATUS_APPROVED,
        LeavePlan::STATUS_CANCELLATION_REQUESTED,
    ];

    public function scopeEmployeeRegionVisibility(Request $request, Builder $query): Builder
    {
        if ($request->user()?->role !== 'employee') {
            return $query;
        }

        $isPhilippinesViewer = is_string($request->user()->employee_code)
            && str_starts_with($request->user()->employee_code, 'MEC-PHIL-HR-');

        return $query->whereHas('user', function (Builder $userQuery) use ($isPhilippinesViewer) {
            if ($isPhilippinesViewer) {
                $userQuery->where('employee_code', 'like', 'MEC-PHIL-HR-%');

                return;
            }

            $userQuery->where(function (Builder $regionQuery) {
                $regionQuery
                    ->whereNull('employee_code')
                    ->orWhere('employee_code', 'not like', 'MEC-PHIL-HR-%');
            });
        });
    }

    public function build(
        Request $request,
        Builder $query,
        string $showRoute,
        bool $showEmployee,
        ?int $excludeLeavePlanId = null,
        bool $includeUrls = true,
        ?CarbonInterface $defaultMonth = null,
        ?array $allowedStatusFilters = null
    ): array {
        $month = $this->month($request->query('month'), $defaultMonth);
        $filters = $this->filters($request, $allowedStatusFilters);
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $leavePlans = (clone $query)
            ->with(['user', 'department'])
            ->when($excludeLeavePlanId, fn ($q, $id) => $q->whereKeyNot($id))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status), fn ($q) => $q->whereIn('status', self::DEFAULT_STATUSES))
            ->when($filters['attendance_code'], fn ($q, $code) => $q->where('attendance_code', $code))
            ->when($filters['employee_id'], fn ($q, $employeeId) => $q->where('user_id', $employeeId))
            ->when($filters['department_id'], fn ($q, $departmentId) => $q->where('department_id', $departmentId))
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->orderBy('start_date')
            ->get();
        $holidaysByDate = $this->holidaysByDate($request, $monthStart, $monthEnd);
        $countedLeaveDates = $this->countedLeaveDatesByPlan($leavePlans, $holidaysByDate->flatten(1));

        return [
            'month' => $month,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'weeks' => $this->weeks($month, $leavePlans, $countedLeaveDates, $holidaysByDate, $showRoute, $showEmployee, $includeUrls),
            'filters' => $filters,
            'statuses' => self::DEFAULT_STATUSES,
        ];
    }

    private function month(?string $month, ?CarbonInterface $defaultMonth = null): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        }

        if ($defaultMonth) {
            return Carbon::parse($defaultMonth)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function filters(Request $request, ?array $allowedStatusFilters = null): array
    {
        $status = $request->query('status');

        if ($allowedStatusFilters !== null && ! in_array($status, $allowedStatusFilters, true)) {
            $status = null;
        }

        return [
            'status' => $status,
            'attendance_code' => $request->query('attendance_code'),
            'employee_id' => $request->query('employee_id'),
            'department_id' => $request->query('department_id'),
        ];
    }

    private function weeks(Carbon $month, Collection $leavePlans, Collection $countedLeaveDates, Collection $holidaysByDate, string $showRoute, bool $showEmployee, bool $includeUrls): Collection
    {
        $calendarStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        return collect(CarbonPeriod::create($calendarStart, $calendarEnd))
            ->map(fn (Carbon $date) => [
                'date' => $date->copy(),
                'in_month' => $date->isSameMonth($month),
                'events' => $this->eventsForDate($date, $leavePlans, $countedLeaveDates, $holidaysByDate, $showRoute, $showEmployee, $includeUrls),
            ])
            ->chunk(7);
    }

    private function eventsForDate(Carbon $date, Collection $leavePlans, Collection $countedLeaveDates, Collection $holidaysByDate, string $showRoute, bool $showEmployee, bool $includeUrls): Collection
    {
        $dateString = $date->toDateString();

        $holidayEvents = $holidaysByDate
            ->get($dateString, collect())
            ->map(fn (HolidayDate $holidayDate) => [
                'type' => 'holiday',
                'label' => $this->holidayLabel($holidayDate),
                'title' => $this->holidayTitle($holidayDate),
                'url' => null,
                'status' => 'holiday',
                'duration' => null,
            ]);

        $leaveEvents = $leavePlans
            ->filter(fn (LeavePlan $leavePlan) => ($countedLeaveDates->get($leavePlan->id) ?? collect())->contains($dateString))
            ->map(fn (LeavePlan $leavePlan) => [
                'type' => 'leave',
                'leavePlan' => $leavePlan,
                'label' => trim(($showEmployee ? $leavePlan->user?->name.' - ' : '').$leavePlan->attendance_code),
                'attendance_code' => $leavePlan->attendance_code,
                'leave_type_label' => config('timesheet.attendance_codes')[$leavePlan->attendance_code] ?? $leavePlan->attendance_code,
                'title' => trim(($showEmployee ? $leavePlan->user?->name.' - ' : '').$leavePlan->leaveLabel()),
                'url' => $includeUrls ? route($showRoute, $leavePlan) : null,
                'status' => $leavePlan->status,
                'duration' => $leavePlan->leaveLengthLabel($this->countedDays($leavePlan, $countedLeaveDates->get($leavePlan->id, collect()))),
            ]);

        return $holidayEvents
            ->concat($leaveEvents)
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

    private function holidaysByDate(Request $request, Carbon $monthStart, Carbon $monthEnd): Collection
    {
        return HolidayDate::query()
            ->with('event')
            ->whereHas('event', fn ($query) => $query->where('is_active', true))
            ->whereIn('region', $this->holidayRegions($request))
            ->whereDate('holiday_date', '>=', $monthStart)
            ->whereDate('holiday_date', '<=', $monthEnd)
            ->orderBy('holiday_date')
            ->orderByRaw("case region when 'global' then 0 when 'uae' then 1 when 'ph' then 2 else 3 end")
            ->get()
            ->groupBy(fn (HolidayDate $holidayDate) => $holidayDate->holiday_date->toDateString());
    }

    private function holidayRegions(Request $request): array
    {
        if ($request->routeIs('employee.leave-plans.*')) {
            return app(HolidayService::class)->applicableRegions($request->user());
        }

        return array_keys(HolidayEvent::REGIONS);
    }

    private function holidayLabel(HolidayDate $holidayDate): string
    {
        return 'Holiday - '.$this->holidayRegionLabel($holidayDate).' - '.($holidayDate->event?->name ?: 'Company holiday');
    }

    private function holidayTitle(HolidayDate $holidayDate): string
    {
        $name = $holidayDate->event?->name ?: 'Company holiday';

        return $name.' ('.$this->holidayRegionLabel($holidayDate).' holiday)';
    }

    private function holidayRegionLabel(HolidayDate $holidayDate): string
    {
        return $holidayDate->event?->regionLabel()
            ?? HolidayEvent::REGIONS[$holidayDate->region]
            ?? ucfirst((string) $holidayDate->region);
    }
}
