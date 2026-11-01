<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a model that had to hand back a structured result (see
 * `App\Ai\ToolSubmission`) replied without one: no submit tool call, or no
 * JSON matching the schema, usually a model that doesn't support either. Mapped to a 502 in `bootstrap/app.php`: the
 * request was fine, the upstream model's answer wasn't usable.
 */
class ModelSubmissionException extends RuntimeException
{
    public static function missingToolCall(string $toolName): self
    {
        return new self("The model did not call {$toolName}. It may not support tool calling.");
    }

    public static function missingStructuredOutput(): self
    {
        return new self('The model did not return a structured result. It may not support structured output.');
    }
}
