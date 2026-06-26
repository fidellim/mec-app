<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimesheetSaveRequest;
use App\Models\LeavePlan;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetPeriod;
use App\Services\AuditLogService;
use App\Services\TimesheetEmailNotificationService;
use App\Services\TimesheetStatusHistoryService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeTimesheetController extends Controller
{
    public function index()
    {
        $timesheets = Timesheet::with('period')->where('user_id', auth()->id())->latest()->paginate(15);
        return view('employee.timesheets.index', compact('timesheets'));
    }

    public function create()
    {
        if ($redirect = $this->redirectIfMissingDepartment()) {
            return $redirect;
        }

        $periods = TimesheetPeriod::where('status', 'open')->latest('start_date')->get();
        abort_unless($periods->isNotEmpty(), 422, 'No open timesheet period is available.');

        if (! request()->filled('period_id')) {
            return view('employee.timesheets.choose_period', compact('periods'));
        }

        $period = $periods->firstWhere('id', (int) request('period_id'));

        if (! $period) {
            return redirect()
                ->route('employee.timesheets.create')
                ->with('warning', 'Select an open weekly period before creating a timesheet.');
        }

        $existingTimesheet = Timesheet::where('user_id', auth()->id())
            ->where('timesheet_period_id', $period->id)
            ->whereIn('status', Timesheet::ACTIVE_STATUSES)
            ->first();

        if ($existingTimesheet) {
            return redirect()
                ->route('employee.timesheets.show', $existingTimesheet)
                ->with('warning', 'You already have a timesheet for this week. You cannot create another one.');
        }

        return view('employee.timesheets.form', [
            'timesheet' => null,
            'periods' => collect([$period]),
            'projects' => Project::where('is_active', true)->orderBy('project_code')->get(),
            'attendanceCodes' => config('timesheet.attendance_codes'),
            'entries' => $this->defaultEntries($period),
            'approvedLeavePlans' => $this->approvedLeavePlansForPeriod($period),
        ]);
    }

    public function store(TimesheetSaveRequest $request, AuditLogService $audit, TimesheetEmailNotificationService $emails, TimesheetStatusHistoryService $history)
    {
        $user = $request->user();
        if (! $user->department_id) {
            return redirect()
                ->route('employee.timesheets.index')
                ->with('warning', $this->missingDepartmentMessage());
        }

        $period = TimesheetPeriod::findOrFail($request->timesheet_period_id);
        abort_if($period->status === 'closed', 422, 'Closed periods cannot accept submissions.');

        $existingTimesheet = Timesheet::where('user_id', $user->id)
            ->where('timesheet_period_id', $period->id)
            ->whereIn('status', Timesheet::ACTIVE_STATUSES)
            ->first();

        if ($existingTimesheet) {
            return redirect()
                ->route('employee.timesheets.show', $existingTimesheet)
                ->with('warning', 'You already have a timesheet for this week. You cannot create another one.');
        }

        $timesheet = DB::transaction(function () use ($request, $user, $period, $audit, $history) {
            $timesheet = Timesheet::create([
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'timesheet_period_id' => $period->id,
                'status' => $request->boolean('submit') ? 'submitted' : 'draft',
                'submitted_at' => $request->boolean('submit') ? now() : null,
            ]);

            $this->syncEntries($timesheet, $request->entries);
            $this->recalculate($timesheet);
            if ($request->boolean('submit')) {
                $new = $timesheet->fresh()->toArray();
                $audit->record('timesheet_submitted', $timesheet, null, $new);
                $history->record('timesheet_submitted', $timesheet, null, $new);
            }

            return $timesheet;
        });

        if ($request->boolean('submit')) {
            $emails->submitted($timesheet);
        }

        return redirect()->route('employee.timesheets.show', $timesheet)->with('success', 'Timesheet saved.');
    }

    public function show(Timesheet $timesheet)
    {
        $this->authorizeOwner($timesheet);
        return view('employee.timesheets.show', ['timesheet' => $timesheet->load(['entries.project', 'period', 'department'])]);
    }

    public function history(Timesheet $timesheet)
    {
        $this->authorizeOwner($timesheet);

        return view('shared.timesheet_history_timeline', [
            'timesheet' => $timesheet->load('statusHistories.user'),
        ]);
    }

    public function edit(Timesheet $timesheet)
    {
        $this->authorizeOwner($timesheet);
        abort_unless($timesheet->editableBy(auth()->user()), 403);

        return view('employee.timesheets.form', [
            'timesheet' => $timesheet->load('entries'),
            'periods' => TimesheetPeriod::where('id', $timesheet->timesheet_period_id)->get(),
            'projects' => Project::where('is_active', true)->orderBy('project_code')->get(),
            'attendanceCodes' => config('timesheet.attendance_codes'),
            'entries' => $this->entriesWithMissingPeriodDays($timesheet),
            'approvedLeavePlans' => $this->approvedLeavePlansForPeriod($timesheet->period),
        ]);
    }

    public function update(TimesheetSaveRequest $request, Timesheet $timesheet, AuditLogService $audit, TimesheetEmailNotificationService $emails, TimesheetStatusHistoryService $history)
    {
        $this->authorizeOwner($timesheet);
        abort_unless($timesheet->editableBy($request->user()), 403);
        if (! $request->user()->department_id || ! $timesheet->department_id) {
            return redirect()
                ->route('employee.timesheets.index')
                ->with('warning', $this->missingDepartmentMessage());
        }
        abort_if($timesheet->period->status === 'closed' && $timesheet->status !== Timesheet::STATUS_RECALLED, 422, 'Closed periods cannot accept submissions.');

        $old = $timesheet->load('entries')->toArray();
        $wasRejected = $timesheet->status === 'rejected';
        $wasRecalled = $timesheet->status === Timesheet::STATUS_RECALLED;

        DB::transaction(function () use ($request, $timesheet, $audit, $history, $old, $wasRejected, $wasRecalled) {
            $timesheet->update([
                'status' => $request->boolean('submit') ? 'submitted' : 'draft',
                'submitted_at' => $request->boolean('submit') ? now() : $timesheet->submitted_at,
                'rejection_comment' => $request->boolean('submit') ? null : $timesheet->rejection_comment,
                'approved_at' => ($request->boolean('submit') || $wasRecalled) ? null : $timesheet->approved_at,
                'approved_by' => ($request->boolean('submit') || $wasRecalled) ? null : $timesheet->approved_by,
            ]);
            $this->syncEntries($timesheet, $request->entries);
            $this->recalculate($timesheet);
            $action = match (true) {
                $request->boolean('submit') && ($wasRejected || $wasRecalled) => 'timesheet_resubmitted',
                $request->boolean('submit') => 'timesheet_submitted',
                default => null,
            };

            if ($action) {
                $new = $timesheet->fresh('entries')->toArray();
                $audit->record($action, $timesheet, $old, $new);
                $history->record($action, $timesheet, $old, $new);
            }
        });

        if ($request->boolean('submit')) {
            $emails->submitted($timesheet, $wasRejected || $wasRecalled);
        }

        return redirect()->route('employee.timesheets.show', $timesheet)->with('success', 'Timesheet updated.');
    }

    public function recall(Request $request, Timesheet $timesheet, AuditLogService $audit, TimesheetStatusHistoryService $history)
    {
        $this->authorizeOwner($timesheet);
        abort_unless($timesheet->status === 'submitted', 403, 'Only submitted timesheets can be recalled.');

        $validated = $request->validate([
            'withdrawal_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $timesheet->toArray();
        $timesheet->update([
            'status' => Timesheet::STATUS_WITHDRAWN,
            'submitted_at' => null,
        ]);

        $new = $timesheet->fresh()->toArray();
        $new['withdrawal_comment'] = $validated['withdrawal_comment'] ?? null;
        $audit->record('timesheet_withdrawn', $timesheet, $old, $new);
        $history->record('timesheet_withdrawn', $timesheet, $old, $new);

        return redirect()
            ->route('employee.timesheets.edit', $timesheet)
            ->with('warning', 'Your submitted timesheet has been withdrawn. You can now edit and resubmit it.');
    }

    public function destroy(Timesheet $timesheet)
    {
        $this->authorizeOwner($timesheet);
        abort_unless($timesheet->status === 'draft', 403);
        $timesheet->delete();

        return redirect()->route('employee.timesheets.index')->with('success', 'Draft deleted.');
    }

    private function defaultEntries(TimesheetPeriod $period)
    {
        return collect(CarbonPeriod::create($period->start_date, $period->end_date))->map(fn ($date) => [
            'work_date' => $date->toDateString(),
            'day_name' => $date->format('l'),
            'attendance_code' => $date->isWeekend() ? null : 'O100',
            'project_id' => null,
            'regular_hours' => 0,
            'overtime_hours' => 0,
            'remarks' => null,
        ]);
    }

    private function approvedLeavePlansForPeriod(TimesheetPeriod $period)
    {
        return LeavePlan::query()
            ->where('user_id', auth()->id())
            ->where('status', LeavePlan::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $period->end_date)
            ->whereDate('end_date', '>=', $period->start_date)
            ->orderBy('start_date')
            ->get();
    }

    private function entriesWithMissingPeriodDays(Timesheet $timesheet)
    {
        $existingEntries = $timesheet->entries->sortBy([
            ['work_date', 'asc'],
            ['id', 'asc'],
        ])->values();

        $datesWithEntries = $existingEntries
            ->pluck('work_date')
            ->map(fn ($date) => $date->toDateString())
            ->unique();

        $missingEntries = $this->defaultEntries($timesheet->period)
            ->reject(fn ($entry) => $datesWithEntries->contains($entry['work_date']));

        return $existingEntries
            ->concat($missingEntries)
            ->sortBy(fn ($entry) => $entry instanceof \App\Models\TimesheetEntry
                ? $entry->work_date->toDateString().'_'.$entry->id
                : $entry['work_date'].'_0')
            ->values();
    }

    private function syncEntries(Timesheet $timesheet, array $entries): void
    {
        $timesheet->entries()->delete();

        foreach ($entries as $entry) {
            $workDate = \Carbon\Carbon::parse($entry['work_date']);
            $timesheet->entries()->create([
                'work_date' => $workDate->toDateString(),
                'day_name' => $workDate->format('l'),
                'attendance_code' => $entry['attendance_code'] ?: null,
                'project_id' => $entry['project_id'] ?: null,
                'regular_hours' => $entry['regular_hours'] ?? 0,
                'overtime_hours' => $entry['overtime_hours'] ?? 0,
                'description' => null,
                'remarks' => $entry['remarks'] ?? null,
            ]);
        }
    }

    private function recalculate(Timesheet $timesheet): void
    {
        $regular = $timesheet->entries()->sum('regular_hours');
        $overtime = $timesheet->entries()->sum('overtime_hours');
        $timesheet->update([
            'total_regular_hours' => $regular,
            'total_overtime_hours' => $overtime,
            'total_hours' => $regular + $overtime,
        ]);
    }

    private function authorizeOwner(Timesheet $timesheet): void
    {
        abort_unless((int) $timesheet->user_id === (int) auth()->id(), 403);
    }

    private function redirectIfMissingDepartment()
    {
        if (auth()->user()->department_id) {
            return null;
        }

        return redirect()
            ->route('employee.timesheets.index')
            ->with('warning', $this->missingDepartmentMessage());
    }

    private function missingDepartmentMessage(): string
    {
        return 'You need to be assigned to a department before creating or submitting a timesheet. Please contact Super Admin.';
    }
}
