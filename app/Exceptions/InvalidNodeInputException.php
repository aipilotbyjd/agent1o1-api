<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A node's config failed its schema once its `{{ }}` templates were filled
 * in from upstream outputs: thrown by `ResolvedConfigCaster`. The same
 * upstream data would fail the same way again, so `StepFailureHandler`
 * never retries it.
 */
class InvalidNodeInputException extends RuntimeException
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The node received invalid input from its variables: '.implode(' ', $errors));
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
