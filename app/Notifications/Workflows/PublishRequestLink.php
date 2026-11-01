<?php

namespace App\Notifications\Workflows;

use App\Models\Workflows\WorkflowPublishRequest;

/**
 * Where a publish-request email sends people: the workflow editor, opened on
 * that request.
 */
final class PublishRequestLink
{
    public static function to(WorkflowPublishRequest $publishRequest): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            ."/{$publishRequest->workspace_id}/playbooks/{$publishRequest->workflow_id}/edit?publish_request={$publishRequest->id}";
    }
}
