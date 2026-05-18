<?php

namespace App\Services;

use App\Mail\TimesheetWorkflowMail;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TimesheetEmailNotificationService
{
    public function submitted(Timesheet $timesheet, bool $resubmitted = false): void
    {
        $timesheet = $this->loadTimesheet($timesheet);
        $recipient = $timesheet->department?->hod;

        $this->send($recipient, new TimesheetWorkflowMail(
            timesheet: $timesheet,
            headline: $resubmitted ? 'Timesheet resubmitted for approval' : 'Timesheet submitted for approval',
            intro: $timesheet->user->name.' submitted a timesheet for Week '.$timesheet->period->week_number.', '.$timesheet->period->year.'.',
            actionLabel: 'Review Timesheet',
            actionUrl: route('hod.timesheets.show', $timesheet),
        ));
    }

    public function recalled(Timesheet $timesheet): void
    {
        $timesheet = $this->loadTimesheet($timesheet);
        $recipient = $timesheet->department?->hod;

        $this->send($recipient, new TimesheetWorkflowMail(
            timesheet: $timesheet,
            headline: 'Timesheet recalled by employee',
            intro: $timesheet->user->name.' recalled a submitted timesheet for Week '.$timesheet->period->week_number.', '.$timesheet->period->year.'.',
            actionLabel: 'View Department Timesheets',
            actionUrl: route('hod.timesheets.index', [
                'employee_id' => $timesheet->user_id,
                'week_number' => $timesheet->period->week_number,
                'year' => $timesheet->period->year,
            ]),
        ));
    }

    public function approved(Timesheet $timesheet): void
    {
        $timesheet = $this->loadTimesheet($timesheet);

        $this->send($timesheet->user, new TimesheetWorkflowMail(
            timesheet: $timesheet,
            headline: 'Timesheet approved',
            intro: 'Your timesheet for Week '.$timesheet->period->week_number.', '.$timesheet->period->year.' has been approved.',
            actionLabel: 'View Timesheet',
            actionUrl: route('employee.timesheets.show', $timesheet),
        ));
    }

    public function rejected(Timesheet $timesheet): void
    {
        $timesheet = $this->loadTimesheet($timesheet);

        $this->send($timesheet->user, new TimesheetWorkflowMail(
            timesheet: $timesheet,
            headline: 'Timesheet rejected',
            intro: 'Your timesheet for Week '.$timesheet->period->week_number.', '.$timesheet->period->year.' was rejected and needs your action.',
            actionLabel: 'Edit Timesheet',
            actionUrl: route('employee.timesheets.edit', $timesheet),
            comment: $timesheet->rejection_comment,
        ));
    }

    private function send(?User $recipient, TimesheetWorkflowMail $mail): void
    {
        if (! $recipient?->email || ! $recipient->is_active) {
            return;
        }

        try {
            Mail::to($recipient->email)->send($mail);
        } catch (\Throwable $exception) {
            Log::warning('Timesheet email notification failed.', [
                'recipient_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'subject' => $mail->envelope()->subject,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function loadTimesheet(Timesheet $timesheet): Timesheet
    {
        return $timesheet->fresh(['user', 'department.hod', 'period']) ?? $timesheet->load(['user', 'department.hod', 'period']);
    }
}
