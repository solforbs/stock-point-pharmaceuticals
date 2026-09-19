<?php

namespace App\Services\Admin;

/**
 * Raised when a record may not be deleted for a reason no foreign key can
 * express: it is not a deletable kind of record at all, or deleting it would
 * lock everyone out. Carries its own API code and status.
 */
class RecordNotDeletableException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}
