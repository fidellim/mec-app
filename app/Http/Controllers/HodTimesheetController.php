<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectTimesheetRequest;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Models\User;
use App\Services\AuditLogService;

class HodTimesheetController extends Controller
{
    public function index()
    {
        $timesheets = $this->scope(Timesheet::with(['user', 'period']))
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return view('hod.timesheets.index', compact('timesheets'));
    }

    public function show(Timesheet $timesheet)
    {
        $this->authorizeDepartment($timesheet);
        return view('hod.timesheets.show', ['timesheet' => $timesheet->load(['user', 'entries.project', 'period', 'department'])]);
    }

    public function approve(Timesheet $timesheet, AuditLogService $audit)
    {
        $this->authorizeDepartment($timesheet);
        abort_unless($timesheet->status === 'submitted', 422);
        abort_if((int) $timesheet->user_id === (int) auth()->id() && ! auth()->user()->isAdminLike(), 403, 'Head of Department cannot approve their own timesheet.');

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

        return back()->with('success', 'Timesheet approved.');
    }

    public function reject(RejectTimesheetRequest $request, Timesheet $timesheet, AuditLogService $audit)
    {
        $this->authorizeDepartment($timesheet);
        abort_unless($timesheet->status === 'submitted', 422);
        abort_if((int) $timesheet->user_id === (int) auth()->id() && ! auth()->user()->isAdminLike(), 403, 'Head of Department cannot reject their own timesheet.');

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

        return back()->with('success', 'Timesheet rejected.');
    }

    public function tracker()
    {
        $period = TimesheetPeriod::where('status', 'open')->latest('start_date')->first();
        $employees = User::with(['timesheets' => fn ($q) => $period ? $q->where('timesheet_period_id', $period->id) : $q])
            ->where('department_id', auth()->user()->department_id)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('hod.tracker', compact('employees', 'period'));
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
}
