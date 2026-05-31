<?php

namespace App\Mail;

use App\Models\TimesheetPeriod;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MissingTimesheetReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $employee,
        public readonly TimesheetPeriod $period,
        public readonly string $actionUrl,
        public readonly string $sourceLabel = 'Head of Department'
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Timesheet reminder for Week '.$this->period->week_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.missing-timesheet-reminder');
    }
}
