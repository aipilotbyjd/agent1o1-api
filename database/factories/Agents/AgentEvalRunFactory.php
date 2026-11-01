<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentEvalSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentEvalRun>
 */
class AgentEvalRunFactory extends Factory
{
    protected $model = AgentEvalRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suite = AgentEvalSuite::factory()->create();

        return [
            'workspace_id' => $suite->workspace_id,
            'agent_eval_suite_id' => $suite->id,
        ];
    }
}
