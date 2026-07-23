<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AgentTrigger\StoreAgentTriggerRequest;
use App\Http\Requests\Api\V1\AgentTrigger\UpdateAgentTriggerRequest;
use App\Http\Resources\V1\TriggerResource;
use App\Jobs\RunAgentJob;
use App\Models\Agent;
use App\Models\Trigger;
use App\Models\Workspace;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * CRUD for agent triggers. Storage is unified with the workflow trigger system:
 * each row is a polymorphic Trigger whose target is this agent, so agent
 * triggers flow through the same webhook / schedule pipeline (signature
 * verification, dedup, filters, concurrency, rate limiting).
 */
class AgentTriggerController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse(
            'Agent triggers retrieved.',
            TriggerResource::collection($agent->triggers()->latest()->get()),
        );
    }

    public function store(StoreAgentTriggerRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $trigger = $agent->triggers()->create($this->attributesFor(
            $request->validated(),
            $workspace,
        ));

        return $this->successResponse('Agent trigger created.', new TriggerResource($trigger), 201);
    }

    public function update(UpdateAgentTriggerRequest $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $trigger->update($this->attributesFor($request->validated(), $workspace, $trigger));

        return $this->successResponse('Agent trigger updated.', new TriggerResource($trigger->fresh()));
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $trigger->delete();

        return $this->successResponse('Agent trigger deleted.');
    }

    public function fire(Request $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        // Manual fire bypasses the trigger pipeline (guards/dedup) and runs the
        // agent directly, mirroring how a workflow manual run bypasses triggers.
        $message = $trigger->initial_message ?? $request->input('message', '');

        RunAgentJob::dispatch($agent->id, $message, $trigger->id, [
            'fired_by' => $request->user()->id,
        ]);

        return $this->successResponse('Trigger fired. Agent will run in the background.', null, 202);
    }

    /**
     * Translate the agent-facing trigger payload into unified Trigger columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFor(array $data, Workspace $workspace, ?Trigger $existing = null): array
    {
        $requestedType = $data['type'] ?? $existing?->settings['agent_type'] ?? 'webhook';
        $config = $data['config'] ?? $existing?->settings['config'] ?? [];

        $engineType = match ($requestedType) {
            'schedule' => 'scheduled',
            'event' => 'manual',
            default => $requestedType,
        };

        $attributes = [
            'workspace_id' => $workspace->id,
            'name' => ucfirst($requestedType).' trigger',
            'type' => $engineType,
            'settings' => ['agent_type' => $requestedType, 'config' => $config],
        ];

        if (array_key_exists('initial_message', $data)) {
            $attributes['initial_message'] = $data['initial_message'];
        }

        if (array_key_exists('is_active', $data)) {
            $attributes['is_active'] = $data['is_active'] ?? true;
        }

        if ($engineType === 'webhook' && ! $existing?->webhook_uuid) {
            $attributes['webhook_uuid'] = Str::uuid()->toString();
            $attributes['webhook_status'] = 'active';
        }

        if ($engineType === 'scheduled') {
            $attributes['schedule_expression'] = $config['cron'] ?? null;
            $attributes['schedule_timezone'] = $config['timezone'] ?? 'UTC';
            $attributes['schedule_next_run_at'] = $this->nextRunAt($config);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function nextRunAt(array $config): ?string
    {
        $cron = $config['cron'] ?? null;

        if (! $cron) {
            return null;
        }

        try {
            return (new CronExpression($cron))
                ->getNextRunDate('now', 0, false, $config['timezone'] ?? 'UTC')
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
