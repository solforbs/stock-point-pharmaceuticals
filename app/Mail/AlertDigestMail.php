<?php

namespace App\Mail;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Part 17 — the morning alert digest: what is falling due and what is
 * running out of shelf life, for one recipient and only the alerts that
 * recipient is allowed to see.
 */
class AlertDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Alert>  $alerts
     */
    public function __construct(
        public string $recipientName,
        public string $branchName,
        public Collection $alerts,
        public string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        $critical = $this->alerts->where('severity', 'CRITICAL')->count();
        $subject = $critical > 0
            ? "{$this->branchName}: {$critical} critical of {$this->alerts->count()} alerts"
            : "{$this->branchName}: {$this->alerts->count()} alerts need attention";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.alert-digest',
            with: [
                'groups' => $this->alerts->groupBy('category'),
                'headings' => [
                    'RECEIVABLE' => 'Customer invoices falling due',
                    'PAYABLE' => 'Supplier invoices falling due',
                    'EXPIRY' => 'Stock running out of shelf life',
                ],
            ],
        );
    }
}
