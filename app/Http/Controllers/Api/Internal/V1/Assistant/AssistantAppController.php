<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Connectors\Connector;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Tools\ConnectorToolProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Which apps the assistant can work in for this member, and through which
 * account: what the home screen's "Apps" panel shows.
 */
class AssistantAppController extends Controller
{
    use ResolvesOwnAssistant;

    public function index(Request $request, Workspace $workspace, ConnectorToolProvider $provider): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $credentials = $provider->credentialsFor($this->ownAssistant($request, $workspace));

        $apps = Connector::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Connector $connector): array => [
                'key' => $connector->key,
                'name' => $connector->name,
                'icon' => $connector->icon,
                'color' => $connector->color,
                'connected' => $credentials->has($connector->key),
                'account' => $credentials->get($connector->key)?->name,
                'shared' => $credentials->get($connector->key)?->scope === ConnectorCredentialScope::Team,
            ])
            ->values();

        return ApiResponse::success(['apps' => $apps]);
    }
}
