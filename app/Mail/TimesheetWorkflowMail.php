<?php

namespace App\Mail;

use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TimesheetWorkflowMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Timesheet $timesheet,
        public readonly string $headline,
        public readonly string $intro,
        public readonly string $actionLabel,
        public readonly string $actionUrl,
        public readonly ?string $comment = null
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.timesheet-workflow');
    }
}
