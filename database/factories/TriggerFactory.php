<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Trigger;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trigger>
 */
class TriggerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'workspace_id' => Workspace::factory(),
            'name' => fake()->sentence(2),
            'type' => 'manual',
            'is_active' => false,
            'is_paused' => false,
        ];
    }

    public function webhook(): static
    {
        return $this->state(fn () => [
            'type' => 'webhook',
            'is_active' => true,
            'webhook_uuid' => Str::uuid()->toString(),
            'webhook_secret' => Str::random(40),
            'webhook_status' => 'active',
        ]);
    }

    public function appWebhook(string $provider, ?int $triggerTypeId = null): static
    {
        return $this->state(fn () => [
            'type' => 'webhook',
            'is_active' => true,
            'webhook_uuid' => Str::uuid()->toString(),
            'webhook_provider' => $provider,
            'webhook_secret' => Str::random(40),
            'webhook_status' => 'active',
            'trigger_type_id' => $triggerTypeId,
        ]);
    }

    /**
     * Target this trigger at an agent instead of a workflow.
     */
    public function forAgent(?Agent $agent = null): static
    {
        return $this->state(function () use ($agent) {
            $agent ??= Agent::factory()->create();

            return [
                'workflow_id' => null,
                'target_type' => 'agent',
                'target_id' => $agent->id,
                'workspace_id' => $agent->workspace_id,
            ];
        });
    }

    public function polling(): static
    {
        return $this->state(fn () => [
            'type' => 'polling',
            'is_active' => true,
            'polling_interval_seconds' => 300,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'type' => 'scheduled',
            'is_active' => true,
            'schedule_expression' => '0 * * * *',
            'schedule_timezone' => 'UTC',
        ]);
    }
}
