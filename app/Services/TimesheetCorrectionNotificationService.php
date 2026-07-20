<?php

namespace App\Services;

use App\Mail\TimesheetWorkflowMail;
use App\Models\TimesheetCorrectionRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TimesheetCorrectionNotificationService
{
    public function __construct(
        private readonly HodExclusionService $hodExclusions,
        private readonly AdminExclusionService $adminExclusions,
    ) {}

    public function submitted(TimesheetCorrectionRequest $request): void
    {
        $request->loadMissing('timesheet.user', 'timesheet.period', 'timesheet.department.hod', 'timesheet.department.hods', 'requester');

        if ($request->timesheet->user?->role === 'hod') {
            User::query()->whereIn('role', ['admin', 'super_admin'])
                ->where('is_active', true)
                ->where('receives_hod_timesheet_submission_emails', true)
                ->orderBy('id')->get()
                ->filter(fn (User $recipient) => $this->adminExclusions->shouldReceiveHodSubmissionEmail($recipient, $request->timesheet->user))
                ->each(fn (User $recipient) => $this->send($recipient, $this->reviewMail($request, route('admin.timesheets.show', $request->timesheet))));

            return;
        }

        $recipients = $request->timesheet->department?->hods ?? collect();
        if ($request->timesheet->department?->hod) $recipients->push($request->timesheet->department->hod);

        $recipients->unique('id')->filter(fn ($user) => $user->is_active && $user->role === 'hod')
            ->filter(fn ($hod) => ! $this->hodExclusions->visibilityExcluded($hod, $request->timesheet->user))
            ->each(fn ($hod) => $this->send($hod, $this->reviewMail($request, route('hod.timesheets.show', $request->timesheet))));
    }

    public function resolved(TimesheetCorrectionRequest $request): void
    {
        $request->loadMissing('timesheet.user', 'timesheet.period', 'requester', 'resolver');
        $label = ucfirst($request->status);
        $this->send($request->requester, new TimesheetWorkflowMail(
            $request->timesheet,
            'Correction request '.$request->status,
            ($request->resolver?->name ?? 'The workflow').' marked your correction request as '.$request->status.'.',
            'View Project',
            route('projects.utilization', $request->entries()->value('project_id')),
            $request->resolution_comment,
        ));
    }

    private function send(?User $recipient, TimesheetWorkflowMail $mail): void
    {
        if (! $recipient?->email || ! $recipient->is_active) return;
        try { Mail::to($recipient->email)->queue($mail->afterCommit()); }
        catch (\Throwable $e) { Log::warning('Correction request email failed.', ['recipient_id' => $recipient->id, 'message' => $e->getMessage()]); }
    }

    private function reviewMail(TimesheetCorrectionRequest $request, string $url): TimesheetWorkflowMail
    {
        return new TimesheetWorkflowMail(
            $request->timesheet,
            'Timesheet correction request needs review',
            $request->requester->name.' flagged '.$request->entries()->count().' project entries on '.$request->timesheet->user->name.'\'s timesheet.',
            'Review Timesheet',
            $url,
            $request->comment,
        );
    }
}
