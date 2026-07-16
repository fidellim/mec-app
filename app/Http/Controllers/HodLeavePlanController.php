<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectLeavePlanRequest;
use App\Models\Department;
use App\Models\LeavePlan;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\HodExclusionService;
use App\Services\LeavePlanApprovalService;
use App\Services\LeavePlanEmailNotificationService;
use App\Services\LeavePlanCalendarService;
use App\Services\LeavePlanReviewCalendarService;
use App\Services\LeavePlanStatusHistoryService;
use App\Services\LeaveEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HodLeavePlanController extends Controller
{
    public function __construct(private readonly HodExclusionService $hodExclusions)
    {
    }

    public function index()
    {
        $managedDepartmentIds = $this->managedDepartmentIds();
        $selectedDepartmentId = $this->selectedDepartmentId($managedDepartmentIds);

        $leavePlans = $this->scope(LeavePlan::with(['user', 'department']), $selectedDepartmentId)
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('employee_id'), fn ($q, $employeeId) => $q->where('user_id', $employeeId))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $employees = User::whereIn('department_id', $selectedDepartmentId ? [$selectedDepartmentId] : $managedDepartmentIds)
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->whereDoesntHave('visibilityExcludedByHods', fn ($query) => $query->whereKey(auth()->id()))
            ->orderBy('name')
            ->get();

        $departments = Department::whereIn('id', $managedDepartmentIds)->orderBy('name')->get();

        return view('hod.leave-plans.index', compact('leavePlans', 'employees', 'departments', 'selectedDepartmentId'));
    }

    public function leaveEntitlements(Request $request, LeaveEntitlementService $entitlements)
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'employee' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $managedDepartmentIds = $this->managedDepartmentIds();
        $selectedDepartmentId = $this->selectedDepartmentId($managedDepartmentIds);
        $year = (int) ($filters['year'] ?? now()->year);
        $employeeSearch = trim((string) ($filters['employee'] ?? ''));

        $employees = User::with('department')
            ->whereIn('department_id', $selectedDepartmentId ? [$selectedDepartmentId] : $managedDepartmentIds)
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->whereKeyNot($request->user()->id)
            ->whereDoesntHave('visibilityExcludedByHods', fn ($query) => $query->whereKey($request->user()->id))
            ->when($employeeSearch !== '', function ($query) use ($employeeSearch) {
                $escaped = addcslashes($employeeSearch, '%_\\');
                $query->where(function ($employeeQuery) use ($escaped) {
                    $employeeQuery->where('name', 'like', "%{$escaped}%")
                        ->orWhere('employee_code', 'like', "%{$escaped}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $balancesByUser = $entitlements->annualBalancesForUsers($employees->getCollection(), $year);
        $employees->getCollection()->each(function (User $employee) use ($balancesByUser) {
            $employee->annualLeaveBalance = $balancesByUser[$employee->id] ?? null;
        });

        return view('hod.leave-entitlements.index', [
            'employees' => $employees,
            'departments' => Department::whereIn('id', $managedDepartmentIds)->orderBy('name')->get(),
            'selectedDepartmentId' => $selectedDepartmentId,
            'employeeSearch' => $employeeSearch,
            'year' => $year,
        ]);
    }

    public function calendar(Request $request, LeavePlanCalendarService $calendar)
    {
        $managedDepartmentIds = $this->managedDepartmentIds();
        $selectedDepartmentId = $this->selectedDepartmentId($managedDepartmentIds);

        $query = $this->scope(LeavePlan::query(), $selectedDepartmentId);

        $employees = User::whereIn('department_id', $selectedDepartmentId ? [$selectedDepartmentId] : $managedDepartmentIds)
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->whereDoesntHave('visibilityExcludedByHods', fn ($employeeQuery) => $employeeQuery->whereKey(auth()->id()))
            ->orderBy('name')
            ->get();

        $departments = Department::whereIn('id', $managedDepartmentIds)->orderBy('name')->get();

        $calendarData = $calendar->build(
            request: $request,
            query: $query,
            showRoute: 'hod.leave-plans.show',
            showEmployee: true,
        ) + [
            'employees' => $employees,
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'attendanceCodes' => $this->leaveAttendanceCodes(),
        ];

        if ($request->query('calendar_fragment') === 'calendar') {
            return view('shared.leave_plan_calendar', $calendarData);
        }

        return view('hod.leave-plans.calendar', $calendarData);
    }

    public function show(LeavePlan $leavePlan, LeavePlanReviewCalendarService $reviewCalendar, LeaveEntitlementService $entitlements)
    {
        $this->authorizeDepartment($leavePlan);
        $leavePlan = $this->loadForShow($leavePlan);
        $balanceYear = (int) $leavePlan->start_date->year;

        return view('hod.leave-plans.show', [
            'leavePlan' => $leavePlan,
            'leaveBalances' => $entitlements->visibleBalancesFor($leavePlan->user, $balanceYear, $leavePlan->id, $leavePlan->start_date, auth()->user()),
            'leaveBalanceYear' => $balanceYear,
            'reviewCalendarMonths' => $reviewCalendar->build(
                $leavePlan,
                $this->scope(LeavePlan::query(), null),
            ),
        ]);
    }

    public function assignedIndex(LeavePlanApprovalService $approvals)
    {
        $actor = auth()->user();
        $allAssigned = LeavePlan::with(['user', 'department'])
            ->whereIn('status', [LeavePlan::STATUS_SUBMITTED, LeavePlan::STATUS_CANCELLATION_REQUESTED])
            ->whereIn('approval_stage', [LeavePlan::APPROVAL_STAGE_DIRECTOR, LeavePlan::APPROVAL_STAGE_HR])
            ->latest()
            ->get()
            ->filter(fn (LeavePlan $leavePlan) => $approvals->isAssignedCurrentStageApprover($actor, $leavePlan))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $leavePlans = new LengthAwarePaginator(
            $allAssigned->forPage($page, $perPage),
            $allAssigned->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('assigned.leave-plans.index', compact('leavePlans'));
    }

    public function assignedShow(LeavePlan $leavePlan, LeavePlanApprovalService $approvals, LeavePlanReviewCalendarService $reviewCalendar, LeaveEntitlementService $entitlements)
    {
        abort_unless($approvals->isAssignedCurrentStageApprover(auth()->user(), $leavePlan), 403);
        $leavePlan = $this->loadForShow($leavePlan);
        $balanceYear = (int) $leavePlan->start_date->year;

        return view('hod.leave-plans.show', [
            'leavePlan' => $leavePlan,
            'leaveBalances' => $entitlements->visibleBalancesFor($leavePlan->user, $balanceYear, $leavePlan->id, $leavePlan->start_date, auth()->user()),
            'leaveBalanceYear' => $balanceYear,
            'reviewCalendarMonths' => $reviewCalendar->build(
                $leavePlan,
                LeavePlan::query()->where('department_id', $leavePlan->department_id),
            ),
        ]);
    }

    public function history(LeavePlan $leavePlan)
    {
        if (request()->routeIs('assigned.leave-plans.*')) {
            $approvals = app(LeavePlanApprovalService::class);
            abort_unless($approvals->isAssignedCurrentStageApprover(auth()->user(), $leavePlan), 403);
        } else {
            $this->authorizeDepartment($leavePlan);
        }

        return view('shared.leave_plan_history_timeline', [
            'leavePlan' => $leavePlan->load('statusHistories.user'),
        ]);
    }

    public function approve(LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails, LeavePlanStatusHistoryService $history)
    {
        $this->authorizeApprovalAction($leavePlan, 'approve');
        abort_unless($leavePlan->status === LeavePlan::STATUS_SUBMITTED, 422);

        $old = $leavePlan->toArray();
        $stage = $leavePlan->approval_stage ?: LeavePlan::APPROVAL_STAGE_HOD;
        $updates = [
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_comment' => null,
            'rejected_approval_stage' => null,
        ];

        if ($stage === LeavePlan::APPROVAL_STAGE_HOD) {
            $updates += [
                'approval_stage' => LeavePlan::APPROVAL_STAGE_DIRECTOR,
                'hod_approved_at' => now(),
                'hod_approved_by' => auth()->id(),
            ];
        } elseif ($stage === LeavePlan::APPROVAL_STAGE_DIRECTOR) {
            $updates += [
                'approval_stage' => LeavePlan::APPROVAL_STAGE_HR,
                'director_approved_at' => now(),
                'director_approved_by' => auth()->id(),
            ];
        } else {
            $updates += [
                'status' => LeavePlan::STATUS_APPROVED,
                'approval_stage' => null,
                'hr_approved_at' => now(),
                'hr_approved_by' => auth()->id(),
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ];
        }

        $leavePlan->update($updates);
        $fresh = $leavePlan->fresh(['user', 'department']);

        $action = $fresh->status === LeavePlan::STATUS_APPROVED ? 'leave_plan_approved' : 'leave_plan_stage_approved';
        $audit->record($action, $fresh, $old, $fresh->toArray());
        $history->record($action, $fresh, $old, $fresh->toArray());

        if ($fresh->status === LeavePlan::STATUS_APPROVED) {
            $this->removeOneShotStatutoryEligibilityAfterApproval($fresh, $audit);
            $this->removeOneShotBereavementEligibilityAfterApproval($fresh, $audit);
            $emails->approved($fresh);
        } else {
            $emails->stagePending($fresh);
        }

        return $this->reviewActionRedirect(
            $fresh->status === LeavePlan::STATUS_APPROVED ? 'Leave plan approved.' : 'Leave plan moved to '.$fresh->approvalStageLabel().' review.'
        );
    }

    private function removeOneShotStatutoryEligibilityAfterApproval(LeavePlan $leavePlan, AuditLogService $audit): void
    {
        $flag = LeaveEntitlementService::ONE_SHOT_PH_STATUTORY_LEAVE_FLAGS[$leavePlan->attendance_code] ?? null;

        if (! $flag || ! $leavePlan->user || ! (bool) $leavePlan->user->{$flag}) {
            return;
        }

        $user = $leavePlan->user;
        $old = $user->toArray();

        $user->update([$flag => false]);
        $freshUser = $user->fresh();
        $audit->record('statutory_leave_eligibility_auto_removed', $freshUser, $old, $freshUser->toArray());
    }

    private function removeOneShotBereavementEligibilityAfterApproval(LeavePlan $leavePlan, AuditLogService $audit): void
    {
        if ($leavePlan->attendance_code !== LeaveEntitlementService::BEREAVEMENT_COMPASSIONATE_LEAVE_CODE) {
            return;
        }

        $flag = LeaveEntitlementService::ONE_SHOT_UAE_BEREAVEMENT_LEAVE_FLAGS[$leavePlan->bereavement_relationship] ?? null;

        if (! $flag || ! $leavePlan->user || ! (bool) $leavePlan->user->{$flag}) {
            return;
        }

        $user = $leavePlan->user;
        $old = $user->toArray();

        $user->update([$flag => false]);
        $freshUser = $user->fresh();
        $audit->record('bereavement_leave_eligibility_auto_removed', $freshUser, $old, $freshUser->toArray());
    }

    public function reject(RejectLeavePlanRequest $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails, LeavePlanStatusHistoryService $history)
    {
        $this->authorizeApprovalAction($leavePlan, 'reject');
        abort_unless($leavePlan->status === LeavePlan::STATUS_SUBMITTED, 422);

        $old = $leavePlan->toArray();
        $stage = $leavePlan->approval_stage ?: LeavePlan::APPROVAL_STAGE_HOD;
        $leavePlan->update([
            'status' => LeavePlan::STATUS_REJECTED,
            'approval_stage' => null,
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
            'rejection_comment' => $request->rejection_comment,
            'rejected_approval_stage' => $stage,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $new = $leavePlan->fresh()->toArray();
        $audit->record('leave_plan_rejected', $leavePlan, $old, $new);
        $history->record('leave_plan_rejected', $leavePlan, $old, $new);
        $emails->rejected($leavePlan);

        return $this->reviewActionRedirect('Leave plan rejected.');
    }

    public function approveCancellation(LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails, LeavePlanStatusHistoryService $history)
    {
        $this->authorizeApprovalAction($leavePlan, 'approve cancellation for', $this->cancellationIsStaged($leavePlan));
        abort_unless($leavePlan->status === LeavePlan::STATUS_CANCELLATION_REQUESTED, 422);

        $old = $leavePlan->toArray();
        $stage = $leavePlan->approval_stage;
        $updates = [
            'cancellation_rejection_comment' => null,
            'rejected_approval_stage' => null,
        ];

        if ($stage === LeavePlan::APPROVAL_STAGE_DIRECTOR) {
            $updates += [
                'approval_stage' => LeavePlan::APPROVAL_STAGE_HR,
            ];
        } else {
            $updates += [
                'status' => LeavePlan::STATUS_CANCELLED,
                'approval_stage' => null,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ];
        }

        $leavePlan->update($updates);

        $new = $leavePlan->fresh()->toArray();
        $action = ($stage === LeavePlan::APPROVAL_STAGE_DIRECTOR) ? 'leave_plan_stage_approved' : 'leave_plan_cancellation_approved';
        $audit->record($action, $leavePlan, $old, $new);
        $history->record($action, $leavePlan, $old, $new);

        if ($stage === LeavePlan::APPROVAL_STAGE_DIRECTOR) {
            $emails->cancellationStagePending($leavePlan);
        } else {
            $emails->cancellationApproved($leavePlan);
        }

        return $this->reviewActionRedirect(
            $stage === LeavePlan::APPROVAL_STAGE_DIRECTOR ? 'Leave plan cancellation moved to '.$leavePlan->fresh()->approvalStageLabel().' review.' : 'Leave plan cancellation approved.'
        );
    }

    public function rejectCancellation(Request $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails, LeavePlanStatusHistoryService $history)
    {
        $this->authorizeApprovalAction($leavePlan, 'reject cancellation for', $this->cancellationIsStaged($leavePlan));
        abort_unless($leavePlan->status === LeavePlan::STATUS_CANCELLATION_REQUESTED, 422);

        $validated = $request->validate([
            'cancellation_rejection_comment' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $old = $leavePlan->toArray();
        $stage = $leavePlan->approval_stage ?: LeavePlan::APPROVAL_STAGE_HOD;
        $leavePlan->update([
            'status' => LeavePlan::STATUS_APPROVED,
            'approval_stage' => null,
            'cancellation_rejection_comment' => $validated['cancellation_rejection_comment'],
            'rejected_approval_stage' => $stage,
        ]);

        $new = $leavePlan->fresh()->toArray();
        $audit->record('leave_plan_cancellation_rejected', $leavePlan, $old, $new);
        $history->record('leave_plan_cancellation_rejected', $leavePlan, $old, $new);
        $emails->cancellationRejected($leavePlan);

        return $this->reviewActionRedirect('Leave plan cancellation rejected.');
    }

    public function recallApproved(Request $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails, LeavePlanStatusHistoryService $history)
    {
        $this->authorizeApprovalAction($leavePlan, 'recall', false);
        abort_unless($leavePlan->status === LeavePlan::STATUS_APPROVED, 422, 'Only approved leave plans can be recalled.');

        $validated = $request->validate([
            'recall_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $old = $leavePlan->toArray();
        $leavePlan->update([
            'status' => LeavePlan::STATUS_RECALLED,
            'recalled_at' => now(),
            'recalled_by' => $request->user()->id,
            'recall_reason' => $validated['recall_reason'],
        ]);

        $new = $leavePlan->fresh()->toArray();
        $audit->record('leave_plan_approved_recalled', $leavePlan, $old, $new);
        $history->record('leave_plan_approved_recalled', $leavePlan, $old, $new);
        $emails->recalled($leavePlan);

        return back()->with('success', 'Approved leave plan recalled. The employee can now correct and resubmit it.');
    }

    public function voidApproved(Request $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanStatusHistoryService $history)
    {
        abort_unless($request->user()?->role === 'super_admin', 403);
        $this->authorizeApprovalAction($leavePlan, 'void', false);
        abort_unless($leavePlan->status === LeavePlan::STATUS_APPROVED, 422, 'Only approved leave plans can be voided.');

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $old = $leavePlan->toArray();
        $leavePlan->update([
            'status' => LeavePlan::STATUS_VOIDED,
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $validated['void_reason'],
        ]);

        $new = $leavePlan->fresh()->toArray();
        $audit->record('leave_plan_voided', $leavePlan, $old, $new);
        $history->record('leave_plan_voided', $leavePlan, $old, $new);

        return back()->with('success', 'Approved leave plan voided. The record is retained for audit history.');
    }

    private function scope($query, ?int $selectedDepartmentId = null)
    {
        if (auth()->user()->isAdminLike()) {
            return $query;
        }

        $departmentIds = $selectedDepartmentId ? [$selectedDepartmentId] : $this->managedDepartmentIds();

        return $query
            ->whereIn('department_id', $departmentIds)
            ->whereHas('user', fn ($userQuery) => $userQuery
                ->whereDoesntHave('visibilityExcludedByHods', fn ($hodQuery) => $hodQuery->whereKey(auth()->id())));
    }

    private function authorizeDepartment(LeavePlan $leavePlan): void
    {
        if (auth()->user()->isAdminLike()) {
            return;
        }

        abort_unless($this->managedDepartmentIds()->contains((int) $leavePlan->department_id), 403);
        abort_if(
            $this->hodExclusions->visibilityExcluded(auth()->user(), $leavePlan->user),
            403,
            'This employee is not visible to this Head of Department.'
        );
    }

    private function authorizeApprovalAction(LeavePlan $leavePlan, string $action, bool $staged = true): void
    {
        $actor = auth()->user();
        $leavePlan->loadMissing('user');
        $approvals = app(LeavePlanApprovalService::class);

        abort_if(
            (int) $leavePlan->user_id === (int) $actor->id,
            403,
            'You cannot '.$action.' your own leave plan.'
        );

        if (
            $actor->role === 'hod'
            && $actor->managedDepartmentIds()->contains((int) $leavePlan->department_id)
            && $this->hodExclusions->visibilityExcluded($actor, $leavePlan->user)
        ) {
            abort(403, 'This employee is not visible to this Head of Department.');
        }

        if ($staged && $message = $approvals->currentStageMissingMessage($leavePlan)) {
            abort(422, $message);
        }

        if ($actor->isAdminLike()) {
            return;
        }

        if ($staged && in_array($leavePlan->approval_stage, [LeavePlan::APPROVAL_STAGE_DIRECTOR, LeavePlan::APPROVAL_STAGE_HR], true)) {
            abort_unless($approvals->isAssignedCurrentStageApprover($actor, $leavePlan), 403);

            return;
        }

        abort_unless($actor->role === 'hod', 403);
        abort_unless(! $staged || ($leavePlan->approval_stage ?: LeavePlan::APPROVAL_STAGE_HOD) === LeavePlan::APPROVAL_STAGE_HOD, 403);
        abort_unless($actor->managedDepartmentIds()->contains((int) $leavePlan->department_id), 403);
        abort_if(
            $this->hodExclusions->visibilityExcluded($actor, $leavePlan->user),
            403,
            'This employee is not visible to this Head of Department.'
        );
        abort_if(
            $this->hodExclusions->approvalExcluded($actor, $leavePlan->user),
            403,
            'This Head of Department is not assigned to '.$action.' this employee leave plan.'
        );
    }

    private function loadForShow(LeavePlan $leavePlan): LeavePlan
    {
        return $leavePlan->load([
            'user',
            'department',
            'approver',
            'rejector',
            'hodApprover',
            'directorApprover',
            'hrApprover',
            'canceller',
            'recaller',
            'voider',
        ]);
    }

    private function managedDepartmentIds()
    {
        return auth()->user()->managedDepartmentIds();
    }

    private function reviewActionRedirect(string $message): RedirectResponse
    {
        $response = request()->routeIs('assigned.leave-plans.*')
            ? redirect()->route('assigned.leave-plans.index')
            : back();

        return $response->with('success', $message);
    }

    private function selectedDepartmentId($managedDepartmentIds): ?int
    {
        if (! request()->filled('department_id')) {
            return null;
        }

        $departmentId = (int) request('department_id');
        abort_unless($managedDepartmentIds->contains($departmentId), 403);

        return $departmentId;
    }

    private function leaveAttendanceCodes(): array
    {
        return collect(config('timesheet.attendance_codes'))
            ->only(config('timesheet.leave_attendance_codes', []))
            ->all();
    }

    private function cancellationIsStaged(LeavePlan $leavePlan): bool
    {
        return in_array($leavePlan->approval_stage, [LeavePlan::APPROVAL_STAGE_DIRECTOR, LeavePlan::APPROVAL_STAGE_HR], true);
    }
}
