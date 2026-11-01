<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a `Run` is asked to do something its current status forbids:
 * cancelling an already-finished run, retrying one that is still in flight,
 * retrying from a node key the run never had. Mapped to a 409 in
 * `bootstrap/app.php`: the request itself is well-formed, it is the
 * resource's state that refuses it.
 */
class RunStateException extends RuntimeException
{
    public static function alreadyFinished(): self
    {
        return new self('The run has already finished and cannot be cancelled.');
    }

    public static function stillRunning(): self
    {
        return new self('The run is still in flight: cancel it before retrying.');
    }

    public static function sessionBusy(): self
    {
        return new self('This conversation is still answering the previous message: wait for it to finish.');
    }

    public static function notAwaitingApproval(): self
    {
        return new self('This turn is not waiting on any approvals: it may already have been resumed.');
    }

    public static function approvalsUndecided(): self
    {
        return new self('Some actions in this turn are still waiting for a decision.');
    }

    public static function notRetryable(): self
    {
        return new self('Only workflow runs pinned to a published version can be retried.');
    }

    public static function unknownNodeKey(string $key): self
    {
        return new self("The run's workflow version has no node with key '{$key}'.");
    }
}
