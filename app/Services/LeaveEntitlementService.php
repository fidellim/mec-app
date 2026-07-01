<?php

namespace App\Services;

use App\Models\LeaveEntitlement;
use App\Models\LeavePlan;
use App\Models\LeaveSetting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class LeaveEntitlementService
{
    public const ANNUAL_LEAVE_CODE = 'L100';
    public const SICK_LEAVE_CODE = 'L110';
    public const MATERNITY_LEAVE_CODE = 'L160';
    public const PARENTAL_LEAVE_CODE = 'L170';
    public const BEREAVEMENT_COMPASSIONATE_LEAVE_CODE = 'L180';
    public const SERVICE_INCENTIVE_LEAVE_CODE = 'L190';

    public const ENTITLED_LEAVE_CODES = [
        self::ANNUAL_LEAVE_CODE,
        self::SICK_LEAVE_CODE,
        self::MATERNITY_LEAVE_CODE,
        self::PARENTAL_LEAVE_CODE,
        self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE,
        self::SERVICE_INCENTIVE_LEAVE_CODE,
    ];

    public const COUNTED_STATUSES = [
        LeavePlan::STATUS_SUBMITTED,
        LeavePlan::STATUS_APPROVED,
        LeavePlan::STATUS_CANCELLATION_REQUESTED,
    ];

    private const UAE_PAY_BANDS = [
        self::SICK_LEAVE_CODE => [
            ['key' => 'full_pay', 'label' => 'Full pay', 'days' => 15.0],
            ['key' => 'half_pay', 'label' => 'Half pay', 'days' => 30.0],
            ['key' => 'unpaid', 'label' => 'Unpaid', 'days' => 45.0],
        ],
        self::MATERNITY_LEAVE_CODE => [
            ['key' => 'full_pay', 'label' => 'Full pay', 'days' => 45.0],
            ['key' => 'half_pay', 'label' => 'Half pay', 'days' => 15.0],
        ],
    ];

    public function __construct(private readonly HolidayService $holidays)
    {
    }

    public function allowanceFor(User $user, int $year, string $attendanceCode = self::ANNUAL_LEAVE_CODE): float
    {
        return $this->claimableAllowanceFor($user, $year, $attendanceCode);
    }

    public function claimableAllowanceFor(User $user, int $year, string $attendanceCode = self::ANNUAL_LEAVE_CODE): float
    {
        $entitlement = $this->entitlementFor($user, $year, $attendanceCode);

        return $entitlement
            ? (float) $entitlement->claimable_allowance_days
            : $this->defaultClaimableAllowanceFor($user, $attendanceCode);
    }

    public function visibleAllowanceFor(User $user, int $year, string $attendanceCode = self::ANNUAL_LEAVE_CODE): float
    {
        $entitlement = $this->entitlementFor($user, $year, $attendanceCode);

        return $entitlement
            ? (float) $entitlement->allowance_days
            : $this->visibleAllowanceFromClaimable($user, $attendanceCode, $this->defaultClaimableAllowanceFor($user, $attendanceCode));
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
        $entitlement = $this->entitlementFor($user, $year, $attendanceCode);
        $allowance = (float) ($entitlement?->allowance_days ?? $this->visibleAllowanceFor($user, $year, $attendanceCode));
        $claimableAllowance = (float) ($entitlement?->claimable_allowance_days ?? $this->claimableAllowanceFor($user, $year, $attendanceCode));
        $used = (float) $this->usedDaysByYear($user, $attendanceCode, $excludeLeavePlanId)->get($year, 0.0);
        $isVisibleFullPayAllowance = $claimableAllowance > $allowance;
        $usesOverride = $entitlement?->source === LeaveEntitlement::SOURCE_USER_OVERRIDE;
        $payBands = $this->visibleSupplementalPayBandsFor($user, $attendanceCode, $used);

        return [
            'year' => $year,
            'attendance_code' => $attendanceCode,
            'label' => $this->entitlementLabel($attendanceCode),
            'allowance' => $allowance,
            'claimable_allowance' => $claimableAllowance,
            'used' => $used,
            'remaining' => max(0.0, $allowance - $used),
            'claimable_remaining' => max(0.0, $claimableAllowance - $used),
            'allowance_label' => $isVisibleFullPayAllowance ? 'Full-pay allowance' : 'Allowance',
            'remaining_label' => $isVisibleFullPayAllowance ? 'Full-pay remaining' : 'Remaining',
            'description' => $isVisibleFullPayAllowance
                ? 'Additional policy days may become available after the full-pay allowance is used.'
                : null,
            'pay_bands' => $payBands,
            'uses_override' => $usesOverride,
            'source' => $entitlement?->source ?? LeaveEntitlement::SOURCE_REGIONAL_DEFAULT,
            'source_label' => $usesOverride ? 'Current-year override' : 'Regional default',
            'region' => $entitlement?->region ?? $this->regionFor($user),
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
                    'claimable_allowance' => $this->formatDays($balance['claimable_allowance']),
                    'used' => $this->formatDays($balance['used']),
                    'remaining' => $this->formatDays($balance['remaining']),
                    'claimable_remaining' => $this->formatDays($balance['claimable_remaining']),
                ];
                $balance['pay_bands'] = collect($balance['pay_bands'])
                    ->map(fn (array $band) => $band + [
                        'formatted_days' => $this->formatDays((float) $band['days']),
                    ])
                    ->all();

                return [$attendanceCode => $balance];
            })
            ->all();
    }

    public function eligibleEntitledLeaveCodesFor(User $user): array
    {
        return collect(self::ENTITLED_LEAVE_CODES)
            ->reject(fn (string $attendanceCode) => $attendanceCode === self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE
                && $this->regionFor($user) === 'uae')
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

    public function ensureEntitlementsFor(User $user, int $year): Collection
    {
        $eligibleCodes = $this->eligibleEntitledLeaveCodesFor($user);
        $existing = LeaveEntitlement::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->whereIn('attendance_code', $eligibleCodes)
            ->get()
            ->keyBy('attendance_code');

        foreach ($eligibleCodes as $attendanceCode) {
            if ($existing->has($attendanceCode)) {
                continue;
            }

            $existing[$attendanceCode] = LeaveEntitlement::create($this->entitlementAttributesFor($user, $year, $attendanceCode));
        }

        return $existing
            ->filter(fn (LeaveEntitlement $entitlement) => in_array($entitlement->attendance_code, $eligibleCodes, true))
            ->values();
    }

    public function syncCurrentYearAnnualOverride(User $user): LeaveEntitlement
    {
        $year = (int) now()->year;
        $entitlement = $this->entitlementFor($user, $year, self::ANNUAL_LEAVE_CODE);
        $attributes = $this->entitlementAttributesFor($user, $year, self::ANNUAL_LEAVE_CODE);
        unset($attributes['created_by']);

        $entitlement->update($attributes + ['updated_by' => Auth::id()]);

        return $entitlement->fresh();
    }

    public function userIsEligibleFor(User $user, string $attendanceCode): bool
    {
        return match ($attendanceCode) {
            self::MATERNITY_LEAVE_CODE => $user->gender === 'female',
            self::PARENTAL_LEAVE_CODE => (bool) $user->eligible_for_parental_leave,
            self::SERVICE_INCENTIVE_LEAVE_CODE => $this->regionFor($user) === 'ph',
            default => true,
        };
    }

    public function eligibilityMessage(string $attendanceCode): ?string
    {
        return match ($attendanceCode) {
            self::MATERNITY_LEAVE_CODE => 'Maternity leave is available only for employees whose gender is set to Female.',
            self::PARENTAL_LEAVE_CODE => 'Parental leave requires HR eligibility approval. Contact HR or an admin if you need to apply.',
            self::SERVICE_INCENTIVE_LEAVE_CODE => 'Service incentive leave is available only for Philippines employees.',
            default => null,
        };
    }

    public function submissionViolations(User $user, array $attributes, ?int $excludeLeavePlanId = null): array
    {
        $attendanceCode = $attributes['attendance_code'] ?? null;

        if ($attendanceCode === self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE && $this->regionFor($user) === 'uae') {
            return [];
        }

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
                $allowance = $this->claimableAllowanceFor($user, $year, $attendanceCode);
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

    public function bereavementSubmissionViolation(User $user, array $attributes): ?array
    {
        if (($attributes['attendance_code'] ?? null) !== self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE
            || $this->regionFor($user) !== 'uae') {
            return null;
        }

        $relationship = $attributes['bereavement_relationship'] ?? null;
        $limit = is_string($relationship) ? $this->bereavementRelationshipLimit($relationship) : null;

        if ($limit === null) {
            return null;
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

        $requested = $this->countedLeaveDayCountForPlan($leavePlan);

        if ($requested <= $limit) {
            return null;
        }

        return [
            'relationship' => $relationship,
            'relationship_label' => LeavePlan::bereavementRelationshipOptions()[$relationship] ?? 'Bereavement',
            'limit' => $limit,
            'requested' => $requested,
        ];
    }

    public function annualDaysByYearForPlan(LeavePlan $leavePlan): Collection
    {
        return $this->daysByYearForPlan($leavePlan);
    }

    public function daysByYearForPlan(LeavePlan $leavePlan): Collection
    {
        $countedDates = $this->countedLeaveDatesForPlan($leavePlan);

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

    public function countedLeaveDatesForPlan(LeavePlan $leavePlan): Collection
    {
        if ($this->usesCalendarDays($leavePlan)) {
            $user = $leavePlan->relationLoaded('user')
                ? $leavePlan->user
                : User::find($leavePlan->user_id);
            $holidayDates = $this->holidays->holidayDatesForUser($user, $leavePlan->start_date, $leavePlan->end_date);

            return collect(CarbonPeriod::create($leavePlan->start_date, $leavePlan->end_date))
                ->reject(fn ($date) => $holidayDates->contains($date->toDateString()))
                ->map(fn ($date) => $date->toDateString())
                ->values();
        }

        return $this->holidays->countedLeaveDates($leavePlan);
    }

    public function countedLeaveDayCountForPlan(LeavePlan $leavePlan): float
    {
        if ($leavePlan->duration_type === 'half_day') {
            $startDate = $leavePlan->start_date instanceof CarbonInterface
                ? $leavePlan->start_date
                : Carbon::parse($leavePlan->start_date);

            return $this->countedLeaveDatesForPlan($leavePlan)->contains($startDate->toDateString()) ? 0.5 : 0.0;
        }

        return (float) $this->countedLeaveDatesForPlan($leavePlan)->count();
    }

    public function countBasisLabelForPlan(LeavePlan $leavePlan): string
    {
        return $this->usesCalendarDays($leavePlan) ? 'calendar day' : 'counted leave day';
    }

    public function payBreakdownForPlan(LeavePlan $leavePlan, ?int $excludeLeavePlanId = null): array
    {
        $user = $leavePlan->relationLoaded('user')
            ? $leavePlan->user
            : User::find($leavePlan->user_id);

        if (! $user || $this->regionFor($user) !== 'uae' || ! isset(self::UAE_PAY_BANDS[$leavePlan->attendance_code])) {
            return [];
        }

        $excludeLeavePlanId ??= $leavePlan->exists ? $leavePlan->id : null;
        $requestedByYear = $this->daysByYearForPlan($leavePlan);
        $usedByYear = $this->usedDaysByYear($user, $leavePlan->attendance_code, $excludeLeavePlanId);

        return $requestedByYear
            ->map(fn (float $requested, int $year) => $this->payBreakdownForYear(
                attendanceCode: $leavePlan->attendance_code,
                year: $year,
                previouslyUsed: (float) $usedByYear->get($year, 0.0),
                requested: $requested,
            ))
            ->filter()
            ->values()
            ->all();
    }

    public function violationMessage(array $violation): string
    {
        return $violation['label'].' limit exceeded for '.$violation['year'].'. Allowance: '
            .$this->formatDays((float) $violation['allowance'])
            .' days, used: '.$this->formatDays((float) $violation['used'])
            .' days, requested: '.$this->formatDays((float) $violation['requested'])
            .' days, remaining: '.$this->formatDays((float) $violation['remaining']).' days.';
    }

    public function bereavementViolationMessage(array $violation): string
    {
        return 'Bereavement / compassionate leave for '.$violation['relationship_label']
            .' is limited to '.$this->formatDays((float) $violation['limit'])
            .' days per request. Requested: '.$this->formatDays((float) $violation['requested']).' days.';
    }

    private function addPlanDaysToYearTotals(Collection $totals, LeavePlan $leavePlan): Collection
    {
        $this->daysByYearForPlan($leavePlan)->each(function (float $days, int $year) use ($totals) {
            $totals[$year] = (float) $totals->get($year, 0.0) + $days;
        });

        return $totals;
    }

    private function entitlementFor(User $user, int $year, string $attendanceCode): ?LeaveEntitlement
    {
        if (! $this->userIsEligibleFor($user, $attendanceCode)) {
            return null;
        }

        return $this->ensureEntitlementsFor($user, $year)
            ->firstWhere('attendance_code', $attendanceCode);
    }

    private function entitlementAttributesFor(User $user, int $year, string $attendanceCode): array
    {
        $region = $this->regionFor($user);
        $settingKey = $this->settingKeyFor($user, $attendanceCode);
        $claimableAllowance = $this->defaultClaimableAllowanceFor($user, $attendanceCode);
        $usesOverride = $attendanceCode === self::ANNUAL_LEAVE_CODE
            && $year === (int) now()->year
            && $user->annual_leave_allowance_days !== null;

        if ($usesOverride) {
            $claimableAllowance = (float) $user->annual_leave_allowance_days;
        }

        return [
            'user_id' => $user->id,
            'year' => $year,
            'attendance_code' => $attendanceCode,
            'allowance_days' => $this->visibleAllowanceFromClaimable($user, $attendanceCode, $claimableAllowance),
            'claimable_allowance_days' => $claimableAllowance,
            'source' => $usesOverride ? LeaveEntitlement::SOURCE_USER_OVERRIDE : LeaveEntitlement::SOURCE_REGIONAL_DEFAULT,
            'region' => $region,
            'setting_key' => $settingKey,
            'notes' => $usesOverride ? 'Current-year annual leave override from user profile.' : null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ];
    }

    private function defaultClaimableAllowanceFor(User $user, string $attendanceCode): float
    {
        return LeaveSetting::decimalValue(
            $this->settingKeyFor($user, $attendanceCode),
            $this->fallbackAllowanceFor($user, $attendanceCode),
        );
    }

    private function visibleAllowanceFromClaimable(User $user, string $attendanceCode, float $claimableAllowance): float
    {
        if ($this->regionFor($user) === 'uae' && isset(self::UAE_PAY_BANDS[$attendanceCode])) {
            $fullPayDays = (float) collect(self::UAE_PAY_BANDS[$attendanceCode])
                ->firstWhere('key', 'full_pay')['days'];

            return min($fullPayDays, $claimableAllowance);
        }

        return $claimableAllowance;
    }

    private function visibleSupplementalPayBandsFor(User $user, string $attendanceCode, float $used): array
    {
        if ($this->regionFor($user) !== 'uae' || ! isset(self::UAE_PAY_BANDS[$attendanceCode])) {
            return [];
        }

        $cursor = 0.0;

        return collect(self::UAE_PAY_BANDS[$attendanceCode])
            ->map(function (array $band) use (&$cursor) {
                $band['threshold'] = $cursor;
                $cursor += (float) $band['days'];

                return $band;
            })
            ->reject(fn (array $band) => $band['key'] === 'full_pay')
            ->filter(fn (array $band) => $used >= (float) $band['threshold'])
            ->map(fn (array $band) => [
                'key' => $band['key'],
                'label' => $band['label'],
                'days' => (float) $band['days'],
                'threshold' => (float) $band['threshold'],
            ])
            ->values()
            ->all();
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
            self::SERVICE_INCENTIVE_LEAVE_CODE => LeaveSetting::SERVICE_INCENTIVE_LEAVE_DEFAULT_DAYS_PH,
            default => $region === 'ph'
                ? LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_PH
                : LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS_UAE,
        };
    }

    private function fallbackAllowanceFor(User $user, string $attendanceCode): float
    {
        $region = $this->regionFor($user);

        if ($attendanceCode === self::SICK_LEAVE_CODE) {
            return $region === 'ph' ? 0.0 : 90.0;
        }

        if ($attendanceCode === self::MATERNITY_LEAVE_CODE) {
            return $region === 'ph' ? 0.0 : 60.0;
        }

        if ($attendanceCode === self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE) {
            return $region === 'ph' ? 0.0 : 8.0;
        }

        if ($attendanceCode === self::PARENTAL_LEAVE_CODE) {
            return $region === 'ph' ? 0.0 : 5.0;
        }

        if ($attendanceCode === self::SERVICE_INCENTIVE_LEAVE_CODE) {
            return 5.0;
        }

        if ($region === 'ph') {
            return 0.0;
        }

        return LeaveSetting::decimalValue(LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS, 22.0);
    }

    private function bereavementRelationshipLimit(string $relationship): ?float
    {
        return match ($relationship) {
            LeavePlan::BEREAVEMENT_RELATIONSHIP_SPOUSE => LeaveSetting::decimalValue(
                LeaveSetting::BEREAVEMENT_SPOUSE_LEAVE_DAYS_UAE,
                5.0,
            ),
            LeavePlan::BEREAVEMENT_RELATIONSHIP_IMMEDIATE_FAMILY => LeaveSetting::decimalValue(
                LeaveSetting::BEREAVEMENT_IMMEDIATE_FAMILY_LEAVE_DAYS_UAE,
                3.0,
            ),
            default => null,
        };
    }

    public function regionFor(User $user): string
    {
        return is_string($user->employee_code) && str_starts_with($user->employee_code, 'MEC-PHIL-HR-')
            ? 'ph'
            : 'uae';
    }

    public function regionLabelFor(User $user): string
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
            self::SERVICE_INCENTIVE_LEAVE_CODE => 'Service incentive leave',
            default => 'Annual leave',
        };
    }

    private function usesCalendarDays(LeavePlan $leavePlan): bool
    {
        $user = $leavePlan->relationLoaded('user')
            ? $leavePlan->user
            : User::find($leavePlan->user_id);

        return $user
            && $this->regionFor($user) === 'uae'
            && in_array($leavePlan->attendance_code, [self::SICK_LEAVE_CODE, self::MATERNITY_LEAVE_CODE], true);
    }

    private function payBreakdownForYear(string $attendanceCode, int $year, float $previouslyUsed, float $requested): array
    {
        $remainingRequest = $requested;
        $cursor = 0.0;
        $bands = [];

        foreach (self::UAE_PAY_BANDS[$attendanceCode] as $band) {
            $bandStart = $cursor;
            $bandEnd = $cursor + $band['days'];
            $availableInBand = max(0.0, $bandEnd - max($previouslyUsed, $bandStart));
            $allocated = min($remainingRequest, $availableInBand);

            $bands[] = [
                'key' => $band['key'],
                'label' => $band['label'],
                'days' => $allocated,
                'formatted_days' => $this->formatDays($allocated),
            ];

            $remainingRequest -= $allocated;
            $cursor = $bandEnd;
        }

        if ($remainingRequest > 0) {
            $bands[] = [
                'key' => 'outside_policy',
                'label' => 'Outside configured allowance',
                'days' => $remainingRequest,
                'formatted_days' => $this->formatDays($remainingRequest),
            ];
        }

        $bands = collect($bands)->filter(fn (array $band) => $band['days'] > 0)->values()->all();

        return [
            'year' => $year,
            'attendance_code' => $attendanceCode,
            'label' => $this->entitlementLabel($attendanceCode),
            'previously_used' => $previouslyUsed,
            'requested' => $requested,
            'formatted_previously_used' => $this->formatDays($previouslyUsed),
            'formatted_requested' => $this->formatDays($requested),
            'bands' => $bands,
        ];
    }
}
