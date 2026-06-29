<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectTimesheetRequest;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;
use App\Models\Department;
use App\Services\AuditLogService;
use App\Services\MissingTimesheetReminderService;
use App\Services\HodExclusionService;
use App\Services\TimesheetEmailNotificationService;
use App\Services\TimesheetRecallService;
use App\Services\TimesheetStatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HodTimesheetController extends Controller
{
    public function __construct(private readonly HodExclusionService $hodExclusions)
    {
    }

    public function index()
    {
        $managedDepartmentIds = $this->managedDepartmentIds();
        $selectedDepartmentId = $this->selectedDepartmentId($managedDepartmentIds);

        $timesheets = $this->scope(Timesheet::with(['user', 'period', 'department']), $selectedDepartmentId)
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('employee_id'), fn ($q, $employeeId) => $q->where('user_id', $employeeId))
            ->when(request('week_number'), fn ($q, $weekNumber) => $q->whereHas('period', fn ($period) => $period->where('week_number', $weekNumber)))
            ->when(request('year'), fn ($q, $year) => $q->whereHas('period', fn ($period) => $period->where('year', $year)))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $employees = User::whereIn('department_id', $selectedDepartmentId ? [$selectedDepartmentId] : $managedDepartmentIds)
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $departments = Department::whereIn('id', $managedDepartmentIds)->orderBy('name')->get();

        $periods = TimesheetPeriod::orderByDesc('year')
            ->orderByDesc('week_number')
            ->get(['week_number', 'year']);

        return view('hod.timesheets.index', compact('timesheets', 'employees', 'periods', 'departments', 'selectedDepartmentId'));
    }

    public function show(Timesheet $timesheet)
    {
        $this->authorizeDepartment($timesheet);
        return view('hod.timesheets.show', ['timesheet' => $timesheet->load(['user', 'entries.project', 'period', 'department'])]);
    }

    public function history(Timesheet $timesheet)
    {
        $this->authorizeDepartment($timesheet);

        return view('shared.timesheet_history_timeline', [
            'timesheet' => $timesheet->load('statusHistories.user'),
        ]);
    }

    public function approve(Timesheet $timesheet, AuditLogService $audit, TimesheetEmailNotificationService $emails, TimesheetStatusHistoryService $history)
    {
        if ($this->isOwnTimesheet($timesheet)) {
            return back()->with('warning', $this->ownTimesheetApprovalMessage());
        }

        $this->authorizeApprovalAction($timesheet, 'approve');
        abort_unless($timesheet->status === 'submitted', 422);

        $old = $timesheet->toArray();
        $timesheet->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_comment' => null,
        ]);
        $new = $timesheet->fresh()->toArray();
        $audit->record('timesheet_approved', $timesheet, $old, $new);
        $history->record('timesheet_approved', $timesheet, $old, $new);
        $emails->approved($timesheet);

        return back()->with('success', 'Timesheet approved.');
    }

    public function reject(RejectTimesheetRequest $request, Timesheet $timesheet, AuditLogService $audit, TimesheetEmailNotificationService $emails, TimesheetStatusHistoryService $history)
    {
        if ($this->isOwnTimesheet($timesheet)) {
            return back()->with('warning', $this->ownTimesheetApprovalMessage());
        }

        $this->authorizeApprovalAction($timesheet, 'reject');
        abort_unless($timesheet->status === 'submitted', 422);

        $old = $timesheet->toArray();
        $timesheet->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'rejection_comment' => $request->rejection_comment,
            'approved_at' => null,
            'approved_by' => null,
        ]);
        $new = $timesheet->fresh()->toArray();
        $audit->record('timesheet_rejected', $timesheet, $old, $new);
        $history->record('timesheet_rejected', $timesheet, $old, $new);
        $emails->rejected($timesheet);

        return back()->with('success', 'Timesheet rejected.');
    }

    public function recallApproved(Request $request, Timesheet $timesheet, AuditLogService $audit, TimesheetEmailNotificationService $emails, TimesheetRecallService $recalls, TimesheetStatusHistoryService $history)
    {
        if ($this->isOwnTimesheet($timesheet)) {
            return back()->with('warning', 'You cannot recall your own approved timesheet. Another authorized reviewer must complete this correction.');
        }

        $this->authorizeApprovalAction($timesheet, 'recall');
        abort_unless($timesheet->status === Timesheet::STATUS_APPROVED, 422, 'Only approved timesheets can be recalled.');

        $validated = $request->validate([
            'recall_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $recalls->recallApproved($timesheet, $request->user(), $validated['recall_reason'], $audit, $emails, $history);

        return back()->with('success', 'Approved timesheet recalled. The employee has been notified to correct and resubmit it.');
    }

    public function tracker(MissingTimesheetReminderService $reminders)
    {
        $managedDepartmentIds = $this->managedDepartmentIds();
        $selectedDepartmentId = $this->selectedDepartmentId($managedDepartmentIds);

        $periods = TimesheetPeriod::orderByDesc('year')
            ->orderByDesc('week_number')
            ->get();

        $period = request('period_id')
            ? $periods->firstWhere('id', (int) request('period_id'))
            : TimesheetPeriod::where('status', 'open')->latest('start_date')->first();

        $employees = User::with(['timesheets' => fn ($q) => $period ? $q->where('timesheet_period_id', $period->id) : $q])
            ->whereIn('department_id', $selectedDepartmentId ? [$selectedDepartmentId] : $managedDepartmentIds)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $departments = Department::whereIn('id', $managedDepartmentIds)->orderBy('name')->get();

        $reminderCooldowns = $period
            ? $employees->mapWithKeys(fn (User $employee) => [
                $employee->id => $reminders->reminderCooldownLabel($employee, $period),
            ])
            : collect();

        return view('hod.tracker', compact('employees', 'period', 'periods', 'reminderCooldowns', 'departments', 'selectedDepartmentId'));
    }

    public function remindMissing(Request $request, MissingTimesheetReminderService $reminders)
    {
        $validated = $request->validate([
            'period_id' => ['required', Rule::exists('timesheet_periods', 'id')],
            'employee_id' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $period = TimesheetPeriod::findOrFail($validated['period_id']);
        $employeeIds = null;

        $managedDepartmentIds = $this->managedDepartmentIds();
        $selectedDepartmentId = $this->selectedDepartmentId($managedDepartmentIds);

        if (! empty($validated['employee_id'])) {
            $employee = User::whereIn('department_id', $managedDepartmentIds)
                ->where('role', 'employee')
                ->where('is_active', true)
                ->findOrFail($validated['employee_id']);
            $employeeIds = [$employee->id];
        }

        $result = $reminders->sendForPeriodDetailed(
            period: $period,
            departmentId: $selectedDepartmentId,
            source: 'manual_hod',
            employeeIds: $employeeIds,
            departmentIds: $selectedDepartmentId ? null : $managedDepartmentIds->all(),
        );

        $sent = $result['sent'];
        $skippedCooldown = $result['skipped_cooldown'];

        if ($sent === 0 && $skippedCooldown > 0) {
            return back()->with('warning', 'No reminder was sent. The selected employee(s) were already reminded recently.');
        }

        return back()->with(
            $sent > 0 ? 'success' : 'warning',
            $sent > 0
                ? "Sent {$sent} missing timesheet reminder(s)."
                : 'No missing timesheet reminders were sent. The selected employee(s) may already be submitted or approved.'
        );
    }

    private function scope($query, ?int $selectedDepartmentId = null)
    {
        if (auth()->user()->isAdminLike()) {
            return $query;
        }

        $departmentIds = $selectedDepartmentId ? [$selectedDepartmentId] : $this->managedDepartmentIds();

        return $query->whereIn('department_id', $departmentIds);
    }

    private function authorizeDepartment(Timesheet $timesheet): void
    {
        if (auth()->user()->isAdminLike()) {
            return;
        }

        abort_unless($this->managedDepartmentIds()->contains((int) $timesheet->department_id), 403);
    }

    private function authorizeApprovalAction(Timesheet $timesheet, string $action): void
    {
        $actor = auth()->user();
        $timesheet->loadMissing('user');

        abort_if(
            (int) $timesheet->user_id === (int) $actor->id,
            403,
            'You cannot '.$action.' your own timesheet.'
        );

        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->role === 'admin') {
            return;
        }

        abort_unless($actor->role === 'hod', 403);
        abort_unless($actor->managedDepartmentIds()->contains((int) $timesheet->department_id), 403);
        abort_unless(
            $timesheet->user?->role === 'employee',
            403,
            'Head of Department can only '.$action.' employee timesheets.'
        );
        abort_if(
            $this->hodExclusions->approvalExcluded($actor, $timesheet->user),
            403,
            'This Head of Department is not assigned to '.$action.' this employee timesheet.'
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

    private function isOwnTimesheet(Timesheet $timesheet): bool
    {
        return (int) $timesheet->user_id === (int) auth()->id();
    }

    private function ownTimesheetApprovalMessage(): string
    {
        return 'You cannot approve or reject your own timesheet. Another authorized approver must review this submission.';
    }
}
