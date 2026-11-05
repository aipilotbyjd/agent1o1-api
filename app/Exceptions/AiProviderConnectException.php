<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * One-click AI provider connect could not finish: the provider refused,
 * the sign-in expired, or the member may no longer add the key. The
 * message is safe to show the member; mapped to a 422 for API requests.
 */
class AiProviderConnectException extends RuntimeException
{
    public static function notAvailable(string $providerLabel): self
    {
        return new self("One-click connect isn't available for {$providerLabel}. Paste a key instead.");
    }

    public static function expired(): self
    {
        return new self('This sign-in link has expired or was already used. Start the connection again.');
    }

    public static function notAllowed(): self
    {
        return new self("You can't add AI provider keys in this workspace. Ask an admin.");
    }

    public static function refused(string $providerLabel, int $status): self
    {
        return new self("{$providerLabel} didn't complete the connection (HTTP {$status}). Try connecting again.");
    }

    public static function unreachable(string $providerLabel): self
    {
        return new self("Couldn't reach {$providerLabel} to complete the connection. Try again in a moment.");
    }

    public static function cannotRefresh(string $providerLabel): self
    {
        return new self("The {$providerLabel} connection expired and couldn't be renewed. Connect it again.");
    }
}
