<?php

namespace App\Services\Agents;

use App\Enums\Agents\ReflectionStatus;
use App\Enums\Agents\ReflectionType;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Models\Agents\Agent;
use App\Models\Agents\Reflection;
use App\Models\Runs\Run;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Executes an accepted `Reflection`: creates/updates a `Skill` or edits
 * `Agent::instructions` (which `AgentObserver` snapshots into a new
 * `AgentVersion` on save, same as any other instruction edit). Records the
 * change as its own `Run` so it shows up in the agent's history exactly like
 * the "self-improvement interaction" the feature this mirrors describes:
 * see docs/gumloop/output/raw/core-concepts/reflections.md.
 *
 * `tool_access` reflections have nothing safe to automate: applying one only
 * marks it resolved, a human still has to grant the connector/credential
 * themselves.
 */
class ReflectionApplier
{
    public function apply(Reflection $reflection, ?User $actor = null): Reflection
    {
        abort_if($reflection->status->isTerminal(), 422, 'This reflection has already been resolved.');

        if ($reflection->type === ReflectionType::ToolAccess) {
            $reflection->forceFill(['status' => ReflectionStatus::Applied])->save();

            return $reflection->fresh();
        }

        $agent = $reflection->agent;
        $run = $this->openRun($reflection, $agent, $actor);

        try {
            match ($reflection->type) {
                ReflectionType::NewSkill => $this->applyNewSkill($reflection, $agent, $actor),
                ReflectionType::SkillFix => $this->applySkillFix($reflection),
                ReflectionType::InstructionUpdate => $this->applyInstructionUpdate($reflection, $agent),
                ReflectionType::ToolAccess => null, // unreachable: guarded above
            };

            $reflection->forceFill(['status' => ReflectionStatus::Applied, 'applied_run_id' => $run->id])->save();

            $run->forceFill(['status' => RunStatus::Completed, 'finished_at' => now()])->save();
            event(new RunCompleted($run));
        } catch (Throwable $e) {
            $run->forceFill(['status' => RunStatus::Failed, 'error' => $e->getMessage(), 'finished_at' => now()])->save();
            event(new RunFailed($run));

            throw $e;
        }

        return $reflection->fresh();
    }

    public function dismiss(Reflection $reflection): Reflection
    {
        abort_if($reflection->status->isTerminal(), 422, 'This reflection has already been resolved.');

        $reflection->forceFill(['status' => ReflectionStatus::Dismissed])->save();

        return $reflection;
    }

    private function applyNewSkill(Reflection $reflection, Agent $agent, ?User $actor): void
    {
        $skill = $agent->workspace->skills()->create([
            'name' => $reflection->title,
            'slug' => Str::slug($reflection->title).'-'.Str::random(6),
            'description' => $reflection->rationale,
            'instructions' => $reflection->proposed_prompt,
            'created_by' => $actor?->id,
        ]);

        $agent->skills()->syncWithoutDetaching([$skill->id]);
    }

    private function applySkillFix(Reflection $reflection): void
    {
        $skill = $reflection->targetSkill;

        abort_if($skill === null, 422, 'This reflection has no target skill to update.');
        abort_if($skill->isSynced(), 422, 'This skill is synced from GitHub. Make the change in its repository instead.');

        $skill->update(['instructions' => $reflection->proposed_prompt]);
        $skill->increment('version');
    }

    private function applyInstructionUpdate(Reflection $reflection, Agent $agent): void
    {
        // Triggers `AgentObserver`, which snapshots the prior behavior into a
        // new `AgentVersion`: no need to call `AgentVersioner` directly.
        $agent->update(['instructions' => $reflection->proposed_prompt]);
    }

    private function openRun(Reflection $reflection, Agent $agent, ?User $actor): Run
    {
        $run = $reflection->reflectionRun->runs()->create([
            'workspace_id' => $agent->workspace_id,
            'trigger_type' => 'reflection_apply',
            'input' => ['reflection_id' => $reflection->id],
            'triggered_by' => $actor?->id,
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        return $run;
    }
}
