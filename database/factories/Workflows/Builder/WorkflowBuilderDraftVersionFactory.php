<?php

namespace Database\Factories\Workflows\Builder;

use App\Models\Workflows\Builder\WorkflowBuilderDraftVersion;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderDraftVersion>
 */
class WorkflowBuilderDraftVersionFactory extends Factory
{
    protected $model = WorkflowBuilderDraftVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => WorkflowBuilderSession::factory(),
            'graph_snapshot' => ['nodes' => [], 'edges' => []],
        ];
    }
}
