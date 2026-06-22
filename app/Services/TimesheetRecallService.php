<?php

namespace App\Services;

use App\Models\Timesheet;
use App\Models\User;

class TimesheetRecallService
{
    public function recallApproved(
        Timesheet $timesheet,
        User $actor,
        string $reason,
        AuditLogService $audit,
        TimesheetEmailNotificationService $emails,
        TimesheetStatusHistoryService $history
    ): void {
        $old = $timesheet->toArray();

        $timesheet->update([
            'status' => Timesheet::STATUS_RECALLED,
        ]);

        $new = $timesheet->fresh()->toArray();
        $new['recall_reason'] = $reason;
        $new['recalled_by'] = $actor->id;
        $audit->record('timesheet_approved_recalled', $timesheet, $old, $new);
        $history->record('timesheet_approved_recalled', $timesheet, $old, $new);

        $emails->approvedRecalled($timesheet, $reason);
    }
}
