<?php

namespace App\Services\Admin;

/**
 * Raised when a record cannot be deleted because other rows point at it.
 * The message names what is holding it, so the answer is never a bare "no".
 */
class RecordInUseException extends \RuntimeException
{
    /**
     * @param  list<array{table: string, column: string, count: int}>  $references
     */
    public function __construct(public readonly string $label, public readonly array $references)
    {
        $held = implode(', ', array_map(
            fn (array $r) => $r['count'].' '.str_replace('_', ' ', $r['table']),
            array_slice($references, 0, 4)
        ));

        parent::__construct("{$label} cannot be deleted: it is used by {$held}. Deactivate it instead, so the documents that refer to it still make sense.");
    }
}
