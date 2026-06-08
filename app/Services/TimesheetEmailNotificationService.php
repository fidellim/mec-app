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

        if ($timesheet->user?->role === 'hod') {
            $this->sendToAdmins(fn () => new TimesheetWorkflowMail(
                timesheet: $timesheet,
                headline: $resubmitted ? 'HOD timesheet resubmitted for approval' : 'HOD timesheet submitted for approval',
                intro: $timesheet->user->name.' submitted a timesheet for Week '.$timesheet->period->week_number.', '.$timesheet->period->year.'.',
                actionLabel: 'Review Timesheet',
                actionUrl: route('admin.timesheets.show', $timesheet),
            ));

            $this->send($timesheet->user, new TimesheetWorkflowMail(
                timesheet: $timesheet,
                headline: $resubmitted ? 'Your timesheet was resubmitted' : 'Your timesheet was submitted',
                intro: 'Your timesheet for Week '.$timesheet->period->week_number.', '.$timesheet->period->year.' was submitted for admin approval.',
                actionLabel: 'View Timesheet',
                actionUrl: route('employee.timesheets.show', $timesheet),
            ));

            return;
        }

        $this->sendToHods($timesheet, fn () => new TimesheetWorkflowMail(
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

        $this->sendToHods($timesheet, fn () => new TimesheetWorkflowMail(
            timesheet: $timesheet,
            headline: 'Timesheet recalled by employee',
            intro: $timesheet->user->name.' recalled a submitted timesheet for Week '.$timesheet->period->week_number.', '.$timesheet->period->year.'.',
            actionLabel: 'View Department Timesheets',
            actionUrl: route('hod.timesheets.index', [
                'employee_id' => $timesheet->user_id,
                'week_number' => $timesheet->period->week_number,
                'year' => $timesheet->period->year,
                'department_id' => $timesheet->department_id,
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
            Mail::to($recipient->email)->queue($mail);
        } catch (\Throwable $exception) {
            Log::warning('Timesheet email notification failed.', [
                'recipient_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'subject' => $mail->envelope()->subject,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sendToHods(Timesheet $timesheet, \Closure $mailFactory): void
    {
        $recipients = $timesheet->department?->hods ?? collect();

        if ($timesheet->department?->hod) {
            $recipients = $recipients->push($timesheet->department->hod);
        }

        $recipients
            ->unique('id')
            ->filter(fn (User $recipient) => $recipient->role === 'hod')
            ->each(fn (User $recipient) => $this->send($recipient, $mailFactory()));
    }

    private function sendToAdmins(\Closure $mailFactory): void
    {
        User::where(function ($query) {
            $query->where('role', 'admin')
                ->orWhere(function ($query) {
                    $query->where('role', 'super_admin')
                        ->where('receives_hod_timesheet_submission_emails', true);
                });
        })
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->each(fn (User $recipient) => $this->send($recipient, $mailFactory()));
    }

    private function loadTimesheet(Timesheet $timesheet): Timesheet
    {
        return $timesheet->fresh(['user', 'department.hod', 'department.hods', 'period']) ?? $timesheet->load(['user', 'department.hod', 'department.hods', 'period']);
    }
}
