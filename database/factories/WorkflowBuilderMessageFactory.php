<?php

namespace Database\Factories;

use App\Enums\BuilderMessageRole;
use App\Models\WorkflowBuilderMessage;
use App\Models\WorkflowBuilderSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderMessage>
 */
class WorkflowBuilderMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => WorkflowBuilderSession::factory(),
            'role' => BuilderMessageRole::User,
            'content' => fake()->sentence(),
            'processing_status' => 'completed',
        ];
    }

    public function user(): static
    {
        return $this->state(['role' => BuilderMessageRole::User]);
    }

    public function assistant(): static
    {
        return $this->state(['role' => BuilderMessageRole::Assistant]);
    }

    public function pending(): static
    {
        return $this->state(['processing_status' => 'pending']);
    }

    public function failed(string $error = 'AI service unavailable'): static
    {
        return $this->state([
            'processing_status' => 'failed',
            'error_message' => $error,
        ]);
    }
}
