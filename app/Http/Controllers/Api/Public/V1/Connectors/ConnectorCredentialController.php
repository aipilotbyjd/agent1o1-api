<?php

namespace App\Http\Controllers\Api\Public\V1\Connectors;

use App\Exceptions\ConnectorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Public\V1\StoreConnectorCredentialRequest;
use App\Http\Resources\Api\Public\V1\ConnectorCredentialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use Illuminate\Http\Request;

/**
 * Provisioning credentials without a browser: what the `connectors:manage`
 * key ability was defined for. A platform that creates a workspace per
 * customer needs to attach that customer's API keys programmatically; doing
 * it by hand in the UI doesn't scale.
 *
 * OAuth connectors are refused here on purpose: their credentials come from
 * an authorization redirect that only a browser can complete, and accepting
 * hand-written token data for one would produce a credential that silently
 * can't refresh itself.
 *
 * `created_by` stays null: an API key belongs to a workspace, not a person.
 */
class ConnectorCredentialController extends Controller
{
    public function index(Request $request)
    {
        $credentials = $this->apiKeyWorkspace($request)
            ->connectorCredentials()
            ->with('connector')
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'connector_credentials' => ConnectorCredentialResource::collection($credentials),
        ]);
    }

    public function show(Request $request, ConnectorCredential $connectorCredential)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $connectorCredential);

        return ApiResponse::success([
            'connector_credential' => ConnectorCredentialResource::make($connectorCredential->load('connector')),
        ]);
    }

    public function store(StoreConnectorCredentialRequest $request)
    {
        $connector = Connector::findOrFail($request->validated('connector_id'));

        if ($connector->isOAuth()) {
            throw new ConnectorException("Connector [{$connector->key}] is OAuth-only: its credentials can only be created through the OAuth connect flow.");
        }

        if (($missing = $connector->missingFields((array) $request->validated('data'))) !== []) {
            throw ConnectorException::missingFields($connector, $missing);
        }

        $credential = $this->apiKeyWorkspace($request)->connectorCredentials()->create($request->validated());

        return ApiResponse::created([
            'connector_credential' => ConnectorCredentialResource::make($credential->load('connector')),
        ], 'Connector credential created.');
    }

    public function destroy(Request $request, ConnectorCredential $connectorCredential)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $connectorCredential);

        $connectorCredential->delete();

        return ApiResponse::noContent();
    }
}
