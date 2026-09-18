<?php

namespace App\Services\Inventory;

/**
 * One or more rows of a product attribute import failed validation, so
 * nothing was written (the import is all-or-nothing).
 */
class ProductImportValidationException extends \RuntimeException
{
    /**
     * @param  array<int, list<string>>  $rowErrors  keyed by 1-based row number; 0 is the file as a whole
     */
    public function __construct(public readonly array $rowErrors)
    {
        parent::__construct(count($rowErrors).' product row(s) failed validation; nothing was changed.');
    }
}
