<?php

namespace App\Mail;

use App\Models\TenantRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an institution its quote request was not approved, and why.
 */
class TenantRequestRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TenantRequest $tenantRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your request for '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.tenant-request-rejected');
    }
}
