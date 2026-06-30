@php
    $historyActions = [
        'leave_plan_submitted' => ['title' => 'Approval Requested', 'icon' => 'send', 'tone' => 'info'],
        'leave_plan_resubmitted' => ['title' => 'Approval Requested Again', 'icon' => 'send', 'tone' => 'info'],
        'leave_plan_stage_approved' => ['title' => 'Approval Stage Completed', 'icon' => 'check-circle', 'tone' => 'success'],
        'leave_plan_approved' => ['title' => 'Leave Plan Approved', 'icon' => 'check-circle', 'tone' => 'success'],
        'leave_plan_rejected' => ['title' => 'Leave Plan Rejected', 'icon' => 'x-circle', 'tone' => 'danger'],
        'leave_plan_cancellation_requested' => ['title' => 'Cancellation Requested', 'icon' => 'undo', 'tone' => 'warning'],
        'leave_plan_cancellation_approved' => ['title' => 'Cancellation Approved', 'icon' => 'check-circle', 'tone' => 'success'],
        'leave_plan_cancellation_rejected' => ['title' => 'Cancellation Rejected', 'icon' => 'x-circle', 'tone' => 'danger'],
        'leave_plan_approved_recalled' => ['title' => 'Approved Leave Plan Recalled', 'icon' => 'rotate-left', 'tone' => 'recall'],
        'leave_plan_voided' => ['title' => 'Leave Plan Voided', 'icon' => 'ban', 'tone' => 'void'],
    ];
    $history = $leavePlan->statusHistories
        ->filter(fn ($log) => isset($historyActions[$log->action]))
        ->sortByDesc('occurred_at')
        ->values();
    $canSeeIp = auth()->user()?->role === 'super_admin';
    $stageLabel = fn ($stage) => match ($stage) {
        \App\Models\LeavePlan::APPROVAL_STAGE_HOD => 'Head of Department',
        \App\Models\LeavePlan::APPROVAL_STAGE_DIRECTOR => 'Director',
        \App\Models\LeavePlan::APPROVAL_STAGE_HR => 'HR Department',
        default => $stage ? str_replace('_', ' ', $stage) : null,
    };
@endphp

@if($history->isEmpty())
    <div class="empty-state py-3">No history has been recorded yet.</div>
@else
    <ol class="timeline-list">
        @foreach($history as $log)
            @php
                $item = $historyActions[$log->action];
                $comment = $log->comment;
                $oldStatus = $log->old_status;
                $newStatus = $log->new_status;
                $oldStage = $stageLabel($log->old_approval_stage);
                $newStage = $stageLabel($log->new_approval_stage);
                $occurredAt = $log->occurred_at ?? $log->created_at;
            @endphp
            <li class="timeline-item">
                <span class="timeline-marker timeline-marker-{{ $item['tone'] }}" aria-hidden="true">
                    <svg class="timeline-marker-icon timeline-marker-icon-{{ $item['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        @switch($item['icon'])
                            @case('send')
                                <path d="M22 2 11 13" />
                                <path d="m22 2-7 20-4-9-9-4 20-7Z" />
                                @break
                            @case('check-circle')
                                <circle cx="12" cy="12" r="9" />
                                <path d="m8.5 12.5 2.3 2.3 4.9-5.1" />
                                @break
                            @case('x-circle')
                                <circle cx="12" cy="12" r="9" />
                                <path d="m9 9 6 6" />
                                <path d="m15 9-6 6" />
                                @break
                            @case('undo')
                                <path d="M8.2 7.2H4.2V3.2" />
                                <path d="M5.1 7.2a7 7 0 1 1-1.1 4" />
                                @break
                            @case('rotate-left')
                                <path d="M3 12a9 9 0 1 0 3-6.7" />
                                <path d="M3 4v6h6" />
                                @break
                            @case('ban')
                                <circle cx="12" cy="12" r="9" />
                                <path d="m5.7 5.7 12.6 12.6" />
                                @break
                        @endswitch
                    </svg>
                </span>
                <div class="timeline-content">
                    <div class="timeline-date">{{ $occurredAt->isToday() ? 'Today' : $occurredAt->format('M j, Y') }}</div>
                    <div class="timeline-title">{{ $item['title'] }}</div>
                    <div class="timeline-copy">
                        {{ $log->user?->name ?? 'System' }}
                        @if($oldStatus && $newStatus && $oldStatus !== $newStatus)
                            changed status from {{ str_replace('_', ' ', $oldStatus) }} to {{ str_replace('_', ' ', $newStatus) }}.
                        @elseif($oldStage && $newStage && $oldStage !== $newStage)
                            moved approval from {{ $oldStage }} to {{ $newStage }}.
                        @elseif($newStage)
                            routed this plan to {{ $newStage }} review.
                        @else
                            recorded this action.
                        @endif
                    </div>
                    @if($comment)
                        <div class="timeline-comment">{{ $comment }}</div>
                    @endif
                    <div class="timeline-meta">
                        {{ $occurredAt->format('M j, Y g:i A') }}
                        @if($canSeeIp && $log->ip_address)
                            <span class="mx-1">|</span> IP {{ $log->ip_address }}
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ol>
@endif
