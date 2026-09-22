<?php

namespace App\Services\Inventory;

/**
 * Why a line came back. A fixed list so returns can be counted by cause
 * (damaged in transit, short expiry, wrong item…); the remarks beside each
 * line carry the detail, and OTHER must always say what it was.
 */
class ReturnReasons
{
    /** Customer → Stock Point. */
    public const CUSTOMER = [
        'DAMAGED', 'EXPIRED', 'SHORT_EXPIRY', 'WRONG_ITEM', 'EXCESS_QUANTITY',
        'QUALITY_COMPLAINT', 'ADVERSE_REACTION', 'RECALL', 'NOT_REQUIRED', 'OTHER',
    ];

    /** Stock Point → supplier. */
    public const SUPPLIER = [
        'DAMAGED', 'EXPIRED', 'SHORT_EXPIRY', 'WRONG_ITEM', 'NOT_ORDERED', 'EXCESS_QUANTITY',
        'QUALITY_COMPLAINT', 'RECALL', 'SLOW_MOVING', 'OTHER',
    ];
}
