<?php

namespace App\Services\Leave;

/**
 * A refused leave action, carrying the stable API error code (Part 21.15).
 */
class LeaveException extends \DomainException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
