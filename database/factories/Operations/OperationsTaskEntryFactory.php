<?php

namespace Database\Factories\Operations;

use App\Enums\Operations\TaskEntryType;
use App\Models\Operations\OperationsTask;
use App\Models\Operations\OperationsTaskEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationsTaskEntry>
 */
class OperationsTaskEntryFactory extends Factory
{
    protected $model = OperationsTaskEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operations_task_id' => OperationsTask::factory(),
            'type' => TaskEntryType::Done,
            'period' => now()->utc()->toDateString(),
        ];
    }

    public function snoozed(): static
    {
        return $this->state(fn (): array => ['type' => TaskEntryType::Snoozed, 'snoozed_until' => now()->addDay(), 'note' => 'Waiting on the vendor']);
    }
}
