<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, token-authenticated: mirrors `WebhookController`'s "the URL
 * segment is the entire auth story" pattern for trigger webhooks
 * (docs/TRIGGERS_PLAN.md), applied to a `Wait` node's one-time callback
 * token instead of a `Trigger`'s.
 */
class WaitCallbackController extends Controller
{
    public function __construct(private readonly WorkflowRunner $runner) {}

    public function __invoke(string $token, Request $request): JsonResponse
    {
        $nodeRun = $this->runner->resolveCallback($token, (array) $request->all());

        return ApiResponse::success(['node_run_id' => $nodeRun->id], 'Callback accepted.');
    }
}
