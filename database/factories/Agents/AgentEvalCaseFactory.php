<?php

namespace Database\Factories\Agents;

use App\Enums\Agents\EvalAssertionType;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentEvalCase>
 */
class AgentEvalCaseFactory extends Factory
{
    protected $model = AgentEvalCase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_eval_suite_id' => AgentEvalSuite::factory(),
            'name' => fake()->words(2, true),
            'input' => fake()->sentence(),
            'assertions' => [
                ['type' => EvalAssertionType::Contains->value, 'value' => 'yes'],
            ],
        ];
    }

    public function forSuite(AgentEvalSuite $suite): static
    {
        return $this->state(fn (): array => ['agent_eval_suite_id' => $suite->id]);
    }

    /**
     * @param  array<int, array{type: string, value: string}>  $assertions
     */
    public function assertingThat(array $assertions): static
    {
        return $this->state(fn (): array => ['assertions' => $assertions]);
    }
}
