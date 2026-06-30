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
    public const SICK_LEAVE_CODE = 'L110';
    public const MATERNITY_LEAVE_CODE = 'L160';
    public const PARENTAL_LEAVE_CODE = 'L170';
    public const BEREAVEMENT_COMPASSIONATE_LEAVE_CODE = 'L180';

    public const ENTITLED_LEAVE_CODES = [
        self::ANNUAL_LEAVE_CODE,
        self::SICK_LEAVE_CODE,
        self::MATERNITY_LEAVE_CODE,
        self::PARENTAL_LEAVE_CODE,
        self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE,
    ];

    public const COUNTED_STATUSES = [
        LeavePlan::STATUS_SUBMITTED,
        LeavePlan::STATUS_APPROVED,
        LeavePlan::STATUS_CANCELLATION_REQUESTED,
    ];

    public function __construct(private readonly HolidayService $holidays)
    {
    }

    public function allowanceFor(User $user, int $year, string $attendanceCode = self::ANNUAL_LEAVE_CODE): float
    {
        if ($attendanceCode === self::ANNUAL_LEAVE_CODE && $user->annual_leave_allowance_days !== null) {
            return (float) $user->annual_leave_allowance_days;
        }

        return LeaveSetting::decimalValue(
            $this->settingKeyFor($user, $attendanceCode),
            $this->fallbackAllowanceFor($user, $attendanceCode),
        );
    }

    public function usedAnnualDaysByYear(User $user, ?int $excludeLeavePlanId = null): Collection
    {
        return $this->usedDaysByYear($user, self::ANNUAL_LEAVE_CODE, $excludeLeavePlanId);
    }

    public function usedDaysByYear(User $user, string $attendanceCode, ?int $excludeLeavePlanId = null): Collection
    {
        return LeavePlan::query()
            ->with('user')
            ->where('user_id', $user->id)
            ->where('attendance_code', $attendanceCode)
            ->whereIn('status', self::COUNTED_STATUSES)
            ->when($excludeLeavePlanId, fn ($query, $id) => $query->whereKeyNot($id))
            ->get()
            ->reduce(function (Collection $totals, LeavePlan $leavePlan) {
                return $this->addPlanDaysToYearTotals($totals, $leavePlan);
            }, collect());
    }

    public function requestedAnnualDaysByYear(User $user, array $attributes): Collection
    {
        return $this->requestedDaysByYear($user, $attributes, self::ANNUAL_LEAVE_CODE);
    }

    public function requestedDaysByYear(User $user, array $attributes, string $attendanceCode): Collection
    {
        if (($attributes['attendance_code'] ?? null) !== $attendanceCode) {
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

        return $this->daysByYearForPlan($leavePlan);
    }

    public function balanceFor(User $user, int $year, ?int $excludeLeavePlanId = null, string $attendanceCode = self::ANNUAL_LEAVE_CODE): array
    {
        $allowance = $this->allowanceFor($user, $year, $attendanceCode);
        $used = (float) $this->usedDaysByYear($user, $attendanceCode, $excludeLeavePlanId)->get($year, 0.0);

        return [
            'year' => $year,
            'attendance_code' => $attendanceCode,
            'label' => $this->entitlementLabel($attendanceCode),
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => max(0.0, $allowance - $used),
            'uses_override' => $attendanceCode === self::ANNUAL_LEAVE_CODE && $user->annual_leave_allowance_days !== null,
            'region' => $this->regionFor($user),
            'region_label' => $this->regionLabelFor($user),
        ];
    }

    public function visibleBalancesFor(User $user, ?int $year = null, ?int $excludeLeavePlanId = null): array
    {
        $year ??= (int) now()->year;

        return collect($this->eligibleEntitledLeaveCodesFor($user))
            ->mapWithKeys(function (string $attendanceCode) use ($user, $year, $excludeLeavePlanId) {
                $balance = $this->balanceFor($user, $year, $excludeLeavePlanId, $attendanceCode);
                $balance['formatted'] = [
                    'allowance' => $this->formatDays($balance['allowance']),
                    'used' => $this->formatDays($balance['used']),
                    'remaining' => $this->formatDays($balance['remaining']),
                ];

                return [$attendanceCode => $balance];
            })
            ->all();
    }

    public function eligibleEntitledLeaveCodesFor(User $user): array
    {
        return collect(self::ENTITLED_LEAVE_CODES)
            ->filter(fn (string $attendanceCode) => $this->userIsEligibleFor($user, $attendanceCode))
            ->values()
            ->all();
    }

    public function eligibleLeaveAttendanceCodesFor(User $user): array
    {
        return collect(config('timesheet.leave_attendance_codes', []))
            ->filter(fn (string $attendanceCode) => $this->userIsEligibleFor($user, $attendanceCode))
            ->values()
            ->all();
    }

    public function userIsEligibleFor(User $user, string $attendanceCode): bool
    {
        return match ($attendanceCode) {
            self::MATERNITY_LEAVE_CODE => $user->gender === 'female',
            self::PARENTAL_LEAVE_CODE => $user->marital_status === 'married',
            default => true,
        };
    }

    public function eligibilityMessage(string $attendanceCode): ?string
    {
        return match ($attendanceCode) {
            self::MATERNITY_LEAVE_CODE => 'Maternity leave is available only for employees whose gender is set to Female.',
            self::PARENTAL_LEAVE_CODE => 'Parental leave is available only for employees whose marital status is set to Married.',
            default => null,
        };
    }

    public function submissionViolations(User $user, array $attributes, ?int $excludeLeavePlanId = null): array
    {
        $attendanceCode = $attributes['attendance_code'] ?? null;

        if (! in_array($attendanceCode, self::ENTITLED_LEAVE_CODES, true)) {
            return [];
        }

        $requestedByYear = $this->requestedDaysByYear($user, $attributes, $attendanceCode);

        if ($requestedByYear->isEmpty()) {
            return [];
        }

        $usedByYear = $this->usedDaysByYear($user, $attendanceCode, $excludeLeavePlanId);

        return $requestedByYear
            ->map(function (float $requested, int $year) use ($user, $usedByYear, $attendanceCode) {
                $allowance = $this->allowanceFor($user, $year, $attendanceCode);
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
                    'attendance_code' => $attendanceCode,
                    'label' => $this->entitlementLabel($attendanceCode),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function annualDaysByYearForPlan(LeavePlan $leavePlan): Collection
    {
        return $this->daysByYearForPlan($leavePlan);
    }

    public function daysByYearForPlan(LeavePlan $leavePlan): Collection
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
        return $violation['label'].' limit exceeded for '.$violation['year'].'. Allowance: '
            .$this->formatDays((float) $violation['allowance'])
            .' days, used: '.$this->formatDays((float) $violation['used'])
            .' days, requested: '.$this->formatDays((float) $violation['requested'])
            .' days, remaining: '.$this->formatDays((float) $violation['remaining']).' days.';
    }

    private function addPlanDaysToYearTotals(Collection $totals, LeavePlan $leavePlan): Collection
    {
        $this->daysByYearForPlan($leavePlan)->each(function (float $days, int $year) use ($totals) {
            $totals[$year] = (float) $totals->get($year, 0.0) + $days;
        });

        return $totals;
    }

    private function settingKeyFor(User $user, string $attendanceCode): string
    {
        $region = $this->regionFor($user);

        return match ($attendanceCode) {
            self::SICK_LEAVE_CODE => $region === 'ph'
                ? LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_PH
                : LeaveSetting::SICK_LEAVE_DEFAULT_DAYS_UAE,
            self::MATERNITY_LEAVE_CODE => $region === 'ph'
                ? LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_PH
                : LeaveSetting::MATERNITY_LEAVE_DEFAULT_DAYS_UAE,
            self::PARENTAL_LEAVE_CODE => $region === 'ph'
                ? LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_PH
                : LeaveSetting::PARENTAL_LEAVE_DEFAULT_DAYS_UAE,
            self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE => $region === 'ph'
                ? LeaveSetting::BEREAVEMENT_COMPASSIONATE_LEAVE_DEFAULT_DAYS_PH
                : LeaveSetting::BEREAVEMENT_COMPASSIONATE_LEAVE_DEFAULT_DAYS_UAE,
            default => $region === 'ph'
                ? LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_PH
                : LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE,
        };
    }

    private function fallbackAllowanceFor(User $user, string $attendanceCode): float
    {
        $region = $this->regionFor($user);

        if ($attendanceCode === self::SICK_LEAVE_CODE) {
            return $region === 'ph' ? 5.0 : 15.0;
        }

        if ($attendanceCode === self::MATERNITY_LEAVE_CODE) {
            return 60.0;
        }

        if ($attendanceCode === self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE) {
            return 8.0;
        }

        if ($attendanceCode === self::PARENTAL_LEAVE_CODE) {
            return 5.0;
        }

        if ($region === 'ph') {
            return 5.0;
        }

        return LeaveSetting::decimalValue(LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS, 22.0);
    }

    private function regionFor(User $user): string
    {
        return is_string($user->employee_code) && str_starts_with($user->employee_code, 'MEC-PHIL-HR-')
            ? 'ph'
            : 'uae';
    }

    private function regionLabelFor(User $user): string
    {
        return $this->regionFor($user) === 'ph' ? 'Philippines' : 'UAE';
    }

    private function entitlementLabel(string $attendanceCode): string
    {
        return match ($attendanceCode) {
            self::SICK_LEAVE_CODE => 'Sick leave',
            self::MATERNITY_LEAVE_CODE => 'Maternity leave',
            self::PARENTAL_LEAVE_CODE => 'Parental leave',
            self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE => 'Bereavement / compassionate leave',
            default => 'Annual leave',
        };
    }
}
