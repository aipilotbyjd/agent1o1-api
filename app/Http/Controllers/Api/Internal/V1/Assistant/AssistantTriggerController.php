<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantTriggerStatus;
use App\Enums\Assistant\AssistantTriggerType;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\SaveTriggerRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantTrigger;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Triggers\TriggerDefinitions;
use App\Services\Assistant\Triggers\TriggerFirer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssistantTriggerController extends Controller
{
    use ResolvesOwnAssistant;

    public function __construct(private TriggerDefinitions $definitions) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $triggers = $this->ownAssistant($request, $workspace)->triggers()->latest()->get();

        return ApiResponse::success(['triggers' => $triggers->map(fn (AssistantTrigger $trigger): array => $this->describe($trigger))->values()]);
    }

    public function store(SaveTriggerRequest $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $trigger = $this->definitions->create($this->ownAssistant($request, $workspace), $request->validated(), 'owner');

        return ApiResponse::created(['trigger' => $this->describe($trigger)], 'Trigger created.');
    }

    public function update(SaveTriggerRequest $request, Workspace $workspace, AssistantTrigger $trigger): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwn($request, $workspace, $trigger);

        return ApiResponse::success(['trigger' => $this->describe($this->definitions->update($trigger, $request->validated()))], 'Saved.');
    }

    public function destroy(Request $request, Workspace $workspace, AssistantTrigger $trigger): Response
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwn($request, $workspace, $trigger);

        $trigger->delete();

        return ApiResponse::noContent();
    }

    public function runNow(Request $request, Workspace $workspace, AssistantTrigger $trigger, TriggerFirer $firer): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwn($request, $workspace, $trigger);

        $session = $firer->fire($trigger);

        return ApiResponse::success(['session_id' => $session?->id, 'skipped' => $session === null], $session === null ? 'Its last run is still going.' : 'Running now.');
    }

    private function ensureOwn(Request $request, Workspace $workspace, AssistantTrigger $trigger): void
    {
        abort_if($trigger->assistant_id !== $this->ownAssistant($request, $workspace)->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(AssistantTrigger $trigger): array
    {
        return [
            'id' => $trigger->id,
            'type' => $trigger->type->value,
            'name' => $trigger->name,
            'prompt' => $trigger->prompt,
            'cron' => $trigger->cron,
            'timezone' => $trigger->timezone,
            'run_at' => $trigger->run_at,
            'next_run_at' => $trigger->status === AssistantTriggerStatus::Active ? $trigger->next_run_at : null,
            'last_run_at' => $trigger->last_run_at,
            'status' => $trigger->status->value,
            'created_by' => $trigger->created_by,
            'consecutive_failures' => $trigger->consecutive_failures,
            'webhook_url' => $trigger->type === AssistantTriggerType::Webhook ? route('hooks.assistant', $trigger->webhook_token) : null,
        ];
    }
}
