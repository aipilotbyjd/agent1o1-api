<?php

namespace App\Http\Controllers\Api\Internal\V1\Connectors;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Connectors\InitiateOAuthConnectorRequest;
use App\Http\Resources\Api\Internal\V1\Connectors\ConnectorCredentialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Workspaces\Workspace;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Connectors\OAuthConnectorFlowService;
use Illuminate\Http\Request;

class OAuthConnectorController extends Controller
{
    public function __construct(private readonly OAuthConnectorFlowService $flow) {}

    /**
     * Starts the OAuth2 dance: stores a short-lived state row and returns
     * the provider's authorize URL for the frontend to redirect the user to.
     * With `credential_id` it's a reconnect of that existing account.
     */
    public function initiate(InitiateOAuthConnectorRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::ConnectorManage);

        $connector = Connector::findOrFail($request->validated('connector_id'));
        $reconnecting = $this->reconnectTarget($request, $workspace, $connector);

        $result = $this->flow->initiate(
            $workspace,
            $request->user(),
            $connector,
            $request->validated('name'),
            $request->validated('redirect_uri'),
            $request->validated('scope'),
            $reconnecting,
        );

        return ApiResponse::success($result);
    }

    /**
     * The provider's redirect target after the user approves/denies access.
     * Not workspace-scoped in the URL: the workspace comes from the `state`
     * row `initiate()` created, which is how this stays tenant-safe without
     * requiring the provider to round-trip a workspace id.
     */
    public function callback(Request $request, ConnectorCredentialTester $tester)
    {
        $credential = $this->flow->handleCallback(
            $request->query('state', ''),
            $request->query('code', ''),
        );

        // Learns whose account this is (an email, a username) so it can be
        // told apart from the workspace's other connections. Never fatal:
        // the connection itself already succeeded.
        rescue(fn () => $tester->test($credential), report: true);

        return ApiResponse::created(['connector_credential' => ConnectorCredentialResource::make($credential->load('connector'))], 'Connector connected.');
    }

    /**
     * The account being reconnected must be this workspace's, for this
     * connector, and one the member can see: anything else reads as not
     * found, as everywhere else credentials are looked up.
     */
    private function reconnectTarget(InitiateOAuthConnectorRequest $request, Workspace $workspace, Connector $connector): ?ConnectorCredential
    {
        $credentialId = $request->validated('credential_id');

        if ($credentialId === null) {
            return null;
        }

        $credential = $workspace->connectorCredentials()
            ->where('connector_id', $connector->id)
            ->visibleTo($request->user())
            ->find($credentialId);

        abort_if($credential === null, 404);

        return $credential;
    }
}
