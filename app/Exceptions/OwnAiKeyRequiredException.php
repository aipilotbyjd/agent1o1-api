<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The workspace runs AI only on its own provider keys
 * (`PlatformKeyUsage::Never`) and none of them covers the model a call
 * asked for. Mapped to a 422 response in `bootstrap/app.php`; a queued turn
 * fails with this message.
 */
class OwnAiKeyRequiredException extends RuntimeException
{
    /**
     * @param  array<int, string>  $providerLabels  the providers a key could be added for
     */
    public static function forProviders(array $providerLabels): self
    {
        $hint = $providerLabels === []
            ? 'None of the providers this model runs on accepts a workspace key, so pick another model.'
            : 'Add a key for '.implode(' or ', $providerLabels).' in Settings → AI Providers, or pick another model.';

        return new self("This workspace only runs AI on its own provider keys, and none of them covers this model. {$hint}");
    }
}
