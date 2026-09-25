<?php

namespace App\Services\Procurement;

/**
 * One or more rows of a purchase-order import failed validation, so
 * nothing was written (the import is all-or-nothing).
 */
class PurchaseOrderImportValidationException extends \RuntimeException
{
    /**
     * @param  array<int, list<string>>  $rowErrors  keyed by 1-based row number; 0 is the file as a whole
     */
    public function __construct(public readonly array $rowErrors)
    {
        parent::__construct(count($rowErrors).' row(s) failed validation; no purchase order was created.');
    }
}
