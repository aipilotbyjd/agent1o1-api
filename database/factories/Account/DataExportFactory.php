<?php

namespace Database\Factories\Account;

use App\Enums\Account\DataExportStatus;
use App\Models\Account\DataExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataExport>
 */
class DataExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => DataExportStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => DataExportStatus::Completed,
            'disk' => 'local',
            'path' => 'data-exports/'.fake()->uuid().'.zip',
            'size_bytes' => 1024,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function expired(): static
    {
        return $this->completed()->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
