<?php

namespace App\Services;

use App\Models\Timesheet;
use App\Models\TimesheetCorrectionRequest;
use Illuminate\Support\Facades\DB;

class TimesheetCorrectionLifecycleService
{
    public function supersedeOpen(Timesheet $timesheet): void
    {
        if (in_array($timesheet->status, [Timesheet::STATUS_SUBMITTED, Timesheet::STATUS_APPROVED], true)) return;

        $requests = TimesheetCorrectionRequest::where('timesheet_id', $timesheet->id)
            ->where('status', TimesheetCorrectionRequest::STATUS_OPEN)->get();
        if ($requests->isEmpty()) return;

        $comment = 'Superseded when the timesheet changed to '.$timesheet->status.'.';
        DB::table('timesheet_correction_requests')->whereIn('id', $requests->pluck('id'))->update([
            'status' => TimesheetCorrectionRequest::STATUS_SUPERSEDED,
            'resolution_comment' => $comment,
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);

        $audit = app(AuditLogService::class);
        $notifications = app(TimesheetCorrectionNotificationService::class);
        foreach ($requests as $request) {
            $request->forceFill(['status' => TimesheetCorrectionRequest::STATUS_SUPERSEDED, 'resolution_comment' => $comment, 'resolved_at' => now()]);
            $audit->record('timesheet_correction_superseded', $request, null, $request->toArray());
            $notifications->resolved($request);
        }
    }
}
