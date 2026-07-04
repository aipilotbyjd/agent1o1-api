<?php

namespace Database\Factories;

use App\Models\LogStreamingConfig;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogStreamingConfig>
 */
class LogStreamingConfigFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'destination' => 'http',
            'endpoint' => 'https://logs.test/ingest',
            'headers' => ['Authorization' => 'Bearer token'],
            'is_active' => true,
        ];
    }
}
