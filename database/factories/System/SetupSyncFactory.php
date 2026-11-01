<?php

namespace Database\Factories\System;

use App\Enums\Setup\SyncStatus;
use App\Models\System\SetupSync;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SetupSync>
 */
class SetupSyncFactory extends Factory
{
    protected $model = SetupSync::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset' => fake()->unique()->slug(2),
            'checksum' => sha1(fake()->uuid()),
            'version' => 1,
            'status' => SyncStatus::Applied,
            'rows_before' => 0,
            'rows_after' => 1,
            'synced_at' => now(),
        ];
    }

    public function failed(string $error = 'Something went wrong.'): static
    {
        return $this->state(fn (): array => ['status' => SyncStatus::Failed, 'error' => $error]);
    }
}
