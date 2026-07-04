<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReportFailedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reportId,
        public readonly string $errorMessage,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your candidacy report could not be generated',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.report-failed',
            with: [
                'reportId' => $this->reportId,
                'errorMessage' => $this->errorMessage,
            ],
        );
    }
}
