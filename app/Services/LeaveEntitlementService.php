<?php

namespace App\Services;

use App\Models\LeaveEntitlement;
use App\Models\LeavePlan;
use App\Models\LeaveSetting;
use App\Models\AnnualLeaveCarryOver;
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
    public const EMERGENCY_LEAVE_CODE = 'L120';
    public const MATERNITY_LEAVE_CODE = 'L160';
    public const PARENTAL_LEAVE_CODE = 'L170';
    public const BEREAVEMENT_COMPASSIONATE_LEAVE_CODE = 'L180';
    public const SERVICE_INCENTIVE_LEAVE_CODE = 'L190';
    public const PATERNITY_LEAVE_CODE = 'L210';
    public const VAWC_LEAVE_CODE = 'L220';
    public const SPECIAL_WOMEN_LEAVE_CODE = 'L230';

    public const ENTITLED_LEAVE_CODES = [
        self::ANNUAL_LEAVE_CODE,
        self::SICK_LEAVE_CODE,
        self::MATERNITY_LEAVE_CODE,
        self::PARENTAL_LEAVE_CODE,
        self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE,
        self::SERVICE_INCENTIVE_LEAVE_CODE,
        self::PATERNITY_LEAVE_CODE,
        self::VAWC_LEAVE_CODE,
        self::SPECIAL_WOMEN_LEAVE_CODE,
    ];

    public const ONE_SHOT_PH_STATUTORY_LEAVE_FLAGS = [
        self::MATERNITY_LEAVE_CODE => 'eligible_for_maternity_leave',
        self::PATERNITY_LEAVE_CODE => 'eligible_for_paternity_leave',
        self::PARENTAL_LEAVE_CODE => 'eligible_for_parental_leave',
        self::VAWC_LEAVE_CODE => 'eligible_for_vawc_leave',
        self::SPECIAL_WOMEN_LEAVE_CODE => 'eligible_for_special_women_leave',
    ];

    public const ONE_SHOT_UAE_BEREAVEMENT_LEAVE_FLAGS = [
        LeavePlan::BEREAVEMENT_RELATIONSHIP_SPOUSE => 'eligible_for_bereavement_spouse_leave',
        LeavePlan::BEREAVEMENT_RELATIONSHIP_IMMEDIATE_FAMILY => 'eligible_for_bereavement_immediate_family_leave',
    ];

    public const COUNTED_STATUSES = [
        LeavePlan::STATUS_SUBMITTED,
        LeavePlan::STATUS_APPROVED,
        LeavePlan::STATUS_CANCELLATION_REQUESTED,
    ];

    public const PH_UNAVAILABLE_LEAVE_CODES = [
        self::ANNUAL_LEAVE_CODE,
        self::SICK_LEAVE_CODE,
        self::EMERGENCY_LEAVE_CODE,
        self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE,
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

    public function allowanceFor(User $user, int $year, string $attendanceCode = self::ANNUAL_LEAVE_CODE, CarbonInterface|string|null $asOf = null): float
    {
        return $this->claimableAllowanceFor($user, $year, $attendanceCode, $asOf);
    }

    public function claimableAllowanceFor(User $user, int $year, string $attendanceCode = self::ANNUAL_LEAVE_CODE, CarbonInterface|string|null $asOf = null): float
    {
        $entitlement = $this->entitlementFor($user, $year, $attendanceCode);
        $carryOver = $this->approvedCarryOverDaysFor($user, $year, $attendanceCode);

        if ($this->usesUaeAnnualServiceAllowance($user, $year, $attendanceCode, $entitlement)) {
            return $this->uaeAnnualAllowanceForService($user, $this->asOfDate($asOf)) + $carryOver;
        }

        $allowance = $entitlement
            ? (float) $entitlement->claimable_allowance_days
            : $this->defaultClaimableAllowanceFor($user, $attendanceCode, $asOf);

        return $allowance + $carryOver;
    }

    public function visibleAllowanceFor(User $user, int $year, string $attendanceCode = self::ANNUAL_LEAVE_CODE, CarbonInterface|string|null $asOf = null): float
    {
        $entitlement = $this->entitlementFor($user, $year, $attendanceCode);
        $carryOver = $this->approvedCarryOverDaysFor($user, $year, $attendanceCode);

        if ($this->usesUaeAnnualServiceAllowance($user, $year, $attendanceCode, $entitlement)) {
            return $this->uaeAnnualAllowanceForService($user, $this->asOfDate($asOf)) + $carryOver;
        }

        $allowance = $entitlement
            ? (float) $entitlement->allowance_days
            : $this->visibleAllowanceFromClaimable($user, $attendanceCode, $this->defaultClaimableAllowanceFor($user, $attendanceCode, $asOf));

        return $allowance + $carryOver;
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

    public function balanceFor(User $user, int $year, ?int $excludeLeavePlanId = null, string $attendanceCode = self::ANNUAL_LEAVE_CODE, CarbonInterface|string|null $asOf = null): array
    {
        $entitlement = $this->entitlementFor($user, $year, $attendanceCode);
        $baseAllowance = (float) $this->baseVisibleAllowanceFor($user, $year, $attendanceCode, $asOf, $entitlement);
        $baseClaimableAllowance = (float) $this->baseClaimableAllowanceFor($user, $year, $attendanceCode, $asOf, $entitlement);
        $carryOver = (float) $this->approvedCarryOverDaysFor($user, $year, $attendanceCode);
        $allowance = $baseAllowance + $carryOver;
        $claimableAllowance = $baseClaimableAllowance + $carryOver;
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
            'base_allowance' => $baseAllowance,
            'base_claimable_allowance' => $baseClaimableAllowance,
            'carry_over' => $carryOver,
            'used' => $used,
            'remaining' => max(0.0, $allowance - $used),
            'claimable_remaining' => max(0.0, $claimableAllowance - $used),
            'allowance_label' => $isVisibleFullPayAllowance ? 'Full-pay allowance' : 'Allowance',
            'remaining_label' => $isVisibleFullPayAllowance ? 'Full-pay remaining' : 'Remaining',
            'description' => $this->balanceDescription($user, $attendanceCode),
            'pay_bands' => $payBands,
            'uses_override' => $usesOverride,
            'source' => $entitlement?->source ?? LeaveEntitlement::SOURCE_REGIONAL_DEFAULT,
            'source_label' => $usesOverride ? 'Current-year override' : 'Regional default',
            'region' => $entitlement?->region ?? $this->regionFor($user),
            'region_label' => $this->regionLabelFor($user),
        ];
    }

    public function visibleBalancesFor(User $user, ?int $year = null, ?int $excludeLeavePlanId = null, CarbonInterface|string|null $asOf = null, ?User $viewer = null): array
    {
        $year ??= (int) now()->year;

        $balances = collect($this->eligibleEntitledLeaveCodesFor($user))
            ->intersect($this->visibleEntitledLeaveCodesFor($user, $viewer))
            ->mapWithKeys(function (string $attendanceCode) use ($user, $year, $excludeLeavePlanId, $asOf) {
                $balance = $this->formatBalance($this->balanceFor($user, $year, $excludeLeavePlanId, $attendanceCode, $asOf));

                return [$attendanceCode => $balance];
            });

        if ($this->viewerCanSeeAllEntitlements($viewer) && $this->regionFor($user) === 'uae') {
            $balances = $balances->merge($this->visibleBereavementBalancesFor($user, $year, $excludeLeavePlanId));
        }

        return $balances->all();
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

    private function visibleEntitledLeaveCodesFor(User $user, ?User $viewer = null): array
    {
        if ($this->viewerCanSeeAllEntitlements($viewer)) {
            return self::ENTITLED_LEAVE_CODES;
        }

        return $this->regionFor($user) === 'ph'
            ? [self::SERVICE_INCENTIVE_LEAVE_CODE]
            : [self::ANNUAL_LEAVE_CODE];
    }

    private function viewerCanSeeAllEntitlements(?User $viewer = null): bool
    {
        return $viewer === null || $viewer->isAdminLike();
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

    public function syncCurrentYearAnnualOverride(User $user): ?LeaveEntitlement
    {
        $year = (int) now()->year;
        $entitlement = $this->entitlementFor($user, $year, self::ANNUAL_LEAVE_CODE);

        if (! $entitlement) {
            return null;
        }

        $attributes = $this->entitlementAttributesFor($user, $year, self::ANNUAL_LEAVE_CODE);
        unset($attributes['created_by']);

        $entitlement->update($attributes + ['updated_by' => Auth::id()]);

        return $entitlement->fresh();
    }

    public function syncCurrentYearEligibleEntitlements(User $user): Collection
    {
        $year = (int) now()->year;

        return collect($this->eligibleEntitledLeaveCodesFor($user))
            ->map(function (string $attendanceCode) use ($user, $year) {
                $attributes = $this->entitlementAttributesFor($user, $year, $attendanceCode);
                unset($attributes['created_by']);

                return LeaveEntitlement::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'year' => $year,
                        'attendance_code' => $attendanceCode,
                    ],
                    $attributes + ['updated_by' => Auth::id()],
                )->fresh();
            })
            ->values();
    }

    public function userIsEligibleFor(User $user, string $attendanceCode, CarbonInterface|string|null $asOf = null): bool
    {
        $region = $this->regionFor($user);

        if ($region === 'ph' && in_array($attendanceCode, self::PH_UNAVAILABLE_LEAVE_CODES, true)) {
            return false;
        }

        return match ($attendanceCode) {
            self::ANNUAL_LEAVE_CODE,
            self::SICK_LEAVE_CODE => $region !== 'ph'
                && ($asOf === null || $this->uaeProbationCompleted($user, $this->asOfDate($asOf))),
            self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE => $region !== 'ph' && (
                (bool) $user->eligible_for_bereavement_spouse_leave
                || (bool) $user->eligible_for_bereavement_immediate_family_leave
            ),
            self::MATERNITY_LEAVE_CODE => $region === 'ph'
                ? $user->gender === 'female' && (bool) $user->eligible_for_maternity_leave
                : $user->gender === 'female',
            self::PARENTAL_LEAVE_CODE => $region === 'ph'
                ? (bool) $user->eligible_for_parental_leave && $this->hasMinimumServiceMonths($user, 6)
                : (bool) $user->eligible_for_parental_leave,
            self::SERVICE_INCENTIVE_LEAVE_CODE => $region === 'ph' && $this->hasMinimumServiceMonths($user, 12),
            self::PATERNITY_LEAVE_CODE => $region === 'ph'
                && $user->gender === 'male'
                && $user->marital_status === 'married'
                && (bool) $user->eligible_for_paternity_leave,
            self::VAWC_LEAVE_CODE => $region === 'ph'
                && $user->gender === 'female'
                && (bool) $user->eligible_for_vawc_leave,
            self::SPECIAL_WOMEN_LEAVE_CODE => $region === 'ph'
                && $user->gender === 'female'
                && (bool) $user->eligible_for_special_women_leave
                && $this->hasMinimumServiceMonths($user, 6),
            default => true,
        };
    }

    public function eligibilityMessage(string $attendanceCode, ?User $user = null, CarbonInterface|string|null $asOf = null): ?string
    {
        $region = $user ? $this->regionFor($user) : null;

        if ($user && $region === 'uae' && in_array($attendanceCode, [self::ANNUAL_LEAVE_CODE, self::SICK_LEAVE_CODE], true) && $asOf !== null) {
            if (! $user->joining_date) {
                return 'Joining date is required before UAE annual/sick leave eligibility can be calculated. Please contact HR or an admin.';
            }

            $availableDate = $this->uaeProbationCompletionDate($user)->toFormattedDateString();

            return 'This leave type is available from '.$availableDate.', after six completed months of service.';
        }

        return match ($attendanceCode) {
            self::MATERNITY_LEAVE_CODE => $region === 'ph'
                ? 'Philippines maternity leave requires Female gender and HR eligibility approval.'
                : 'Maternity leave is available only for employees whose gender is set to Female.',
            self::PARENTAL_LEAVE_CODE => $region === 'ph'
                ? 'Philippines parental leave requires HR eligibility approval and at least six months of service.'
                : 'UAE parental leave requires HR eligibility approval. Contact HR or an admin if you need to apply.',
            self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE => 'UAE bereavement leave requires HR eligibility approval for spouse or immediate-family bereavement.',
            self::SERVICE_INCENTIVE_LEAVE_CODE => 'Service incentive leave is available only for Philippines employees with at least one year of service.',
            self::PATERNITY_LEAVE_CODE => 'Paternity leave requires Philippines region, Male gender, Married status, and HR eligibility approval.',
            self::VAWC_LEAVE_CODE => 'Leave for VAWC requires Philippines region, Female gender, and HR eligibility approval.',
            self::SPECIAL_WOMEN_LEAVE_CODE => 'Special leave for women requires Philippines region, Female gender, HR eligibility approval, and at least six months of service.',
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
        $asOfByYear = $this->requestedAsOfDatesByYear($user, $attributes, $attendanceCode);

        return $requestedByYear
            ->map(function (float $requested, int $year) use ($user, $usedByYear, $asOfByYear, $attendanceCode, $attributes) {
                $allowance = $this->claimableAllowanceFor(
                    $user,
                    $year,
                    $attendanceCode,
                    $this->submissionAllowanceAsOf($year, $asOfByYear->get($year, $attributes['start_date'] ?? null)),
                );
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

    private function submissionAllowanceAsOf(int $year, CarbonInterface|string|null $asOf): CarbonInterface|string|null
    {
        if ($year !== (int) now()->year || $asOf === null || $asOf === '') {
            return $asOf;
        }

        $asOfDate = $asOf instanceof CarbonInterface ? $asOf : Carbon::parse($asOf);

        return now()->startOfDay()->gt($asOfDate->copy()->startOfDay()) ? now() : $asOf;
    }

    private function requestedAsOfDatesByYear(User $user, array $attributes, string $attendanceCode): Collection
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

        return $this->countedLeaveDatesForPlan($leavePlan)
            ->groupBy(fn (string $date) => (int) Carbon::parse($date)->year)
            ->map(fn (Collection $dates) => $dates->sort()->last());
    }

    public function bereavementSubmissionViolations(User $user, array $attributes, ?int $excludeLeavePlanId = null): array
    {
        if (($attributes['attendance_code'] ?? null) !== self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE
            || $this->regionFor($user) !== 'uae') {
            return [];
        }

        $relationship = $attributes['bereavement_relationship'] ?? null;
        $allowance = is_string($relationship) ? $this->bereavementRelationshipAllowance($relationship) : null;

        if ($allowance === null) {
            return [];
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

        $requestedByYear = $this->daysByYearForPlan($leavePlan);
        $usedByYear = $this->usedBereavementDaysByYear($user, $relationship, $excludeLeavePlanId);

        return $requestedByYear
            ->map(function (float $requested, int $year) use ($relationship, $allowance, $usedByYear) {
                $used = (float) $usedByYear->get($year, 0.0);
                $remaining = $allowance - $used;

                if ($requested <= $remaining) {
                    return null;
                }

                return [
                    'year' => $year,
                    'relationship' => $relationship,
                    'relationship_label' => LeavePlan::bereavementRelationshipOptions()[$relationship] ?? 'Bereavement',
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

    public function userIsEligibleForBereavementRelationship(User $user, ?string $relationship): bool
    {
        if ($this->regionFor($user) !== 'uae' || ! is_string($relationship)) {
            return false;
        }

        $flag = self::ONE_SHOT_UAE_BEREAVEMENT_LEAVE_FLAGS[$relationship] ?? null;

        return $flag !== null && (bool) $user->{$flag};
    }

    public function bereavementRelationshipEligibilityMessage(?string $relationship): string
    {
        $label = is_string($relationship)
            ? (LeavePlan::bereavementRelationshipOptions()[$relationship] ?? 'selected relationship')
            : 'selected relationship';

        return 'UAE bereavement leave for '.$label.' requires HR eligibility approval.';
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
        return 'Bereavement leave - '.$violation['relationship_label']
            .' limit exceeded for '.$violation['year'].'. Allowance: '
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

    private function entitlementFor(User $user, int $year, string $attendanceCode): ?LeaveEntitlement
    {
        if (! $this->userIsEligibleFor($user, $attendanceCode)) {
            return null;
        }

        return $this->ensureEntitlementsFor($user, $year)
            ->firstWhere('attendance_code', $attendanceCode);
    }

    public function approvedCarryOverDaysFor(User $user, int $year, string $attendanceCode = self::ANNUAL_LEAVE_CODE): float
    {
        if ($attendanceCode !== self::ANNUAL_LEAVE_CODE) {
            return 0.0;
        }

        return (float) AnnualLeaveCarryOver::query()
            ->where('user_id', $user->id)
            ->where('to_year', $year)
            ->where('attendance_code', self::ANNUAL_LEAVE_CODE)
            ->where('status', AnnualLeaveCarryOver::STATUS_APPROVED)
            ->sum('approved_days');
    }

    private function baseClaimableAllowanceFor(User $user, int $year, string $attendanceCode, CarbonInterface|string|null $asOf = null, ?LeaveEntitlement $entitlement = null): float
    {
        $entitlement ??= $this->entitlementFor($user, $year, $attendanceCode);

        if ($this->usesUaeAnnualServiceAllowance($user, $year, $attendanceCode, $entitlement)) {
            return $this->uaeAnnualAllowanceForService($user, $this->asOfDate($asOf));
        }

        return $entitlement
            ? (float) $entitlement->claimable_allowance_days
            : $this->defaultClaimableAllowanceFor($user, $attendanceCode, $asOf);
    }

    private function baseVisibleAllowanceFor(User $user, int $year, string $attendanceCode, CarbonInterface|string|null $asOf = null, ?LeaveEntitlement $entitlement = null): float
    {
        $entitlement ??= $this->entitlementFor($user, $year, $attendanceCode);

        if ($this->usesUaeAnnualServiceAllowance($user, $year, $attendanceCode, $entitlement)) {
            return $this->uaeAnnualAllowanceForService($user, $this->asOfDate($asOf));
        }

        return $entitlement
            ? (float) $entitlement->allowance_days
            : $this->visibleAllowanceFromClaimable($user, $attendanceCode, $this->defaultClaimableAllowanceFor($user, $attendanceCode, $asOf));
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

    public function completedServiceMonths(User $user, CarbonInterface $asOf): int
    {
        if (! $user->joining_date) {
            return 0;
        }

        $joiningDate = $user->joining_date instanceof CarbonInterface
            ? $user->joining_date->copy()->startOfDay()
            : Carbon::parse($user->joining_date)->startOfDay();
        $asOfDate = $asOf->copy()->startOfDay();

        if ($asOfDate->lt($joiningDate)) {
            return 0;
        }

        return (int) floor($joiningDate->diffInMonths($asOfDate));
    }

    public function uaeProbationCompleted(User $user, CarbonInterface $asOf): bool
    {
        if ($this->regionFor($user) !== 'uae' || ! $user->joining_date) {
            return false;
        }

        return $this->completedServiceMonths($user, $asOf) >= 6;
    }

    public function uaeAnnualAllowanceForService(User $user, CarbonInterface $asOf): float
    {
        $serviceMonths = $this->completedServiceMonths($user, $asOf);

        if ($serviceMonths < 6) {
            return 0.0;
        }

        if ($serviceMonths < 12) {
            return (float) ($serviceMonths * 2);
        }

        return LeaveSetting::decimalValue(
            $this->settingKeyFor($user, self::ANNUAL_LEAVE_CODE),
            $this->fallbackAllowanceFor($user, self::ANNUAL_LEAVE_CODE),
        );
    }

    private function defaultClaimableAllowanceFor(User $user, string $attendanceCode, CarbonInterface|string|null $asOf = null): float
    {
        if ($this->regionFor($user) === 'ph'
            && $attendanceCode === self::MATERNITY_LEAVE_CODE
            && (bool) $user->is_solo_parent) {
            return 120.0;
        }

        if ($this->regionFor($user) === 'uae' && $attendanceCode === self::ANNUAL_LEAVE_CODE) {
            return $this->uaeAnnualAllowanceForService($user, $this->asOfDate($asOf));
        }

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
                'used_days' => min(
                    (float) $band['days'],
                    max(0.0, $used - (float) $band['threshold']),
                ),
                'remaining_days' => max(
                    0.0,
                    (float) $band['days'] - min(
                        (float) $band['days'],
                        max(0.0, $used - (float) $band['threshold']),
                    ),
                ),
            ])
            ->values()
            ->all();
    }

    private function visibleBereavementBalancesFor(User $user, int $year, ?int $excludeLeavePlanId = null): array
    {
        return collect(LeavePlan::bereavementRelationshipOptions())
            ->filter(fn (string $label, string $relationship) => $this->userIsEligibleForBereavementRelationship($user, $relationship))
            ->mapWithKeys(function (string $label, string $relationship) use ($user, $year, $excludeLeavePlanId) {
                $allowance = (float) $this->bereavementRelationshipAllowance($relationship);
                $used = (float) $this->usedBereavementDaysByYear($user, $relationship, $excludeLeavePlanId)->get($year, 0.0);

                return ['L180_'.$relationship => $this->formatBalance([
                    'year' => $year,
                    'attendance_code' => self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE,
                    'bereavement_relationship' => $relationship,
                    'label' => 'Bereavement leave - '.$label,
                    'allowance' => $allowance,
                    'claimable_allowance' => $allowance,
                    'used' => $used,
                    'remaining' => max(0.0, $allowance - $used),
                    'claimable_remaining' => max(0.0, $allowance - $used),
                    'allowance_label' => 'Allowance',
                    'remaining_label' => 'Remaining',
                    'description' => null,
                    'pay_bands' => [],
                    'uses_override' => false,
                    'source' => LeaveEntitlement::SOURCE_REGIONAL_DEFAULT,
                    'source_label' => 'Leave Settings',
                    'region' => 'uae',
                    'region_label' => 'UAE',
                ])];
            })
            ->all();
    }

    private function usedBereavementDaysByYear(User $user, string $relationship, ?int $excludeLeavePlanId = null): Collection
    {
        return LeavePlan::query()
            ->with('user')
            ->where('user_id', $user->id)
            ->where('attendance_code', self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE)
            ->where('bereavement_relationship', $relationship)
            ->whereIn('status', self::COUNTED_STATUSES)
            ->when($excludeLeavePlanId, fn ($query, $id) => $query->whereKeyNot($id))
            ->get()
            ->reduce(function (Collection $totals, LeavePlan $leavePlan) {
                return $this->addPlanDaysToYearTotals($totals, $leavePlan);
            }, collect());
    }

    private function formatBalance(array $balance): array
    {
        $balance['formatted'] = [
            'allowance' => $this->formatDays((float) $balance['allowance']),
            'claimable_allowance' => $this->formatDays((float) $balance['claimable_allowance']),
            'base_allowance' => $this->formatDays((float) ($balance['base_allowance'] ?? $balance['allowance'])),
            'base_claimable_allowance' => $this->formatDays((float) ($balance['base_claimable_allowance'] ?? $balance['claimable_allowance'])),
            'carry_over' => $this->formatDays((float) ($balance['carry_over'] ?? 0.0)),
            'used' => $this->formatDays((float) $balance['used']),
            'remaining' => $this->formatDays((float) $balance['remaining']),
            'claimable_remaining' => $this->formatDays((float) $balance['claimable_remaining']),
        ];
        $balance['pay_bands'] = collect($balance['pay_bands'] ?? [])
            ->map(fn (array $band) => $band + [
                'formatted_days' => $this->formatDays((float) $band['days']),
                'formatted_used_days' => $this->formatDays((float) ($band['used_days'] ?? 0.0)),
                'formatted_remaining_days' => $this->formatDays((float) ($band['remaining_days'] ?? $band['days'])),
            ])
            ->all();

        return $balance;
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
            self::PATERNITY_LEAVE_CODE => LeaveSetting::PATERNITY_LEAVE_DEFAULT_DAYS_PH,
            self::VAWC_LEAVE_CODE => LeaveSetting::VAWC_LEAVE_DEFAULT_DAYS_PH,
            self::SPECIAL_WOMEN_LEAVE_CODE => LeaveSetting::SPECIAL_WOMEN_LEAVE_DEFAULT_DAYS_PH,
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
            return $region === 'ph' ? 105.0 : 60.0;
        }

        if ($attendanceCode === self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE) {
            return $region === 'ph' ? 0.0 : 8.0;
        }

        if ($attendanceCode === self::PARENTAL_LEAVE_CODE) {
            return $region === 'ph' ? 7.0 : 5.0;
        }

        if ($attendanceCode === self::SERVICE_INCENTIVE_LEAVE_CODE) {
            return 5.0;
        }

        if ($attendanceCode === self::PATERNITY_LEAVE_CODE) {
            return 7.0;
        }

        if ($attendanceCode === self::VAWC_LEAVE_CODE) {
            return 10.0;
        }

        if ($attendanceCode === self::SPECIAL_WOMEN_LEAVE_CODE) {
            return 60.0;
        }

        if ($region === 'ph') {
            return 0.0;
        }

        return LeaveSetting::decimalValue(LeaveSetting::ANNUAL_LEAVE_DEFAULT_DAYS, 22.0);
    }

    private function bereavementRelationshipAllowance(string $relationship): ?float
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
            self::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE => 'Bereavement leave',
            self::SERVICE_INCENTIVE_LEAVE_CODE => 'Service incentive leave',
            self::PATERNITY_LEAVE_CODE => 'Paternity leave',
            self::VAWC_LEAVE_CODE => 'Leave for VAWC',
            self::SPECIAL_WOMEN_LEAVE_CODE => 'Special leave for women',
            default => 'Annual leave',
        };
    }

    private function hasMinimumServiceMonths(User $user, int $months): bool
    {
        if (! $user->joining_date) {
            return false;
        }

        $joiningDate = $user->joining_date instanceof CarbonInterface
            ? $user->joining_date
            : Carbon::parse($user->joining_date);

        return $joiningDate->copy()->addMonthsNoOverflow($months)->lte(now());
    }

    private function usesUaeAnnualServiceAllowance(User $user, int $year, string $attendanceCode, ?LeaveEntitlement $entitlement = null): bool
    {
        return $this->regionFor($user) === 'uae'
            && $attendanceCode === self::ANNUAL_LEAVE_CODE
            && ! ($year === (int) now()->year && $user->annual_leave_allowance_days !== null)
            && $entitlement?->source !== LeaveEntitlement::SOURCE_USER_OVERRIDE;
    }

    private function uaeProbationCompletionDate(User $user): CarbonInterface
    {
        $joiningDate = $user->joining_date instanceof CarbonInterface
            ? $user->joining_date->copy()
            : Carbon::parse($user->joining_date);

        return $joiningDate->addMonthsNoOverflow(6);
    }

    private function asOfDate(CarbonInterface|string|null $asOf): CarbonInterface
    {
        if ($asOf instanceof CarbonInterface) {
            return $asOf;
        }

        if (is_string($asOf) && $asOf !== '') {
            return Carbon::parse($asOf);
        }

        return now();
    }

    private function balanceDescription(User $user, string $attendanceCode): ?string
    {
        if ($this->regionFor($user) === 'uae' && $attendanceCode === self::ANNUAL_LEAVE_CODE) {
            return 'Annual leave starts after 6 months of service and accrues monthly until 1 year.';
        }

        return null;
    }

    private function usesCalendarDays(LeavePlan $leavePlan): bool
    {
        $user = $leavePlan->relationLoaded('user')
            ? $leavePlan->user
            : User::find($leavePlan->user_id);

        return $user
            && ($leavePlan->attendance_code === self::MATERNITY_LEAVE_CODE
                || ($this->regionFor($user) === 'uae' && $leavePlan->attendance_code === self::SICK_LEAVE_CODE));
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
