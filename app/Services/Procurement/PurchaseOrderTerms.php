<?php

namespace App\Services\Procurement;

use App\Models\PurchaseOrder;

/**
 * The terms and conditions printed on every purchase order, in the PDF and
 * the Excel download alike. The organisation and delivery branch are filled
 * in from the order so each tenant's suppliers see their own names.
 */
final class PurchaseOrderTerms
{
    /**
     * @return list<string>
     */
    public static function for(PurchaseOrder $order): array
    {
        $order->loadMissing('branch.organisation');

        $organisation = $order->branch?->organisation?->name ?? config('app.name');
        $branch = $order->branch?->name ?? 'our store';

        return [
            "Supply only PPB-registered products in the brand and pack size ordered. No substitutes without written approval from {$organisation}.",
            "Minimum shelf life at delivery: 18 months, or 75% of total shelf life. Short-dated stock will be returned at the supplier's cost.",
            'Show the batch number and expiry date of every line on both the delivery note and the invoice.',
            "Seal and label each carton \"{$organisation} – {$branch}\" with this PO number and carton count (e.g. 1 of 12). Protect syrups from heat and breakage.",
            'Quote this PO number on the invoice. Quantities above this PO will not be paid. Prices as per your quotation; state the VAT treatment on the invoice.',
            "Damaged, leaking or non-conforming goods will be rejected at goods receipt at {$branch} and reported within 48 hours.",
            'Acknowledge this PO by email or WhatsApp with the confirmed dispatch date, then send the waybill/consignment number once dispatched.',
        ];
    }
}
