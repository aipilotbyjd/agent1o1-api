<?php

namespace App\Http\Controllers\Api\V1;

use App\Agents\Internal\Registry;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InternalAgentRun;
use App\Models\Workspace;
use App\Services\Agent\ConnectorTemplateService;
use App\Services\AgentMetadataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only discovery endpoints backing the agent builder: available AI
 * providers/models, the tool catalog, categories, and trigger types.
 */
class AgentMetadataController extends Controller
{
    public function __construct(private readonly AgentMetadataService $metadata) {}

    public function providers(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Providers retrieved.', ['providers' => $this->metadata->providers()]);
    }

    public function models(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Models retrieved.', [
            'providers' => $this->metadata->models($request->query('provider')),
        ]);
    }

    public function tools(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Tools retrieved.', ['tools' => $this->metadata->tools($workspace)]);
    }

    public function categories(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Categories retrieved.', ['categories' => $this->metadata->categories($workspace)]);
    }

    public function triggerTypes(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Trigger types retrieved.', ['trigger_types' => $this->metadata->triggerTypes()]);
    }

    /**
     * One-click connector presets over the generic app nodes (roadmap item 7):
     * curated tool configs a user can drop onto an agent and just add a key.
     */
    public function connectors(Request $request, Workspace $workspace, ConnectorTemplateService $connectors): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Connector templates retrieved.', ['connectors' => $connectors->all()]);
    }

    /**
     * The platform-owned (internal) agent catalog: every agent in the
     * Registry with its resolved provider/model config and this workspace's
     * aggregate usage of it. Read-only — internal agents are code-defined.
     */
    public function internalAgents(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $stats = InternalAgentRun::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('name, count(*) as runs, coalesce(sum(total_tokens), 0) as tokens, coalesce(sum(estimated_cost), 0) as cost')
            ->groupBy('name')
            ->get()
            ->keyBy('name');

        $defaults = config('agents.internal.defaults', []);

        $agents = collect(Registry::all())
            ->map(function (string $class, string $name) use ($stats, $defaults) {
                $override = config("agents.internal.overrides.{$name}", []);
                $segments = explode('\\', $class);

                return [
                    'name' => $name,
                    'class' => class_basename($class),
                    'category' => strtolower($segments[count($segments) - 2] ?? 'general'),
                    'version' => $class::$version,
                    // null means "inherits the calling agent's provider/model".
                    'provider' => $override['provider'] ?? $defaults['provider'] ?? null,
                    'model' => $override['model'] ?? $defaults['model'] ?? null,
                    'usage' => [
                        'runs' => (int) ($stats[$name]->runs ?? 0),
                        'total_tokens' => (int) ($stats[$name]->tokens ?? 0),
                        'estimated_cost' => (float) ($stats[$name]->cost ?? 0),
                    ],
                ];
            })
            ->values();

        return $this->successResponse('Internal agents retrieved.', ['internal_agents' => $agents]);
    }
}
