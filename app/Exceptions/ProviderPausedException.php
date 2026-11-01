<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Every provider a call could run on is paused by the operator (see
 * `ProviderPauses`) and the workspace has no key of its own for it. Mapped to
 * a 503 in `bootstrap/app.php`; a queued turn fails with this message.
 */
class ProviderPausedException extends RuntimeException
{
    public static function forProviders(): self
    {
        return new self('This model is temporarily unavailable. Try again later, or pick another model.');
    }
}
