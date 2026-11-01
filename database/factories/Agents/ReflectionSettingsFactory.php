<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\ReflectionSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReflectionSettings>
 */
class ReflectionSettingsFactory extends Factory
{
    protected $model = ReflectionSettings::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (): array => ['agent_id' => $agent->id]);
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => true]);
    }

    public function autoApply(): static
    {
        return $this->state(fn (): array => ['apply_behavior' => 'auto_apply']);
    }

    public function notifyOnSkip(): static
    {
        return $this->state(fn (): array => ['notify_on_skip' => true]);
    }
}
