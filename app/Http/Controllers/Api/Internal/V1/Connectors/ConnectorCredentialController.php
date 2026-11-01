<?php

namespace App\Http\Controllers\Api\Internal\V1\Connectors;

use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Permission;
use App\Exceptions\ConnectorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Connectors\DestroyConnectorCredentialRequest;
use App\Http\Requests\Api\Internal\V1\Connectors\StoreConnectorCredentialRequest;
use App\Http\Requests\Api\Internal\V1\Connectors\UpdateConnectorCredentialRequest;
use App\Http\Resources\Api\Internal\V1\Connectors\ConnectorCredentialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Workspaces\Workspace;
use App\Services\Connectors\ConnectorCredentialReassigner;
use App\Services\Connectors\ConnectorCredentialTester;
use App\Services\Connectors\ConnectorCredentialUsage;
use App\Services\Workspaces\AuditLogger;
use Illuminate\Http\Request;

class ConnectorCredentialController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::ConnectorView);

        $credentials = $workspace->connectorCredentials()
            ->visibleTo($request->user())
            ->with('connector')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(['connector_credentials' => ConnectorCredentialResource::collection($credentials)]);
    }

    public function show(Request $request, Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorView);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        return ApiResponse::success(['connector_credential' => ConnectorCredentialResource::make($connectorCredential->load('connector'))]);
    }

    public function store(StoreConnectorCredentialRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::ConnectorManage);

        $connector = Connector::findOrFail($request->validated('connector_id'));

        if ($connector->isOAuth()) {
            throw new ConnectorException("Connector [{$connector->key}] is OAuth-only: use the OAuth connect flow instead of storing credential data directly.");
        }

        if (($missing = $connector->missingFields((array) $request->validated('data'))) !== []) {
            throw ConnectorException::missingFields($connector, $missing);
        }

        $credential = $workspace->connectorCredentials()->create([
            ...collect($request->validated())->except('is_default')->all(),
            'created_by' => $request->user()->id,
        ]);

        if ($request->boolean('is_default')) {
            $credential->markAsDefault();
        }

        return ApiResponse::created(['connector_credential' => ConnectorCredentialResource::make($credential->load('connector'))], 'Connector credential created.');
    }

    public function update(UpdateConnectorCredentialRequest $request, Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorManage);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        if ($request->has('data') && ($connector = $connectorCredential->connector) !== null && ($missing = $connector->missingFields((array) $request->validated('data'))) !== []) {
            throw ConnectorException::missingFields($connector, $missing);
        }

        $connectorCredential->update($request->validated());

        // The model observer skips updates (OAuth refreshes rewrite the token
        // constantly), so a member's own edit is recorded here instead.
        $this->audit->record($workspace->id, AuditAction::ConnectorCredentialUpdated, $connectorCredential, [
            'fields' => array_keys($request->validated()),
        ]);

        return ApiResponse::success(['connector_credential' => ConnectorCredentialResource::make($connectorCredential->load('connector'))], 'Connector credential updated.');
    }

    /**
     * With `replace_with`, everything pinned to this connection is moved to
     * that one first (see `ConnectorCredentialReassigner`), so nothing that
     * used it breaks.
     */
    public function destroy(DestroyConnectorCredentialRequest $request, Workspace $workspace, ConnectorCredential $connectorCredential, ConnectorCredentialReassigner $reassigner)
    {
        $this->requirePermission(Permission::ConnectorManage);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        $replacementId = $request->validated('replace_with');

        if ($replacementId !== null) {
            $replacement = $workspace->connectorCredentials()->visibleTo($request->user())->find($replacementId);
            abort_if($replacement === null, 404);

            $moved = $reassigner->reassign($connectorCredential, $replacement);

            $this->audit->record($workspace->id, AuditAction::ConnectorCredentialReassigned, $replacement, [
                'from' => $connectorCredential->id,
                ...$moved,
            ]);
        }

        $connectorCredential->delete();

        return ApiResponse::noContent();
    }

    /**
     * Sets this credential as the one a node/agent gets when nothing pins
     * a `credential_id`: see `ResolvesConnectorCredential`.
     */
    public function setDefault(Request $request, Workspace $workspace, ConnectorCredential $connectorCredential)
    {
        $this->requirePermission(Permission::ConnectorManage);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        $connectorCredential->markAsDefault();
        $this->audit->record($workspace->id, AuditAction::ConnectorCredentialDefaultChanged, $connectorCredential);

        return ApiResponse::success(['connector_credential' => ConnectorCredentialResource::make($connectorCredential->fresh()->load('connector'))], 'Default connector credential set.');
    }

    /**
     * Checks the connection works right now. A failed check is a result,
     * not an error: the response is 200 either way, with `ok` saying which.
     */
    public function test(Request $request, Workspace $workspace, ConnectorCredential $connectorCredential, ConnectorCredentialTester $tester)
    {
        $this->requirePermission(Permission::ConnectorManage);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        $result = $tester->test($connectorCredential);

        return ApiResponse::success([
            'result' => $result,
            'connector_credential' => ConnectorCredentialResource::make($connectorCredential->fresh()->load('connector')),
        ], $result['message']);
    }

    /**
     * The workflows, agents and knowledge sources that would stop working
     * if this credential were disconnected.
     */
    public function usage(Request $request, Workspace $workspace, ConnectorCredential $connectorCredential, ConnectorCredentialUsage $usage)
    {
        $this->requirePermission(Permission::ConnectorView);
        $this->ensureBelongsToWorkspace($workspace, $connectorCredential);
        $this->ensureVisible($request, $connectorCredential);

        return ApiResponse::success(['usage' => $usage->for($connectorCredential)]);
    }

    /**
     * A personal credential belonging to someone else is treated as if it
     * doesn't exist: hidden, not merely forbidden, matching the "cannot
     * see or use" guarantee.
     */
    private function ensureVisible(Request $request, ConnectorCredential $connectorCredential): void
    {
        abort_unless($connectorCredential->isVisibleTo($request->user()), 404);
    }
}
