<?php

namespace Database\Factories\Triggers;

use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Models\Agents\Agent;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trigger>
 */
class TriggerFactory extends Factory
{
    protected $model = Trigger::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => app(WorkspaceService::class)
                ->create(User::factory()->create(), ['name' => fake()->company()])
                ->id,
            'target_type' => TriggerTargetType::Workflow->value,
            // Filled in by configure() with a real target in this trigger's
            // own workspace: `TargetRunStarter` refuses to fire a trigger
            // whose target is missing or belongs to someone else, so a
            // made-up id would make every factory-built trigger unrunnable.
            'target_id' => null,
            'type' => TriggerType::Manual,
            'config' => [],
            'is_active' => true,
            'consecutive_failure_count' => 0,
        ];
    }

    /**
     * Every trigger needs a real, runnable target. Done in `configure()`
     * rather than `definition()` so it can read the workspace the trigger
     * actually ended up in, and so an explicit `forWorkflow()`/`forAgent()`
     * (or a passed `target_id`) short-circuits it.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Trigger $trigger): void {
            if ($trigger->target_id !== null) {
                return;
            }

            $trigger->target_id = match (TriggerTargetType::from($trigger->target_type)) {
                TriggerTargetType::Workflow => Workflow::factory()
                    ->published()
                    ->create(['workspace_id' => $trigger->workspace_id])->id,
                TriggerTargetType::Agent => Agent::factory()
                    ->create(['workspace_id' => $trigger->workspace_id])->id,
            };
        });
    }

    public function forWorkflow(Workflow $workflow): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $workflow->workspace_id,
            'target_type' => TriggerTargetType::Workflow->value,
            'target_id' => $workflow->id,
        ]);
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $agent->workspace_id,
            'target_type' => TriggerTargetType::Agent->value,
            'target_id' => $agent->id,
        ]);
    }

    public function webhook(): static
    {
        return $this->state(fn (): array => [
            'type' => TriggerType::Webhook,
            'token' => Trigger::generateToken(),
        ]);
    }

    public function schedule(string $cron = '0 9 * * *'): static
    {
        return $this->state(fn (): array => [
            'type' => TriggerType::Schedule,
            'config' => ['cron' => $cron],
        ]);
    }

    public function polling(int $intervalMinutes = 15): static
    {
        return $this->state(fn (): array => [
            'type' => TriggerType::Polling,
            'config' => ['poll_interval_minutes' => $intervalMinutes],
        ]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
