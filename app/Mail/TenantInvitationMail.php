<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one-time link: set up an institution (REGISTER) or choose a password (ACTIVATE).
 */
class TenantInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TenantInvitation $invitation, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->invitation->purpose === 'REGISTER' ? 'Set up your institution on '.config('app.name') : 'Your '.config('app.name').' account is ready');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.tenant-invitation');
    }
}
