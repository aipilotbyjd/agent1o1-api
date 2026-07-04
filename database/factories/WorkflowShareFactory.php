<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowShare;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowShare>
 */
class WorkflowShareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'workspace_id' => Workspace::factory(),
            'token' => Str::random(48),
            'allow_clone' => true,
            'expires_at' => null,
            'view_count' => 0,
        ];
    }
}
