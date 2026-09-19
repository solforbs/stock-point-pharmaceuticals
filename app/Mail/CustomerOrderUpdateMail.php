<?php

namespace App\Mail;

use App\Models\SalesOrder;
use App\Models\SalesOrderUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Part 7 — what the customer receives when their order moves on. Written for
 * someone outside the business: no document jargon, no internal status
 * names, and everything needed to recognise the order.
 */
class CustomerOrderUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{milestone: string, label: string, reached: bool, at: ?string, current: bool}>  $timeline
     */
    public function __construct(
        public SalesOrder $order,
        public string $milestone,
        public array $timeline,
        public string $organisationName,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        $headline = match ($this->milestone) {
            'CONFIRMED' => 'confirmed',
            'PICKING' => 'is being prepared',
            'PACKED' => 'is packed and ready',
            'DISPATCHED' => 'is on its way',
            'DELIVERED' => 'has been delivered',
            'CANCELLED' => 'has been cancelled',
            default => 'has been updated',
        };

        return new Envelope(subject: "Order {$this->order->doc_number} {$headline}");
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.customer-order-update',
            with: ['label' => SalesOrderUpdate::MILESTONES[$this->milestone] ?? 'Updated'],
        );
    }
}
