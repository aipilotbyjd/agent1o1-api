<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\Reflection;
use App\Models\Agents\ReflectionRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reflection>
 */
class ReflectionFactory extends Factory
{
    protected $model = Reflection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $run = ReflectionRun::factory()->create();

        return [
            'workspace_id' => $run->workspace_id,
            'agent_id' => $run->agent_id,
            'reflection_run_id' => $run->id,
            'type' => 'instruction_update',
            'title' => fake()->sentence(6),
            'rationale' => fake()->paragraph(),
            'proposed_prompt' => fake()->paragraph(),
            'confidence' => fake()->numberBetween(50, 100),
            'support_count' => fake()->numberBetween(1, 10),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
        ]);
    }

    public function forRun(ReflectionRun $run): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $run->workspace_id,
            'agent_id' => $run->agent_id,
            'reflection_run_id' => $run->id,
        ]);
    }
}
