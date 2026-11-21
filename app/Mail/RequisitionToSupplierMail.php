<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A purchase request sent to a supplier for quotation: the sender's own
 * covering message and the request as a PDF without any prices.
 */
class RequisitionToSupplierMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $docNumber,
        public string $organisationName,
        public string $branchName,
        public string $supplierName,
        public string $messageBody,
        public string $pdfPath,
        public ?string $replyToAddress = null,
        public ?string $replyToName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Request for quotation {$this->docNumber} from {$this->organisationName}",
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress, $this->replyToName ?? '')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.requisition-to-supplier');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->pdfPath)->as("{$this->docNumber}.pdf")->withMime('application/pdf'),
        ];
    }
}
