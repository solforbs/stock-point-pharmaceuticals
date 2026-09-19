<?php

namespace App\Services\Sales;

use App\Mail\CustomerOrderUpdateMail;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organisation;
use App\Models\SalesOrder;
use App\Models\SalesOrderUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Part 7 — keeping the customer in the picture.
 *
 * A wholesale customer's question is almost always "where is my order?", and
 * the warehouse already knows: it is reserved, being picked, packed, on its
 * way, delivered. Each of those moments is sent to the customer once and
 * recorded, so the answer exists even when nobody is at the phone.
 *
 * A customer is not a user of this system, so the only way to reach them is
 * the address on their record. A missing one is recorded as such rather than
 * passing silently, and a mail server that refuses never fails the warehouse
 * action that triggered it — the goods still moved.
 */
class CustomerOrderNotifier
{
    /**
     * Tells the customer an order has reached a milestone. Safe to call
     * twice: the same milestone is only ever announced once.
     */
    public function announce(SalesOrder $order, string $milestone, ?string $note = null): ?SalesOrderUpdate
    {
        if (! array_key_exists($milestone, SalesOrderUpdate::MILESTONES)) {
            return null;
        }

        $order->loadMissing(['customer', 'lines.product:id,code,name', 'lines.uom:id,code']);
        $customer = $order->customer;

        if ($customer === null) {
            return null; // a counter sale has nobody to write to
        }

        $existing = SalesOrderUpdate::where('sales_order_id', $order->id)->where('milestone', $milestone)->first();
        if ($existing !== null) {
            return $existing;
        }

        $address = trim((string) ($customer->email ?? ''));

        $update = SalesOrderUpdate::create([
            'sales_order_id' => $order->id,
            'milestone' => $milestone,
            'channel' => 'EMAIL',
            'recipient' => $address !== '' ? $address : null,
            'note' => $note,
            'sent_at' => null,
            'failure_reason' => $address === '' ? 'The customer has no email address on file.' : null,
        ]);

        if ($address === '') {
            return $update;
        }

        try {
            Mail::to($address, $customer->name)->send(new CustomerOrderUpdateMail(
                $order,
                $milestone,
                $this->timelineFor($order, $milestone),
                $this->organisationName($order),
                $note,
            ));

            $update->update(['sent_at' => now()]);

            AuditLog::record('CUSTOMER_ORDER_UPDATE_SENT', 'sales_order', $order->id, [
                'reference' => $order->doc_number,
                'after_json' => ['milestone' => $milestone, 'to' => $address],
            ]);
        } catch (\Throwable $e) {
            $update->update(['failure_reason' => mb_substr($e->getMessage(), 0, 255)]);
            Log::error('Customer order update failed', ['order' => $order->doc_number, 'milestone' => $milestone, 'error' => $e->getMessage()]);
        }

        return $update->fresh();
    }

    /**
     * The journey so far, for the email: every milestone with the date it
     * happened, so the customer sees progress rather than one bare line.
     *
     * @return list<array{milestone: string, label: string, reached: bool, at: ?string, current: bool}>
     */
    public function timelineFor(SalesOrder $order, string $current): array
    {
        $reached = SalesOrderUpdate::where('sales_order_id', $order->id)->pluck('created_at', 'milestone');
        $steps = [];

        // A cancelled order has left the normal journey; showing picking and
        // delivery steps after it would be nonsense.
        $sequence = $current === 'CANCELLED'
            ? ['CONFIRMED', 'CANCELLED']
            : ['CONFIRMED', 'PICKING', 'PACKED', 'DISPATCHED', 'DELIVERED'];

        foreach ($sequence as $milestone) {
            $at = $milestone === $current ? now() : ($reached[$milestone] ?? null);
            $steps[] = [
                'milestone' => $milestone,
                'label' => SalesOrderUpdate::MILESTONES[$milestone],
                'reached' => $at !== null,
                'at' => $at?->format('j M Y, H:i'),
                'current' => $milestone === $current,
            ];
        }

        return $steps;
    }

    private function organisationName(SalesOrder $order): string
    {
        $organisationId = Branch::where('id', $order->branch_id)->value('organisation_id');

        return (string) (Organisation::where('id', $organisationId)->value('name') ?? config('app.name'));
    }
}
