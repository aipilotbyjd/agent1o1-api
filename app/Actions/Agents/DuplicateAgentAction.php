<?php

namespace App\Actions\Agents;

use App\Enums\Billing\PlanLimit;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies an agent's *configuration*: instructions, model settings, attached
 * tools, skills, workflows and attached knowledge-base collections (with their mode). Deliberately not
 * copied: sessions (a conversation belongs to the agent that had it),
 * memories (facts learned about one agent's users are not facts about
 * another's), version history (the copy starts at version 1, written by
 * `AgentObserver`), and artifacts.
 *
 * Mirrors `WorkflowController::duplicate()`'s shape so both objects behave
 * the same way in the UI.
 */
class DuplicateAgentAction
{
    public function __construct(private readonly PlanLimitGate $limits) {}

    public function execute(Agent $agent, ?User $creator = null, ?string $name = null): Agent
    {
        $this->limits->assertCanCreate($agent->workspace, PlanLimit::Agents);

        return DB::transaction(function () use ($agent, $creator, $name): Agent {
            $copy = $agent->workspace->agents()->create([
                'name' => $name ?? "{$agent->name} (copy)",
                'slug' => Str::slug($name ?? $agent->name).'-'.Str::random(6),
                'description' => $agent->description,
                'icon' => $agent->icon,
                'color' => $agent->color,
                'instructions' => $agent->instructions,
                'provider' => $agent->provider,
                'model' => $agent->model,
                'model_catalog_id' => $agent->model_catalog_id,
                'temperature' => $agent->temperature,
                'settings' => $agent->settings,
                'allow_self_updates' => $agent->allow_self_updates,
                'allow_skill_editing' => $agent->allow_skill_editing,
                'allow_self_clone' => $agent->allow_self_clone,
                'autonomy_mode' => $agent->autonomy_mode,
                'test_mode' => $agent->test_mode,
                'allow_web_fetch' => $agent->allow_web_fetch,
                'created_by' => $creator?->id,
            ]);

            foreach ($agent->toolBindings as $binding) {
                $copy->toolBindings()->create([
                    'node_type' => $binding->node_type,
                    'config' => $binding->config,
                    'exposed_fields' => $binding->exposed_fields,
                    'approval_policy' => $binding->approval_policy,
                ]);
            }

            $copy->skills()->sync($agent->skills->pluck('id'));
            $copy->subagents()->sync($agent->subagents->pluck('id'));
            $copy->workflows()->sync($agent->workflows->mapWithKeys(fn ($workflow): array => [
                $workflow->id => ['approval_policy' => $workflow->pivot->approval_policy],
            ]));

            // Which workspace collections the agent uses and how: without
            // these the copy would fall back to searching every collection.
            foreach ($agent->knowledgeCollections as $attached) {
                $copy->knowledgeCollections()->create(['collection' => $attached->collection, 'mode' => $attached->mode]);
            }

            return $copy;
        });
    }
}
