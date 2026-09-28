<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeavePlanSaveRequest;
use App\Models\LeavePlan;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\LeaveCoverageService;
use App\Services\LeaveEntitlementService;
use App\Services\LeavePlanApprovalService;
use App\Services\LeavePlanCalendarService;
use App\Services\LeavePlanEmailNotificationService;
use App\Services\LeavePlanStatusHistoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeLeavePlanController extends Controller
{
    public function index(Request $request, LeaveEntitlementService $entitlements)
    {
        $leavePlans = LeavePlan::with('department')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('employee.leave-plans.index', [
            'leavePlans' => $leavePlans,
            'leaveBalances' => $this->leaveBalances($request, $entitlements),
        ]);
    }

    public function calendar(Request $request, LeavePlanCalendarService $calendar)
    {
        if ($redirect = $this->redirectIfMissingDepartment()) {
            return $redirect;
        }

        $query = $calendar->scopeEmployeeRegionVisibility(
            $request,
            LeavePlan::query()->where('department_id', $request->user()->department_id)
        );

        $calendarData = $calendar->build(
            request: $request,
            query: $query,
            showRoute: 'employee.leave-plans.show',
            showEmployee: true,
            includeUrls: false,
            allowedStatusFilters: LeavePlanCalendarService::DEFAULT_STATUSES,
        ) + [
            'attendanceCodes' => $this->leaveAttendanceCodes($request->user()),
        ];
        $calendarData['calendarTitle'] = $calendarData['month']->format('F Y');
        $calendarData['calendarDescription'] = 'Calendar shows submitted, approved, and cancellation-requested leave in your department.';
        $calendarData['calendarReadonly'] = true;

        if ($request->query('calendar_fragment') === 'calendar') {
            return view('shared.leave_plan_calendar', $calendarData);
        }

        return view('employee.leave-plans.calendar', $calendarData);
    }

    public function create(Request $request, LeavePlanCalendarService $calendar, LeaveEntitlementService $entitlements)
    {
        if ($redirect = $this->redirectIfMissingDepartment()) {
            return $redirect;
        }

        if ($request->query('coverage_preview') === '1') {
            return $this->coveragePreview($request, null);
        }

        if ($this->wantsAvailabilityCalendarFragment($request)) {
            return $this->availabilityCalendarFragment($request, $calendar);
        }

        return view('employee.leave-plans.form', [
            'leavePlan' => null,
            'attendanceCodes' => $this->leaveAttendanceCodes($request->user()),
            'availabilityCalendar' => $this->availabilityCalendar($request, $calendar),
            'leaveBalances' => $this->leaveBalances($request, $entitlements),
            'leaveRegion' => $entitlements->regionFor($request->user()),
        ]);
    }

    public function store(LeavePlanSaveRequest $request, AuditLogService $audit, LeavePlanEmailNotificationService $emails, LeavePlanApprovalService $approvals, LeavePlanStatusHistoryService $history)
    {
        if ($redirect = $this->redirectIfMissingDepartment()) {
            return $redirect;
        }

        $user = $request->user();
        $submit = $request->boolean('submit');

        $leavePlan = DB::transaction(function () use ($request, $user, $submit, $audit, $approvals, $history) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($submit) {
                $this->validateCoverageSubmission($request);
            }
            $leavePlan = LeavePlan::create(array_merge($this->attributes($request), [
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'status' => $submit ? LeavePlan::STATUS_SUBMITTED : LeavePlan::STATUS_DRAFT,
                'approval_stage' => $submit ? $approvals->initialApprovalStageFor($user) : null,
                'submitted_at' => $submit ? now() : null,
            ]));

            if ($submit) {
                $new = $leavePlan->fresh()->toArray();
                $audit->record('leave_plan_submitted', $leavePlan, null, $new);
                $history->record('leave_plan_submitted', $leavePlan, null, $new);
            }

            return $leavePlan;
        });

        if ($submit) {
            $emails->submitted($leavePlan);
        }

        return redirect()
            ->route('employee.leave-plans.show', $leavePlan)
            ->with($this->overlapFlash($leavePlan))
            ->with('success', $submit ? 'Leave plan submitted for approval.' : 'Leave plan saved.');
    }

    public function show(LeavePlan $leavePlan)
    {
        $this->authorizeOwner($leavePlan);

        return view('employee.leave-plans.show', [
            'leavePlan' => $leavePlan->load(['department', 'approver', 'rejector', 'hodApprover', 'directorApprover', 'hrApprover', 'canceller', 'recaller', 'voider']),
        ]);
    }

    public function history(LeavePlan $leavePlan)
    {
        $this->authorizeOwner($leavePlan);

        return view('shared.leave_plan_history_timeline', [
            'leavePlan' => $leavePlan->load('statusHistories.user'),
        ]);
    }

    public function edit(Request $request, LeavePlan $leavePlan, LeavePlanCalendarService $calendar, LeaveEntitlementService $entitlements)
    {
        $this->authorizeOwner($leavePlan);
        abort_unless($leavePlan->editableBy(auth()->user()), 403);

        if ($request->query('coverage_preview') === '1') {
            return $this->coveragePreview($request, $leavePlan);
        }

        if ($this->wantsAvailabilityCalendarFragment($request)) {
            return $this->availabilityCalendarFragment($request, $calendar, $leavePlan);
        }

        return view('employee.leave-plans.form', [
            'leavePlan' => $leavePlan,
            'attendanceCodes' => $this->leaveAttendanceCodes($request->user()),
            'availabilityCalendar' => $this->availabilityCalendar($request, $calendar, $leavePlan),
            'leaveBalances' => $this->leaveBalances($request, $entitlements, $leavePlan),
            'leaveRegion' => $entitlements->regionFor($request->user()),
        ]);
    }

    public function update(LeavePlanSaveRequest $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails, LeavePlanApprovalService $approvals, LeavePlanStatusHistoryService $history)
    {
        $this->authorizeOwner($leavePlan);
        abort_unless($leavePlan->editableBy($request->user()), 403);

        if ($redirect = $this->redirectIfMissingDepartment()) {
            return $redirect;
        }

        $submit = $request->boolean('submit');
        $old = $leavePlan->toArray();
        $wasRejected = $leavePlan->status === LeavePlan::STATUS_REJECTED;
        $wasRecalled = $leavePlan->status === LeavePlan::STATUS_RECALLED;

        DB::transaction(function () use ($request, $leavePlan, $submit, $audit, $approvals, $history, $old, $wasRejected, $wasRecalled) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $leavePlan->refresh();
            abort_unless($leavePlan->editableBy($request->user()), 403);
            if ($submit) {
                $this->validateCoverageSubmission($request, $leavePlan);
            }
            $leavePlan->update(array_merge($this->attributes($request), [
                'department_id' => $request->user()->department_id,
                'status' => $submit ? LeavePlan::STATUS_SUBMITTED : LeavePlan::STATUS_DRAFT,
                'approval_stage' => $submit ? $approvals->initialApprovalStageFor($request->user()) : null,
                'submitted_at' => $submit ? now() : $leavePlan->submitted_at,
                'approved_at' => null,
                'approved_by' => null,
                'hod_approved_at' => null,
                'hod_approved_by' => null,
                'director_approved_at' => null,
                'director_approved_by' => null,
                'hr_approved_at' => null,
                'hr_approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_comment' => null,
                'rejected_approval_stage' => null,
                'recalled_at' => null,
                'recalled_by' => null,
                'recall_reason' => null,
                'voided_at' => null,
                'voided_by' => null,
                'void_reason' => null,
            ]));

            if ($submit) {
                $new = $leavePlan->fresh()->toArray();
                $audit->record('leave_plan_submitted', $leavePlan, $old, $new);
                $history->record($wasRejected || $wasRecalled ? 'leave_plan_resubmitted' : 'leave_plan_submitted', $leavePlan, $old, $new);
            }
        });

        if ($submit) {
            $emails->submitted($leavePlan, $wasRejected || $wasRecalled);
        }

        return redirect()
            ->route('employee.leave-plans.show', $leavePlan)
            ->with($this->overlapFlash($leavePlan))
            ->with('success', $submit ? 'Leave plan submitted for approval.' : 'Leave plan updated.');
    }

    public function requestCancellation(Request $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails, LeavePlanApprovalService $approvals, LeavePlanStatusHistoryService $history)
    {
        return DB::transaction(function () use ($request, $leavePlan, $audit, $emails, $approvals, $history) {
            User::whereKey($leavePlan->user_id)->lockForUpdate()->firstOrFail();
            $leavePlan->refresh();
            $this->authorizeOwner($leavePlan);
            abort_unless($leavePlan->status === LeavePlan::STATUS_APPROVED, 403, 'Only approved leave plans can request cancellation.');

            $validated = $request->validate([
                'cancellation_reason' => ['required', 'string', 'min:3', 'max:2000'],
            ]);

            $old = $leavePlan->toArray();
            $leavePlan->update([
                'status' => LeavePlan::STATUS_CANCELLATION_REQUESTED,
                'approval_stage' => $request->user()->role === 'hod' ? $approvals->initialApprovalStageFor($request->user()) : null,
                'cancellation_requested_at' => now(),
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancellation_rejection_comment' => null,
            ]);

            $new = $leavePlan->fresh()->toArray();
            $audit->record('leave_plan_cancellation_requested', $leavePlan, $old, $new);
            $history->record('leave_plan_cancellation_requested', $leavePlan, $old, $new);
            $emails->cancellationRequested($leavePlan);

            return back()->with('success', 'Cancellation request sent for approval.');
        });
    }

    public function destroy(LeavePlan $leavePlan)
    {
        $this->authorizeOwner($leavePlan);
        abort_unless($leavePlan->status === LeavePlan::STATUS_DRAFT, 403);

        $leavePlan->delete();

        return redirect()->route('employee.leave-plans.index')->with('success', 'Draft leave plan deleted.');
    }

    private function attributes(LeavePlanSaveRequest $request): array
    {
        $validated = $request->validated();

        if ($validated['duration_type'] === 'full_day') {
            $validated['half_day_period'] = null;
        }

        if ($validated['attendance_code'] !== LeaveEntitlementService::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE) {
            $validated['bereavement_relationship'] = null;
        }

        return $validated;
    }

    private function leaveAttendanceCodes($user): array
    {
        $leaveAttendanceCodes = app(LeaveEntitlementService::class)->eligibleLeaveAttendanceCodesFor($user);

        return collect(config('timesheet.attendance_codes'))
            ->only($leaveAttendanceCodes)
            ->all();
    }

    private function availabilityCalendar(Request $request, LeavePlanCalendarService $calendar, ?LeavePlan $leavePlan = null): array
    {
        $user = $request->user();

        return $calendar->build(
            request: $request,
            query: $calendar->scopeEmployeeRegionVisibility(
                $request,
                LeavePlan::query()->where('department_id', $user->department_id)
            ),
            showRoute: 'employee.leave-plans.show',
            showEmployee: true,
            excludeLeavePlanId: $leavePlan?->id,
            includeUrls: false,
            defaultMonth: $leavePlan?->start_date ?? $this->oldStartDate($request),
            allowedStatusFilters: LeavePlanCalendarService::DEFAULT_STATUSES,
        );
    }

    private function availabilityCalendarFragment(Request $request, LeavePlanCalendarService $calendar, ?LeavePlan $leavePlan = null)
    {
        return view('shared.leave_plan_calendar', array_merge($this->availabilityCalendar($request, $calendar, $leavePlan), [
            'calendarTitle' => 'Department leave availability',
            'calendarDescription' => 'Shows submitted, approved, and cancellation-requested leave in your department. Your selected dates are highlighted for comparison.',
            'calendarReadonly' => true,
            'calendarInteractiveRange' => true,
            'calendarVariant' => 'availability',
        ]));
    }

    private function wantsAvailabilityCalendarFragment(Request $request): bool
    {
        return $request->query('calendar_fragment') === 'availability';
    }

    private function leaveBalances(Request $request, LeaveEntitlementService $entitlements, ?LeavePlan $leavePlan = null): array
    {
        $date = $this->oldStartDate($request) ?? $leavePlan?->start_date ?? now();
        $year = (int) Carbon::parse($date)->year;

        return $entitlements->visibleBalancesFor($request->user(), $year, $leavePlan?->id, $date, $request->user());
    }

    private function oldStartDate(Request $request): ?Carbon
    {
        $startDate = $request->old('start_date');

        if (! is_string($startDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            return null;
        }

        try {
            return Carbon::parse($startDate);
        } catch (\Throwable) {
            return null;
        }
    }

    private function coveragePreview(Request $request, ?LeavePlan $leavePlan)
    {
        $attributes = $request->validate([
            'attendance_code' => ['required', 'in:L100'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'duration_type' => ['required', 'in:full_day,half_day'],
            'half_day_period' => ['nullable', 'required_if:duration_type,half_day', 'in:morning,afternoon'],
        ]);
        if ($attributes['duration_type'] === 'half_day' && $attributes['start_date'] !== $attributes['end_date']) {
            throw ValidationException::withMessages(['end_date' => 'Half-day leave must use the same start and end date.']);
        }
        $coverage = app(LeaveCoverageService::class)->preview($request->user(), $attributes, $leavePlan?->id);
        $entitlements = app(LeaveEntitlementService::class);
        $balances = [];
        foreach (range((int) substr($attributes['start_date'], 0, 4), (int) substr($attributes['end_date'], 0, 4)) as $year) {
            $balances = array_merge($balances, array_values($entitlements->visibleBalancesFor(
                $request->user(), $year, $leavePlan?->id, $year === (int) substr($attributes['start_date'], 0, 4) ? $attributes['start_date'] : $year.'-01-01', $request->user()
            )));
        }

        return response()->json([
            'coverage' => $coverage,
            'balances_html' => view('shared.leave_balance_cards', [
                'leaveBalances' => $balances,
                'description' => 'Balances for the selected calendar year(s). Available annual leave subtracts approved and pending days.',
            ])->render(),
        ]);
    }

    private function validateCoverageSubmission(LeavePlanSaveRequest $request, ?LeavePlan $leavePlan = null): void
    {
        $attributes = $this->attributes($request);
        $errors = app(LeaveCoverageService::class)->preview($request->user(), $attributes, $leavePlan?->id)['errors'];
        // Resolve a fresh service after taking the lock; validation before the transaction may be stale.
        $entitlements = app()->make(LeaveEntitlementService::class);
        foreach ($entitlements->submissionViolations($request->user(), $attributes, $leavePlan?->id) as $violation) {
            $errors[] = $entitlements->violationMessage($violation);
        }
        if ($errors) {
            throw ValidationException::withMessages(['attendance_code' => $errors]);
        }
    }

    private function overlapFlash(LeavePlan $leavePlan): array
    {
        if ($leavePlan->attendance_code === LeaveEntitlementService::ANNUAL_LEAVE_CODE) {
            return [];
        }
        $entitlements = app(LeaveEntitlementService::class);
        $countedDates = $entitlements->countedLeaveDatesForPlan($leavePlan);

        if ($countedDates->isEmpty()) {
            return [];
        }

        $hasOverlap = LeavePlan::query()
            ->with('user')
            ->where('user_id', $leavePlan->user_id)
            ->whereKeyNot($leavePlan->id)
            ->whereIn('status', LeavePlan::ACTIVE_OVERLAP_STATUSES)
            ->whereDate('start_date', '<=', $leavePlan->end_date)
            ->whereDate('end_date', '>=', $leavePlan->start_date)
            ->get()
            ->contains(fn (LeavePlan $existing) => $entitlements
                ->countedLeaveDatesForPlan($existing)
                ->intersect($countedDates)
                ->isNotEmpty());

        return $hasOverlap
            ? ['warning' => 'This leave plan overlaps another active leave plan for the same employee. Please review the dates.']
            : [];
    }

    private function authorizeOwner(LeavePlan $leavePlan): void
    {
        abort_unless((int) $leavePlan->user_id === (int) auth()->id(), 403);
    }

    private function redirectIfMissingDepartment()
    {
        if (auth()->user()->department_id) {
            return null;
        }

        return redirect()
            ->route('employee.leave-plans.index')
            ->with('warning', 'You need to be assigned to a department before creating or submitting a leave plan. Please contact Super Admin.');
    }
}
