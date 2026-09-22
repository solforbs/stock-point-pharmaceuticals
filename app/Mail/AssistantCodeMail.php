<?php

namespace App\Mail;

use App\Models\AssistantSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one-time code that opens a read-only assistant session.
 */
class AssistantCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code, public AssistantSession $session) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your '.config('app.name').' assistant code');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.assistant-code');
    }
}
