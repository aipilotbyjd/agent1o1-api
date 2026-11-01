<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A suspended workspace tried to start work (see `CreditGate`). Rendered as a
 * 403 by `bootstrap/app.php`.
 */
class WorkspaceSuspendedException extends RuntimeException
{
    public function __construct(string $message = 'This workspace has been suspended.')
    {
        parent::__construct($message);
    }
}
