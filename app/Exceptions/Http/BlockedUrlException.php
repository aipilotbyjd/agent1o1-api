<?php

namespace App\Exceptions\Http;

use App\Services\Http\SsrfGuard;
use RuntimeException;

/**
 * Thrown by {@see SsrfGuard} when a node-configured URL
 * resolves to a scheme or host that outbound requests must not reach: the
 * message is persisted onto the failing `NodeRun` and read by whoever is
 * watching the run, so it stays user-facing.
 */
class BlockedUrlException extends RuntimeException
{
    public static function forUrl(string $url, string $reason): self
    {
        return new self("Request to \"{$url}\" was blocked: {$reason}");
    }
}
