<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by `Workflow::publishVersion()` when the workspace requires changes
 * to be reviewed before they go live: mapped to a 409 in
 * `bootstrap/app.php`. The way through is a `WorkflowPublishRequest`.
 */
class PublishApprovalRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This workspace reviews changes before they go live. Request a review instead of publishing.');
    }
}
