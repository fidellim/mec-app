<?php

namespace App\Services;

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

    public function build(
        Request $request,
        Builder $query,
        string $showRoute,
        bool $showEmployee,
        ?int $excludeLeavePlanId = null,
        bool $includeUrls = true,
        ?CarbonInterface $defaultMonth = null,
        ?array $allowedStatusFilters = null
    ): array
    {
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
        $countedLeaveDates = $this->countedLeaveDatesByPlan($leavePlans);

        return [
            'month' => $month,
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'weeks' => $this->weeks($month, $leavePlans, $countedLeaveDates, $showRoute, $showEmployee, $includeUrls),
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

    private function weeks(Carbon $month, Collection $leavePlans, Collection $countedLeaveDates, string $showRoute, bool $showEmployee, bool $includeUrls): Collection
    {
        $calendarStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        return collect(CarbonPeriod::create($calendarStart, $calendarEnd))
            ->map(fn (Carbon $date) => [
                'date' => $date->copy(),
                'in_month' => $date->isSameMonth($month),
                'events' => $this->eventsForDate($date, $leavePlans, $countedLeaveDates, $showRoute, $showEmployee, $includeUrls),
            ])
            ->chunk(7);
    }

    private function eventsForDate(Carbon $date, Collection $leavePlans, Collection $countedLeaveDates, string $showRoute, bool $showEmployee, bool $includeUrls): Collection
    {
        $dateString = $date->toDateString();

        return $leavePlans
            ->filter(fn (LeavePlan $leavePlan) => ($countedLeaveDates->get($leavePlan->id) ?? collect())->contains($dateString))
            ->map(fn (LeavePlan $leavePlan) => [
                'leavePlan' => $leavePlan,
                'label' => trim(($showEmployee ? $leavePlan->user?->name.' - ' : '').$leavePlan->attendance_code),
                'title' => trim(($showEmployee ? $leavePlan->user?->name.' - ' : '').$leavePlan->leaveLabel()),
                'url' => $includeUrls ? route($showRoute, $leavePlan) : null,
                'status' => $leavePlan->status,
                'duration' => $leavePlan->leaveLengthLabel(),
            ])
            ->values();
    }

    private function countedLeaveDatesByPlan(Collection $leavePlans): Collection
    {
        $holidayService = app(HolidayService::class);

        return $leavePlans->mapWithKeys(fn (LeavePlan $leavePlan) => [
            $leavePlan->id => $holidayService->countedLeaveDates($leavePlan),
        ]);
    }
}
