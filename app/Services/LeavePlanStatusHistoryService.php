<?php

namespace App\Services;

use App\Models\LeavePlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class LeavePlanStatusHistoryService
{
    public function record(string $action, LeavePlan $leavePlan, ?array $oldValues = null, ?array $newValues = null, ?string $comment = null): void
    {
        $leavePlan->statusHistories()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'old_status' => $oldValues['status'] ?? null,
            'new_status' => $newValues['status'] ?? null,
            'old_approval_stage' => $oldValues['approval_stage'] ?? null,
            'new_approval_stage' => $newValues['approval_stage'] ?? null,
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
            ?? $values['cancellation_reason']
            ?? $values['cancellation_rejection_comment']
            ?? $values['rejection_comment']
            ?? $values['void_reason']
            ?? null;
    }
}
