<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Part 20.3 — a scheduled report delivered as a CSV attachment with a
 * plain-text covering note.
 */
class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reportTitle,
        public string $periodFrom,
        public string $periodTo,
        public int $rowCount,
        public string $branchName,
        public string $csv,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->reportTitle} — {$this->periodFrom} to {$this->periodTo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.scheduled-report',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->csv, $this->filename)->withMime('text/csv'),
        ];
    }
}
