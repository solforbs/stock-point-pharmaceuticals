<?php

namespace App\Services\Sales;

/**
 * Part 7 — stock may only be sold from a store marked sellable. A warehouse,
 * cold room or quarantine holds stock that is not yet (or no longer) meant
 * for a customer, and a sale from there would bypass that decision.
 */
class StoreNotSellableException extends \RuntimeException
{
    public function __construct(public readonly string $storeCode)
    {
        parent::__construct("Store {$storeCode} is not a sellable store, so nothing can be sold from it. Transfer the stock to a sellable store, or mark {$storeCode} sellable in Admin > Branches & Stores.");
    }
}
