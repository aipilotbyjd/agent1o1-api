<?php

namespace App\Http\Controllers\Api\Internal\V1\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Enums\Workspaces\Permission;
use App\Exceptions\ConnectorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Triggers\IndexTriggerEventsRequest;
use App\Http\Requests\Api\Internal\V1\Triggers\IndexTriggersRequest;
use App\Http\Requests\Api\Internal\V1\Triggers\StoreTriggerRequest;
use App\Http\Requests\Api\Internal\V1\Triggers\UpdateTriggerRequest;
use App\Http\Resources\Api\Internal\V1\Triggers\TriggerEventResource;
use App\Http\Resources\Api\Internal\V1\Triggers\TriggerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerSampler;
use App\Services\Triggers\TriggerService;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Throwable;

class TriggerController extends Controller
{
    public function __construct(
        private readonly TriggerService $triggers,
    ) {}

    public function index(IndexTriggersRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::TriggerView);

        $since = now()->subDay();

        $triggers = $workspace->triggers()
            ->with(['target', 'subscription', 'workspace'])
            ->withMax('events as last_event_at', 'created_at')
            ->withCount([
                'events as fired_24h_count' => fn (Builder $events) => $events->where('status', TriggerEventStatus::Fired)->where('created_at', '>=', $since),
                'events as failed_24h_count' => fn (Builder $events) => $events->where('status', TriggerEventStatus::Failed)->where('created_at', '>=', $since),
                'events as skipped_24h_count' => fn (Builder $events) => $events->whereIn('status', [TriggerEventStatus::Skipped, TriggerEventStatus::Ignored])->where('created_at', '>=', $since),
                // Turned away at the door (bad token or signature): usually a misconfigured sender.
                'events as rejected_24h_count' => fn (Builder $events) => $events->where('status', TriggerEventStatus::Rejected)->where('created_at', '>=', $since),
            ])
            ->when($request->validated('target_type'), fn (Builder $triggers, string $targetType) => $triggers->where('target_type', $targetType))
            ->when($request->validated('target_id'), fn (Builder $triggers, string $targetId) => $triggers->where('target_id', $targetId))
            ->latest()
            ->get();

        return ApiResponse::success([
            'triggers' => TriggerResource::collection($triggers),
        ]);
    }

    public function store(StoreTriggerRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::TriggerManage);

        $trigger = $this->triggers->create(
            $workspace,
            TriggerTargetType::from($request->validated('target_type')),
            $request->validated('target_id'),
            TriggerType::from($request->validated('type')),
            $request->validated(),
            $request->user(),
        );

        return ApiResponse::created(['trigger' => TriggerResource::make($trigger)], 'Trigger created successfully.');
    }

    public function update(UpdateTriggerRequest $request, Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::TriggerManage);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        $trigger = $this->triggers->update($trigger, $request->validated());

        return ApiResponse::success(['trigger' => TriggerResource::make($trigger)], 'Trigger updated successfully.');
    }

    public function destroy(Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::TriggerManage);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        // The next publish would recreate it: see TriggerNodeSync.
        if ($trigger->trigger_node_key !== null) {
            return ApiResponse::error('This trigger is part of the workflow. Remove its node from the canvas, then publish.', 422);
        }

        $trigger->delete();

        return ApiResponse::noContent();
    }

    public function run(Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        if ($this->triggers->isAlreadyRunning($trigger)) {
            return ApiResponse::error('This trigger already has a run in flight.', 409);
        }

        if (! $this->triggers->canRun($trigger)) {
            return ApiResponse::error('This trigger\'s target cannot be run right now.', 409);
        }

        $event = $this->triggers->receive($trigger, TriggerType::Manual, []);

        return ApiResponse::success(['event' => TriggerEventResource::make($event)], 'Run queued.', 202);
    }

    public function rotateToken(Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::TriggerManage);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        $trigger = $this->triggers->rotateToken($trigger);

        return ApiResponse::success(['trigger' => TriggerResource::make($trigger)], 'Token rotated successfully.');
    }

    public function events(IndexTriggerEventsRequest $request, Workspace $workspace, Trigger $trigger)
    {
        $this->requirePermission(Permission::TriggerView);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        $events = $trigger->events()
            ->when($request->validated('status'), fn ($events, array $statuses) => $events->whereIn('status', $statuses))
            ->latest()
            ->paginate((int) ($request->validated('per_page') ?? 25));

        return ApiResponse::paginated(TriggerEventResource::collection($events));
    }

    /**
     * What the trigger sees right now, to map fields from before it has run.
     */
    public function sample(Workspace $workspace, Trigger $trigger, TriggerSampler $sampler)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $trigger);

        try {
            $items = $sampler->sample($trigger);
        } catch (InvalidArgumentException|ConnectorException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Could not read from the app right now. Try again in a moment.', 422);
        }

        return ApiResponse::success(['items' => $items]);
    }

    /**
     * Run a past event's payload again, as a new event.
     */
    public function replay(Workspace $workspace, Trigger $trigger, TriggerEvent $event)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $trigger);
        abort_unless($event->trigger_id === $trigger->id, 404);

        if ($event->payload === null) {
            return ApiResponse::error('This event\'s payload was cleared, so it cannot be replayed.', 422);
        }

        if (! $this->triggers->canRun($trigger)) {
            return ApiResponse::error('This trigger\'s target cannot be run right now.', 409);
        }

        $replayed = $this->triggers->receive($trigger, TriggerType::Manual, $event->payload);

        return ApiResponse::success(['event' => TriggerEventResource::make($replayed)], 'Replay queued.', 202);
    }
}
