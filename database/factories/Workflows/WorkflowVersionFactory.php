<?php

namespace Database\Factories\Workflows;

use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowVersion>
 */
class WorkflowVersionFactory extends Factory
{
    protected $model = WorkflowVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'version' => 1,
            'graph' => ['nodes' => [], 'edges' => []],
        ];
    }

    public function forWorkflow(Workflow $workflow): static
    {
        return $this->state(fn (): array => ['workflow_id' => $workflow->id]);
    }
}
