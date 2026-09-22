<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * The command exists, but this user's role does not reach the screen it
 * mirrors. Named separately so the assistant can say which permission is
 * missing without leaking the figures behind it.
 */
class AssistantPermissionException extends RuntimeException
{
    public function __construct(public readonly string $permission, public readonly string $title)
    {
        parent::__construct("Your role does not include '{$permission}', so the assistant cannot show {$title}.");
    }
}
