<?php

namespace Database\Factories\Workflows\Builder;

use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderMessage>
 */
class WorkflowBuilderMessageFactory extends Factory
{
    protected $model = WorkflowBuilderMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => WorkflowBuilderSession::factory(),
            'role' => 'user',
            'content' => fake()->sentence(),
        ];
    }
}
