<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a model that had to answer through a submit tool (see
 * `App\Ai\ToolSubmission`) replied without calling it, usually a model that
 * doesn't support tool calling. Mapped to a 502 in `bootstrap/app.php`: the
 * request was fine, the upstream model's answer wasn't usable.
 */
class ModelSubmissionException extends RuntimeException
{
    public static function missingToolCall(string $toolName): self
    {
        return new self("The model did not call {$toolName}. It may not support tool calling.");
    }
}
