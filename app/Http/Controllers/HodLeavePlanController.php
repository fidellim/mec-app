<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectLeavePlanRequest;
use App\Models\Department;
use App\Models\LeavePlan;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\HodExclusionService;
use App\Services\LeavePlanEmailNotificationService;
use App\Services\LeavePlanCalendarService;
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
            ->orderBy('name')
            ->get();

        $departments = Department::whereIn('id', $managedDepartmentIds)->orderBy('name')->get();

        return view('hod.leave-plans.index', compact('leavePlans', 'employees', 'departments', 'selectedDepartmentId'));
    }

    public function calendar(Request $request, LeavePlanCalendarService $calendar)
    {
        $managedDepartmentIds = $this->managedDepartmentIds();
        $selectedDepartmentId = $this->selectedDepartmentId($managedDepartmentIds);

        $query = $this->scope(LeavePlan::query(), $selectedDepartmentId);

        $employees = User::whereIn('department_id', $selectedDepartmentId ? [$selectedDepartmentId] : $managedDepartmentIds)
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $departments = Department::whereIn('id', $managedDepartmentIds)->orderBy('name')->get();

        return view('hod.leave-plans.calendar', $calendar->build(
            request: $request,
            query: $query,
            showRoute: 'hod.leave-plans.show',
            showEmployee: true,
        ) + [
            'employees' => $employees,
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'attendanceCodes' => $this->leaveAttendanceCodes(),
        ]);
    }

    public function show(LeavePlan $leavePlan)
    {
        $this->authorizeDepartment($leavePlan);

        return view('hod.leave-plans.show', [
            'leavePlan' => $leavePlan->load(['user', 'department', 'approver', 'rejector', 'canceller', 'recaller', 'voider']),
        ]);
    }

    public function approve(LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails)
    {
        $this->authorizeApprovalAction($leavePlan, 'approve');
        abort_unless($leavePlan->status === LeavePlan::STATUS_SUBMITTED, 422);

        $old = $leavePlan->toArray();
        $leavePlan->update([
            'status' => LeavePlan::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_comment' => null,
        ]);

        $audit->record('leave_plan_approved', $leavePlan, $old, $leavePlan->fresh()->toArray());
        $emails->approved($leavePlan);

        return back()->with('success', 'Leave plan approved.');
    }

    public function reject(RejectLeavePlanRequest $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails)
    {
        $this->authorizeApprovalAction($leavePlan, 'reject');
        abort_unless($leavePlan->status === LeavePlan::STATUS_SUBMITTED, 422);

        $old = $leavePlan->toArray();
        $leavePlan->update([
            'status' => LeavePlan::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
            'rejection_comment' => $request->rejection_comment,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        $audit->record('leave_plan_rejected', $leavePlan, $old, $leavePlan->fresh()->toArray());
        $emails->rejected($leavePlan);

        return back()->with('success', 'Leave plan rejected.');
    }

    public function approveCancellation(LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails)
    {
        $this->authorizeApprovalAction($leavePlan, 'approve cancellation for');
        abort_unless($leavePlan->status === LeavePlan::STATUS_CANCELLATION_REQUESTED, 422);

        $old = $leavePlan->toArray();
        $leavePlan->update([
            'status' => LeavePlan::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_rejection_comment' => null,
        ]);

        $audit->record('leave_plan_cancellation_approved', $leavePlan, $old, $leavePlan->fresh()->toArray());
        $emails->cancellationApproved($leavePlan);

        return back()->with('success', 'Leave plan cancellation approved.');
    }

    public function rejectCancellation(Request $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails)
    {
        $this->authorizeApprovalAction($leavePlan, 'reject cancellation for');
        abort_unless($leavePlan->status === LeavePlan::STATUS_CANCELLATION_REQUESTED, 422);

        $validated = $request->validate([
            'cancellation_rejection_comment' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $old = $leavePlan->toArray();
        $leavePlan->update([
            'status' => LeavePlan::STATUS_APPROVED,
            'cancellation_rejection_comment' => $validated['cancellation_rejection_comment'],
        ]);

        $audit->record('leave_plan_cancellation_rejected', $leavePlan, $old, $leavePlan->fresh()->toArray());
        $emails->cancellationRejected($leavePlan);

        return back()->with('success', 'Leave plan cancellation rejected.');
    }

    public function recallApproved(Request $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails)
    {
        $this->authorizeApprovalAction($leavePlan, 'recall');
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

        $audit->record('leave_plan_approved_recalled', $leavePlan, $old, $leavePlan->fresh()->toArray());
        $emails->recalled($leavePlan);

        return back()->with('success', 'Approved leave plan recalled. The employee can now correct and resubmit it.');
    }

    public function voidApproved(Request $request, LeavePlan $leavePlan, AuditLogService $audit)
    {
        abort_unless($request->user()?->role === 'super_admin', 403);
        $this->authorizeApprovalAction($leavePlan, 'void');
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

        $audit->record('leave_plan_voided', $leavePlan, $old, $leavePlan->fresh()->toArray());

        return back()->with('success', 'Approved leave plan voided. The record is retained for audit history.');
    }

    private function scope($query, ?int $selectedDepartmentId = null)
    {
        if (auth()->user()->isAdminLike()) {
            return $query;
        }

        $departmentIds = $selectedDepartmentId ? [$selectedDepartmentId] : $this->managedDepartmentIds();

        return $query->whereIn('department_id', $departmentIds);
    }

    private function authorizeDepartment(LeavePlan $leavePlan): void
    {
        if (auth()->user()->isAdminLike()) {
            return;
        }

        abort_unless($this->managedDepartmentIds()->contains((int) $leavePlan->department_id), 403);
    }

    private function authorizeApprovalAction(LeavePlan $leavePlan, string $action): void
    {
        $actor = auth()->user();
        $leavePlan->loadMissing('user');

        abort_if(
            (int) $leavePlan->user_id === (int) $actor->id,
            403,
            'You cannot '.$action.' your own leave plan.'
        );

        if ($actor->isAdminLike()) {
            return;
        }

        abort_unless($actor->role === 'hod', 403);
        abort_unless($actor->managedDepartmentIds()->contains((int) $leavePlan->department_id), 403);
        abort_if(
            $this->hodExclusions->approvalExcluded($actor, $leavePlan->user),
            403,
            'This Head of Department is not assigned to '.$action.' this employee leave plan.'
        );
    }

    private function managedDepartmentIds()
    {
        return auth()->user()->managedDepartmentIds();
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
}
