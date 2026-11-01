<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalCaseResult;
use App\Models\Agents\AgentEvalRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentEvalCaseResult>
 */
class AgentEvalCaseResultFactory extends Factory
{
    protected $model = AgentEvalCaseResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_eval_run_id' => AgentEvalRun::factory(),
            'agent_eval_case_id' => AgentEvalCase::factory(),
        ];
    }
}
