<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeavePlanSaveRequest;
use App\Models\LeavePlan;
use App\Services\AuditLogService;
use App\Services\LeavePlanEmailNotificationService;
use App\Services\LeavePlanCalendarService;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeLeavePlanController extends Controller
{
    public function index()
    {
        $leavePlans = LeavePlan::with('department')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('employee.leave-plans.index', compact('leavePlans'));
    }

    public function calendar(Request $request, LeavePlanCalendarService $calendar)
    {
        $query = LeavePlan::query()->where('user_id', $request->user()->id);

        return view('employee.leave-plans.calendar', $calendar->build(
            request: $request,
            query: $query,
            showRoute: 'employee.leave-plans.show',
            showEmployee: false,
        ) + [
            'attendanceCodes' => $this->leaveAttendanceCodes(),
        ]);
    }

    public function create()
    {
        if ($redirect = $this->redirectIfMissingDepartment()) {
            return $redirect;
        }

        return view('employee.leave-plans.form', [
            'leavePlan' => null,
            'attendanceCodes' => $this->leaveAttendanceCodes(),
        ]);
    }

    public function store(LeavePlanSaveRequest $request, AuditLogService $audit, LeavePlanEmailNotificationService $emails)
    {
        if ($redirect = $this->redirectIfMissingDepartment()) {
            return $redirect;
        }

        $user = $request->user();
        $submit = $request->boolean('submit');

        $leavePlan = DB::transaction(function () use ($request, $user, $submit, $audit) {
            $leavePlan = LeavePlan::create(array_merge($this->attributes($request), [
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'status' => $submit ? LeavePlan::STATUS_SUBMITTED : LeavePlan::STATUS_DRAFT,
                'approval_stage' => $submit ? LeavePlan::APPROVAL_STAGE_HOD : null,
                'submitted_at' => $submit ? now() : null,
            ]));

            if ($submit) {
                $audit->record('leave_plan_submitted', $leavePlan, null, $leavePlan->fresh()->toArray());
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

    public function edit(LeavePlan $leavePlan)
    {
        $this->authorizeOwner($leavePlan);
        abort_unless($leavePlan->editableBy(auth()->user()), 403);

        return view('employee.leave-plans.form', [
            'leavePlan' => $leavePlan,
            'attendanceCodes' => $this->leaveAttendanceCodes(),
        ]);
    }

    public function update(LeavePlanSaveRequest $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails)
    {
        $this->authorizeOwner($leavePlan);
        abort_unless($leavePlan->editableBy($request->user()), 403);

        if ($redirect = $this->redirectIfMissingDepartment()) {
            return $redirect;
        }

        $submit = $request->boolean('submit');
        $old = $leavePlan->toArray();

        DB::transaction(function () use ($request, $leavePlan, $submit, $audit, $old) {
            $leavePlan->update(array_merge($this->attributes($request), [
                'department_id' => $request->user()->department_id,
                'status' => $submit ? LeavePlan::STATUS_SUBMITTED : LeavePlan::STATUS_DRAFT,
                'approval_stage' => $submit ? LeavePlan::APPROVAL_STAGE_HOD : null,
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
                $audit->record('leave_plan_submitted', $leavePlan, $old, $leavePlan->fresh()->toArray());
            }
        });

        if ($submit) {
            $emails->submitted($leavePlan, true);
        }

        return redirect()
            ->route('employee.leave-plans.show', $leavePlan)
            ->with($this->overlapFlash($leavePlan))
            ->with('success', $submit ? 'Leave plan submitted for approval.' : 'Leave plan updated.');
    }

    public function requestCancellation(Request $request, LeavePlan $leavePlan, AuditLogService $audit, LeavePlanEmailNotificationService $emails)
    {
        $this->authorizeOwner($leavePlan);
        abort_unless($leavePlan->status === LeavePlan::STATUS_APPROVED, 403, 'Only approved leave plans can request cancellation.');

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $old = $leavePlan->toArray();
        $leavePlan->update([
            'status' => LeavePlan::STATUS_CANCELLATION_REQUESTED,
            'cancellation_requested_at' => now(),
            'cancellation_reason' => $validated['cancellation_reason'],
            'cancellation_rejection_comment' => null,
        ]);

        $audit->record('leave_plan_cancellation_requested', $leavePlan, $old, $leavePlan->fresh()->toArray());
        $emails->cancellationRequested($leavePlan);

        return back()->with('success', 'Cancellation request sent for approval.');
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

        return $validated;
    }

    private function leaveAttendanceCodes(): array
    {
        return collect(config('timesheet.attendance_codes'))
            ->only(config('timesheet.leave_attendance_codes', []))
            ->all();
    }

    private function overlapFlash(LeavePlan $leavePlan): array
    {
        $holidayService = app(HolidayService::class);
        $countedDates = $holidayService->countedLeaveDates($leavePlan);

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
            ->contains(fn (LeavePlan $existing) => $holidayService
                ->countedLeaveDates($existing)
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
