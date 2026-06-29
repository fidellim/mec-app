<?php

namespace App\Services;

use App\Mail\LeavePlanWorkflowMail;
use App\Models\LeavePlan;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LeavePlanEmailNotificationService
{
    public function __construct(
        private readonly HodExclusionService $hodExclusions,
        private readonly LeavePlanApprovalService $approvals,
    )
    {
    }

    public function submitted(LeavePlan $leavePlan, bool $resubmitted = false): void
    {
        $leavePlan = $this->loadLeavePlan($leavePlan);

        $this->sendToHods($leavePlan, fn () => new LeavePlanWorkflowMail(
            leavePlan: $leavePlan,
            headline: $resubmitted ? 'Leave plan resubmitted for approval' : 'Leave plan submitted for approval',
            intro: $leavePlan->user->name.' submitted a leave plan for '.$this->dateRange($leavePlan).'.',
            actionLabel: 'Review Leave Plan',
            actionUrl: route('hod.leave-plans.show', $leavePlan),
        ));
    }

    public function approved(LeavePlan $leavePlan): void
    {
        $leavePlan = $this->loadLeavePlan($leavePlan);

        $this->send($leavePlan->user, new LeavePlanWorkflowMail(
            leavePlan: $leavePlan,
            headline: 'Leave plan approved',
            intro: 'Your leave plan for '.$this->dateRange($leavePlan).' has been approved.',
            actionLabel: 'View Leave Plan',
            actionUrl: route('employee.leave-plans.show', $leavePlan),
        ));
    }

    public function stagePending(LeavePlan $leavePlan): void
    {
        $leavePlan = $this->loadLeavePlan($leavePlan);

        if ($leavePlan->approval_stage === LeavePlan::APPROVAL_STAGE_DIRECTOR) {
            $this->send($this->approvals->director(), new LeavePlanWorkflowMail(
                leavePlan: $leavePlan,
                headline: 'Leave plan pending Director approval',
                intro: $leavePlan->user->name.' has a leave plan waiting for Director review for '.$this->dateRange($leavePlan).'.',
                actionLabel: 'Review Leave Plan',
                actionUrl: route('assigned.leave-plans.show', $leavePlan),
            ));

            return;
        }

        if ($leavePlan->approval_stage === LeavePlan::APPROVAL_STAGE_HR) {
            $this->send($this->approvals->hrFor($leavePlan), new LeavePlanWorkflowMail(
                leavePlan: $leavePlan,
                headline: 'Leave plan pending HR approval',
                intro: $leavePlan->user->name.' has a leave plan waiting for regional HR review for '.$this->dateRange($leavePlan).'.',
                actionLabel: 'Review Leave Plan',
                actionUrl: route('assigned.leave-plans.show', $leavePlan),
            ));
        }
    }

    public function rejected(LeavePlan $leavePlan): void
    {
        $leavePlan = $this->loadLeavePlan($leavePlan);

        $this->send($leavePlan->user, new LeavePlanWorkflowMail(
            leavePlan: $leavePlan,
            headline: 'Leave plan rejected',
            intro: 'Your leave plan for '.$this->dateRange($leavePlan).' was rejected and needs your action.',
            actionLabel: 'Edit Leave Plan',
            actionUrl: route('employee.leave-plans.edit', $leavePlan),
            comment: $leavePlan->rejection_comment,
        ));
    }

    public function cancellationRequested(LeavePlan $leavePlan): void
    {
        $leavePlan = $this->loadLeavePlan($leavePlan);

        $this->sendToHods($leavePlan, fn () => new LeavePlanWorkflowMail(
            leavePlan: $leavePlan,
            headline: 'Leave plan cancellation requested',
            intro: $leavePlan->user->name.' requested cancellation for '.$this->dateRange($leavePlan).'.',
            actionLabel: 'Review Cancellation',
            actionUrl: route('hod.leave-plans.show', $leavePlan),
            comment: $leavePlan->cancellation_reason,
        ));
    }

    public function cancellationApproved(LeavePlan $leavePlan): void
    {
        $leavePlan = $this->loadLeavePlan($leavePlan);

        $this->send($leavePlan->user, new LeavePlanWorkflowMail(
            leavePlan: $leavePlan,
            headline: 'Leave plan cancellation approved',
            intro: 'Your cancellation request for '.$this->dateRange($leavePlan).' has been approved.',
            actionLabel: 'View Leave Plan',
            actionUrl: route('employee.leave-plans.show', $leavePlan),
        ));
    }

    public function cancellationRejected(LeavePlan $leavePlan): void
    {
        $leavePlan = $this->loadLeavePlan($leavePlan);

        $this->send($leavePlan->user, new LeavePlanWorkflowMail(
            leavePlan: $leavePlan,
            headline: 'Leave plan cancellation rejected',
            intro: 'Your cancellation request for '.$this->dateRange($leavePlan).' was rejected.',
            actionLabel: 'View Leave Plan',
            actionUrl: route('employee.leave-plans.show', $leavePlan),
            comment: $leavePlan->cancellation_rejection_comment,
        ));
    }

    public function recalled(LeavePlan $leavePlan): void
    {
        $leavePlan = $this->loadLeavePlan($leavePlan);

        $this->send($leavePlan->user, new LeavePlanWorkflowMail(
            leavePlan: $leavePlan,
            headline: 'Approved leave plan recalled',
            intro: 'Your approved leave plan for '.$this->dateRange($leavePlan).' was recalled for correction.',
            actionLabel: 'Edit Leave Plan',
            actionUrl: route('employee.leave-plans.edit', $leavePlan),
            comment: $leavePlan->recall_reason,
        ));
    }


    private function send(?User $recipient, LeavePlanWorkflowMail $mail): void
    {
        if (! $recipient?->email || ! $recipient->is_active) {
            return;
        }

        try {
            Mail::to($recipient->email)->queue($mail);
        } catch (\Throwable $exception) {
            Log::warning('Leave plan email notification failed.', [
                'recipient_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'subject' => $mail->envelope()->subject,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sendToHods(LeavePlan $leavePlan, \Closure $mailFactory): void
    {
        $recipients = $leavePlan->department?->hods ?? collect();

        if ($leavePlan->department?->hod) {
            $recipients = $recipients->push($leavePlan->department->hod);
        }

        $recipients
            ->unique('id')
            ->filter(fn (User $recipient) => $recipient->role === 'hod')
            ->reject(fn (User $recipient) => (int) $recipient->id === (int) $leavePlan->user_id)
            ->filter(fn (User $recipient) => $leavePlan->user && $this->hodExclusions->shouldReceiveApprovalRequestEmail($recipient, $leavePlan->user))
            ->each(fn (User $recipient) => $this->send($recipient, $mailFactory()));
    }

    private function loadLeavePlan(LeavePlan $leavePlan): LeavePlan
    {
        return $leavePlan->fresh(['user', 'department.hod', 'department.hods']) ?? $leavePlan->load(['user', 'department.hod', 'department.hods']);
    }

    private function dateRange(LeavePlan $leavePlan): string
    {
        if ($leavePlan->start_date->isSameDay($leavePlan->end_date)) {
            return $leavePlan->start_date->format('M d, Y');
        }

        return $leavePlan->start_date->format('M d, Y').' to '.$leavePlan->end_date->format('M d, Y');
    }
}
