<?php

namespace Database\Factories;

use App\Models\GitSyncConfig;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GitSyncConfig>
 */
class GitSyncConfigFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'provider' => 'github',
            'repository' => 'acme/workflows',
            'branch' => 'main',
            'base_path' => 'workflows',
            'access_token' => 'gh-token',
            'webhook_secret' => Str::random(40),
            'is_active' => true,
        ];
    }
}
