<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A step referenced `{{ secrets.X }}` (or `vars.X`) that the workspace
 * doesn't have. Failing the step says so, where resolving it to an empty
 * value would call an API with a blank token and fail somewhere less clear.
 */
class MissingSecretException extends RuntimeException
{
    /**
     * @param  array<int, string>  $keys
     */
    public static function for(array $keys): self
    {
        $names = implode(', ', $keys);

        return new self(count($keys) === 1
            ? "No secret named {$names} in this workspace. Add it on the Secrets page or fix the name."
            : "No secrets named {$names} in this workspace. Add them on the Secrets page or fix the names.");
    }
}
