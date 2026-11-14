<?php

namespace Database\Factories\System;

use App\Enums\System\BackupStatus;
use App\Enums\System\BackupType;
use App\Models\System\Backup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) Str::uuid();

        return [
            'id' => $id,
            'type' => BackupType::Scheduled,
            'status' => BackupStatus::Completed,
            'disk' => 'backups',
            'path' => "agent1o1/{$id}.zip",
            'size_bytes' => fake()->numberBetween(1_000_000, 500_000_000),
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (): array => ['type' => BackupType::Manual]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => BackupStatus::Pending,
            'path' => null,
            'size_bytes' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    public function running(): static
    {
        return $this->pending()->state(fn (): array => ['status' => BackupStatus::Running, 'started_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => BackupStatus::Failed,
            'path' => null,
            'size_bytes' => null,
            'error' => 'pg_dump: connection refused',
        ]);
    }
}
