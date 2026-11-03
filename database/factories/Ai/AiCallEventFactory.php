<?php

namespace Database\Factories\Ai;

use App\Models\Ai\AiCallEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCallEvent>
 */
class AiCallEventFactory extends Factory
{
    protected $model = AiCallEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'occurred_at' => now(),
            'feature' => 'agent_chat',
            'kind' => 'text',
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'is_byok' => false,
            'status' => AiCallEvent::STATUS_OK,
            'latency_ms' => 1200,
            'input_tokens' => 2000,
            'output_tokens' => 500,
            'provider_cost_usd' => 0.01,
            'price_status' => 'verified',
        ];
    }

    public function forWorkspace(string $workspaceId): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspaceId]);
    }

    public function costing(float $usd): static
    {
        return $this->state(fn (): array => ['provider_cost_usd' => $usd]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => AiCallEvent::STATUS_FAILED, 'error_class' => 'RuntimeException', 'input_tokens' => 0, 'output_tokens' => 0, 'provider_cost_usd' => 0]);
    }
}
