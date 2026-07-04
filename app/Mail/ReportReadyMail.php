<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReportReadyMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reportId,
        public readonly string $downloadUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your candidacy report is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.report-ready',
            with: [
                'reportId' => $this->reportId,
                'downloadUrl' => $this->downloadUrl,
            ],
        );
    }
}
