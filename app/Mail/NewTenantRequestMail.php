<?php

namespace App\Mail;

use App\Models\TenantRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerts the platform administrators to a new quote request awaiting review.
 */
class NewTenantRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TenantRequest $tenantRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New quote request: '.$this->tenantRequest->institution_name);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.new-tenant-request');
    }
}
