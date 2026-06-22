<?php

namespace App\Services;

use App\Models\Timesheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class TimesheetStatusHistoryService
{
    public function record(string $action, Timesheet $timesheet, ?array $oldValues = null, ?array $newValues = null, ?string $comment = null): void
    {
        $timesheet->statusHistories()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'old_status' => $oldValues['status'] ?? null,
            'new_status' => $newValues['status'] ?? null,
            'comment' => $comment ?? $this->commentFrom($newValues ?? []),
            'ip_address' => Request::ip(),
            'metadata' => [
                'source' => 'workflow',
            ],
            'occurred_at' => now(),
        ]);
    }

    private function commentFrom(array $values): ?string
    {
        return $values['recall_reason']
            ?? $values['withdrawal_comment']
            ?? $values['rejection_comment']
            ?? $values['void_reason']
            ?? null;
    }
}
