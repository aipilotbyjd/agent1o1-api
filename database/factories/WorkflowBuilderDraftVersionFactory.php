<?php

namespace Database\Factories;

use App\Models\WorkflowBuilderDraftVersion;
use App\Models\WorkflowBuilderSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderDraftVersion>
 */
class WorkflowBuilderDraftVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => WorkflowBuilderSession::factory(),
            'nodes_snapshot' => [],
            'edges_snapshot' => [],
            'label' => null,
        ];
    }

    public function labelled(string $label): static
    {
        return $this->state(['label' => $label]);
    }
}
