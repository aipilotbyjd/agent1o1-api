<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantSituationStatus;
use App\Enums\Assistant\AssistantSituationStepStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantSituation;
use App\Models\Assistant\AssistantSituationStep;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Briefings\SituationActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssistantSituationController extends Controller
{
    use ResolvesOwnAssistant;

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $status = AssistantSituationStatus::tryFrom((string) $request->query('status', 'open')) ?? AssistantSituationStatus::Open;

        $situations = $this->ownAssistant($request, $workspace)->situations()
            ->with('steps')
            ->where('status', $status)
            ->latest()
            ->limit(50)
            ->get();

        return ApiResponse::success(['situations' => $situations->map(fn (AssistantSituation $situation): array => $this->describe($situation))->values()]);
    }

    public function update(Request $request, Workspace $workspace, AssistantSituation $situation): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwn($request, $workspace, $situation);

        $validated = $request->validate(['status' => ['required', Rule::in(['done', 'dismissed', 'open'])]]);
        $situation->forceFill(['status' => $validated['status']])->save();

        return ApiResponse::success(['situation' => $this->describe($situation->load('steps'))]);
    }

    public function updateStep(Request $request, Workspace $workspace, AssistantSituation $situation, AssistantSituationStep $step): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwn($request, $workspace, $situation);
        abort_if($step->assistant_situation_id !== $situation->id, 404);

        $validated = $request->validate(['status' => ['required', Rule::enum(AssistantSituationStepStatus::class)]]);
        $step->forceFill(['status' => $validated['status']])->save();

        return ApiResponse::success(['situation' => $this->describe($situation->load('steps'))]);
    }

    public function send(Request $request, Workspace $workspace, AssistantSituation $situation, SituationActions $actions): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwn($request, $workspace, $situation);

        $situation = $actions->send($situation->load('steps'));

        return ApiResponse::success(['situation' => $this->describe($situation->load('steps'))], 'Sent.');
    }

    private function ensureOwn(Request $request, Workspace $workspace, AssistantSituation $situation): void
    {
        abort_if($situation->assistant_id !== $this->ownAssistant($request, $workspace)->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(AssistantSituation $situation): array
    {
        return [
            'id' => $situation->id,
            'title' => $situation->title,
            'summary' => $situation->summary,
            'next_step' => $situation->next_step,
            'sources' => $situation->sources ?? [],
            'status' => $situation->status->value,
            'session_id' => $situation->assistant_session_id,
            'steps' => $situation->steps->map(fn (AssistantSituationStep $step): array => [
                'id' => $step->id,
                'body' => $step->body,
                'status' => $step->status->value,
            ])->values(),
            'created_at' => $situation->created_at,
        ];
    }
}
