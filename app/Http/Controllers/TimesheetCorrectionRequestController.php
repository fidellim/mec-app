<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Models\TimesheetCorrectionRequest;
use App\Models\TimesheetCorrectionRequestEntry;
use App\Models\TimesheetEntry;
use App\Services\AdminExclusionService;
use App\Services\AuditLogService;
use App\Services\HodExclusionService;
use App\Services\TimesheetCorrectionNotificationService;
use App\Services\TimesheetEmailNotificationService;
use App\Services\TimesheetRecallService;
use App\Services\TimesheetStatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimesheetCorrectionRequestController extends Controller
{
    public function __construct(private readonly HodExclusionService $hodExclusions, private readonly AdminExclusionService $adminExclusions) {}

    public function store(Request $request, AuditLogService $audit, TimesheetCorrectionNotificationService $notifications)
    {
        $data = $request->validate(['entry_ids' => ['required', 'array', 'min:1', 'max:50'], 'entry_ids.*' => ['integer', 'distinct'], 'comment' => ['required', 'string', 'min:5', 'max:2000']]);
        $actor = $request->user();

        $correction = DB::transaction(function () use ($data, $actor, $audit) {
            $entries = TimesheetEntry::query()->with(['project:id,project_code,project_manager_id', 'timesheet:id,user_id,department_id,status'])
                ->whereIn('id', $data['entry_ids'])->lockForUpdate()->get();
            if ($entries->count() !== count($data['entry_ids'])) throw ValidationException::withMessages(['entry_ids' => 'One or more selected entries no longer exist.']);
            $timesheetIds = $entries->pluck('timesheet_id')->unique();
            if ($timesheetIds->count() !== 1) throw ValidationException::withMessages(['entry_ids' => 'Selected entries must belong to one timesheet.']);
            if ($entries->contains(fn ($entry) => (int) $entry->project?->project_manager_id !== (int) $actor->id)) abort(403);
            $timesheet = $entries->first()->timesheet;
            if (! in_array($timesheet->status, [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED], true)) throw ValidationException::withMessages(['entry_ids' => 'Correction requests can only be raised for submitted or approved timesheets.']);
            $duplicate = TimesheetCorrectionRequestEntry::query()->whereIn('timesheet_entry_id', $entries->pluck('id'))->whereHas('request', fn ($q) => $q->where('status', TimesheetCorrectionRequest::STATUS_OPEN))->exists();
            if ($duplicate) throw ValidationException::withMessages(['entry_ids' => 'A selected entry already belongs to an open correction request.']);

            $correction = TimesheetCorrectionRequest::create(['timesheet_id' => $timesheet->id, 'requested_by' => $actor->id, 'department_id' => $timesheet->department_id, 'comment' => $data['comment'], 'status' => TimesheetCorrectionRequest::STATUS_OPEN]);
            foreach ($entries as $entry) $correction->entries()->create(['timesheet_entry_id' => $entry->id, 'project_id' => $entry->project_id, 'work_date' => $entry->work_date, 'project_code' => $entry->project?->project_code, 'regular_hours' => $entry->regular_hours, 'overtime_hours' => $entry->overtime_hours, 'description' => $entry->description]);
            $audit->record('timesheet_correction_requested', $correction, null, $correction->load('entries')->toArray());
            return $correction;
        });
        $notifications->submitted($correction);
        return back()->with('success', 'Correction request sent to the employee\'s HOD.');
    }

    public function withdraw(TimesheetCorrectionRequest $correctionRequest, AuditLogService $audit, TimesheetCorrectionNotificationService $notifications)
    {
        abort_unless((int) $correctionRequest->requested_by === (int) auth()->id(), 403);
        DB::transaction(function () use ($correctionRequest, $audit) {
            $locked = TimesheetCorrectionRequest::lockForUpdate()->findOrFail($correctionRequest->id);
            abort_unless($locked->status === TimesheetCorrectionRequest::STATUS_OPEN, 422);
            $locked->update(['status' => TimesheetCorrectionRequest::STATUS_WITHDRAWN, 'resolved_by' => auth()->id(), 'resolved_at' => now(), 'resolution_comment' => 'Withdrawn by project manager.']);
            $audit->record('timesheet_correction_withdrawn', $locked, null, $locked->fresh()->toArray());
        });
        $notifications->resolved($correctionRequest->fresh());
        return back()->with('success', 'Correction request withdrawn.');
    }

    public function resolve(Request $request, Timesheet $timesheet, AuditLogService $audit, TimesheetCorrectionNotificationService $notifications, TimesheetEmailNotificationService $emails, TimesheetRecallService $recalls, TimesheetStatusHistoryService $history)
    {
        $this->authorizeResolution($timesheet, $request->user());
        $data = $request->validate(['decisions' => ['required', 'array', 'min:1'], 'decisions.*' => ['required', 'in:accepted,dismissed'], 'dismissal_comments' => ['nullable', 'array'], 'dismissal_comments.*' => ['nullable', 'string', 'min:3', 'max:2000']]);

        $resolved = DB::transaction(function () use ($timesheet, $request, $data, $audit, $emails, $recalls, $history) {
            $locked = Timesheet::lockForUpdate()->findOrFail($timesheet->id);
            abort_unless(in_array($locked->status, [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED], true), 422);
            $open = TimesheetCorrectionRequest::where('timesheet_id', $locked->id)->where('status', 'open')->lockForUpdate()->get();
            if ($open->isEmpty() || $open->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all() !== collect(array_keys($data['decisions']))->map(fn ($id) => (string) $id)->sort()->values()->all()) throw ValidationException::withMessages(['decisions' => 'Resolve every open correction request together. Refresh and try again.']);
            foreach ($open as $item) if ($data['decisions'][$item->id] === 'dismissed' && blank($data['dismissal_comments'][$item->id] ?? null)) throw ValidationException::withMessages(["dismissal_comments.{$item->id}" => 'A dismissal reason is required.']);
            $accepted = $open->filter(fn ($item) => $data['decisions'][$item->id] === 'accepted');
            foreach ($open as $item) {
                $status = $data['decisions'][$item->id];
                $comment = $status === 'dismissed' ? $data['dismissal_comments'][$item->id] : 'Accepted for employee correction.';
                $item->update(['status' => $status, 'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'resolution_comment' => $comment, 'authority_role' => $request->user()->role]);
                $audit->record('timesheet_correction_'.$status, $item, null, $item->fresh()->toArray());
            }
            if ($accepted->isNotEmpty()) {
                $comment = $accepted->map(fn ($item) => $item->comment)->implode("\n\n");
                if ($locked->status === Timesheet::STATUS_SUBMITTED) {
                    $old = $locked->toArray();
                    $locked->update(['status' => Timesheet::STATUS_REJECTED, 'rejected_at' => now(), 'rejected_by' => $request->user()->id, 'rejection_comment' => $comment, 'approved_at' => null, 'approved_by' => null]);
                    $history->record('timesheet_rejected', $locked, $old, $locked->fresh()->toArray()); $audit->record('timesheet_rejected', $locked, $old, $locked->fresh()->toArray()); $emails->rejected($locked);
                } else $recalls->recallApproved($locked, $request->user(), $comment, $audit, $emails, $history);
            }
            return $open;
        });
        $resolved->each(fn ($item) => $notifications->resolved($item->fresh()));
        return back()->with('success', $resolved->contains('status', 'accepted') ? 'Requests resolved and the timesheet returned for correction.' : 'All correction requests dismissed. The timesheet may now be approved.');
    }

    private function authorizeResolution(Timesheet $timesheet, $actor): void
    {
        $timesheet->loadMissing('user');
        abort_if((int) $timesheet->user_id === (int) $actor->id, 403);
        if ($actor->isSuperAdmin()) return;
        if ($actor->role === 'admin') { abort_if($timesheet->user?->role === 'hod' && $this->adminExclusions->approvalExcluded($actor, $timesheet->user), 403); return; }
        abort_unless($actor->role === 'hod' && $actor->managedDepartmentIds()->contains((int) $timesheet->department_id), 403);
        abort_unless($timesheet->user?->role === 'employee', 403, 'Head of Department can only resolve correction requests for employee timesheets.');
        abort_if($this->hodExclusions->approvalExcluded($actor, $timesheet->user), 403);
    }
}
