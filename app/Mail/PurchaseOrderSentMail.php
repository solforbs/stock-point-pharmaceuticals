<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Part 9 — the order as the supplier receives it. This mail is what starts
 * the supplier's dispatch, so it carries everything needed to pick and
 * deliver: what, how much, in which unit, at what price, and by when.
 */
class PurchaseOrderSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public string $organisationName,
        public string $branchName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Purchase order {$this->purchaseOrder->doc_number} from {$this->organisationName}");
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.purchase-order-sent',
            with: ['lines' => $this->purchaseOrder->lines],
        );
    }
}
