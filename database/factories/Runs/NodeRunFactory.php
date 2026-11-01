<?php

namespace Database\Factories\Runs;

use App\Enums\NodeRunStatus;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NodeRun>
 */
class NodeRunFactory extends Factory
{
    protected $model = NodeRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::factory(),
            'key' => fake()->unique()->slug(2),
            'type' => 'transform',
            'status' => NodeRunStatus::Pending,
            'input' => [],
        ];
    }

    public function forRun(Run $run): static
    {
        return $this->state(fn (): array => ['run_id' => $run->id]);
    }

    public function completed(array $output = []): static
    {
        return $this->state(fn (): array => [
            'status' => NodeRunStatus::Completed,
            'output' => $output,
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
        ]);
    }
}
