<?php

namespace Database\Factories\Operations;

use App\Enums\Operations\TaskCadence;
use App\Enums\Operations\TaskKind;
use App\Models\Operations\OperationsTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationsTask>
 */
class OperationsTaskFactory extends Factory
{
    protected $model = OperationsTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'cadence' => TaskCadence::Daily,
            'kind' => TaskKind::Manual,
            'due_after_hours' => TaskCadence::Daily->defaultDueAfterHours(),
            'sort_order' => 0,
            'enabled' => true,
            'is_system' => false,
        ];
    }

    public function cadence(TaskCadence $cadence): static
    {
        return $this->state(fn (): array => ['cadence' => $cadence, 'due_after_hours' => $cadence->defaultDueAfterHours()]);
    }

    public function auto(string $check): static
    {
        return $this->state(fn (): array => ['kind' => TaskKind::Auto, 'check' => $check]);
    }

    public function system(string $key): static
    {
        return $this->state(fn (): array => ['key' => $key, 'is_system' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }
}
