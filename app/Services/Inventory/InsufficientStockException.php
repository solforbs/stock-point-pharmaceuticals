<?php

namespace App\Services\Inventory;

class InsufficientStockException extends \RuntimeException
{
    public function __construct(
        public readonly string $requested,
        public readonly string $available,
        public readonly string $shortfall,
    ) {
        parent::__construct("Insufficient stock: requested {$requested}, available {$available}, shortfall {$shortfall}.");
    }
}
