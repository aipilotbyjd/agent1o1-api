<?php

namespace App\Http\Controllers\Api\Internal\V1\Ai;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Ai\CompleteAiProviderConnectRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\Connect\AiProviderConnectFlow;
use Illuminate\Http\Request;

/**
 * One-click connect for a workspace's own AI provider key: see
 * `AiProviderConnectFlow`.
 */
class AiProviderConnectController extends Controller
{
    public function __construct(private readonly AiProviderConnectFlow $flow) {}

    /**
     * Returns the provider's sign-in URL for the frontend to send the member
     * to, and whose key it will be (`team` or `personal`).
     */
    public function start(Request $request, Workspace $workspace, string $provider)
    {
        $this->requirePermission(Permission::AiCredentialView);
        abort_unless(is_array(config("byok.providers.{$provider}")), 404);

        return ApiResponse::success($this->flow->start($workspace, $request->user(), $provider));
    }

    /**
     * Called by the frontend page the provider redirected the member to,
     * signed in as that member. The workspace comes from the single-use
     * `state` `start()` stored, and only the member who started the
     * connection can finish it.
     */
    public function complete(CompleteAiProviderConnectRequest $request)
    {
        $credential = $this->flow->complete(
            $request->validated('state'),
            $request->validated('code'),
            $request->user(),
        );

        return ApiResponse::created([
            'ai_provider_credential' => [
                'id' => $credential->id,
                'workspace_id' => $credential->workspace_id,
                'execution_provider' => $credential->execution_provider,
                'provider_label' => (string) config("byok.providers.{$credential->execution_provider}.label", $credential->execution_provider),
                'scope' => $credential->scope->value,
                'auth_method' => $credential->auth_method->value,
                'validation_status' => $credential->validation_status->value,
            ],
        ], 'AI provider connected.');
    }
}
