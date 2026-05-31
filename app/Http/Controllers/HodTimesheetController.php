<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectTimesheetRequest;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\MissingTimesheetReminderService;
use App\Services\TimesheetEmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HodTimesheetController extends Controller
{
    public function index()
    {
        $timesheets = $this->scope(Timesheet::with(['user', 'period']))
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('employee_id'), fn ($q, $employeeId) => $q->where('user_id', $employeeId))
            ->when(request('week_number'), fn ($q, $weekNumber) => $q->whereHas('period', fn ($period) => $period->where('week_number', $weekNumber)))
            ->when(request('year'), fn ($q, $year) => $q->whereHas('period', fn ($period) => $period->where('year', $year)))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $employees = User::where('department_id', auth()->user()->department_id)
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $periods = TimesheetPeriod::orderByDesc('year')
            ->orderByDesc('week_number')
            ->get(['week_number', 'year']);

        return view('hod.timesheets.index', compact('timesheets', 'employees', 'periods'));
    }

    public function show(Timesheet $timesheet)
    {
        $this->authorizeDepartment($timesheet);
        return view('hod.timesheets.show', ['timesheet' => $timesheet->load(['user', 'entries.project', 'period', 'department'])]);
    }

    public function approve(Timesheet $timesheet, AuditLogService $audit, TimesheetEmailNotificationService $emails)
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
        $audit->record('timesheet_approved', $timesheet, $old, $timesheet->fresh()->toArray());
        $emails->approved($timesheet);

        return back()->with('success', 'Timesheet approved.');
    }

    public function reject(RejectTimesheetRequest $request, Timesheet $timesheet, AuditLogService $audit, TimesheetEmailNotificationService $emails)
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
        $audit->record('timesheet_rejected', $timesheet, $old, $timesheet->fresh()->toArray());
        $emails->rejected($timesheet);

        return back()->with('success', 'Timesheet rejected.');
    }

    public function tracker(MissingTimesheetReminderService $reminders)
    {
        $periods = TimesheetPeriod::orderByDesc('year')
            ->orderByDesc('week_number')
            ->get();

        $period = request('period_id')
            ? $periods->firstWhere('id', (int) request('period_id'))
            : TimesheetPeriod::where('status', 'open')->latest('start_date')->first();

        $employees = User::with(['timesheets' => fn ($q) => $period ? $q->where('timesheet_period_id', $period->id) : $q])
            ->where('department_id', auth()->user()->department_id)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $reminderCooldowns = $period
            ? $employees->mapWithKeys(fn (User $employee) => [
                $employee->id => $reminders->reminderCooldownLabel($employee, $period),
            ])
            : collect();

        return view('hod.tracker', compact('employees', 'period', 'periods', 'reminderCooldowns'));
    }

    public function remindMissing(Request $request, MissingTimesheetReminderService $reminders)
    {
        $validated = $request->validate([
            'period_id' => ['required', Rule::exists('timesheet_periods', 'id')],
            'employee_id' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $period = TimesheetPeriod::findOrFail($validated['period_id']);
        $employeeIds = null;

        if (! empty($validated['employee_id'])) {
            $employee = User::where('department_id', auth()->user()->department_id)
                ->where('role', 'employee')
                ->where('is_active', true)
                ->findOrFail($validated['employee_id']);
            $employeeIds = [$employee->id];
        }

        $result = $reminders->sendForPeriodDetailed(
            period: $period,
            departmentId: auth()->user()->department_id,
            source: 'manual_hod',
            employeeIds: $employeeIds,
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

    private function scope($query)
    {
        if (auth()->user()->isAdminLike()) {
            return $query;
        }

        return $query->where('department_id', auth()->user()->department_id);
    }

    private function authorizeDepartment(Timesheet $timesheet): void
    {
        if (auth()->user()->isAdminLike()) {
            return;
        }

        abort_unless((int) $timesheet->department_id === (int) auth()->user()->department_id, 403);
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
            abort_unless(
                $timesheet->user?->role === 'hod',
                403,
                'Admin can only '.$action.' Head of Department timesheets.'
            );

            return;
        }

        abort_unless($actor->role === 'hod', 403);
        abort_unless((int) $timesheet->department_id === (int) $actor->department_id, 403);
        abort_unless(
            $timesheet->user?->role === 'employee',
            403,
            'Head of Department can only '.$action.' employee timesheets.'
        );
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
