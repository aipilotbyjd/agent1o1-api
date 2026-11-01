<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Every endpoint a call could run on has reached its per-minute request limit
 * (see `EndpointRateLimiter`). Mapped to a 429 with a `Retry-After` header in
 * `bootstrap/app.php`; a queued turn fails with this message.
 */
class ModelBusyException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('This model is busy right now. Try again in a moment, or pick another model.');
    }

    public static function retryIn(int $seconds): self
    {
        return new self(max(1, $seconds));
    }
}
