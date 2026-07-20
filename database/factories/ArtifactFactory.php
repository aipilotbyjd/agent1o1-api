<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Artifact;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Artifact>
 */
class ArtifactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $groupId = (string) Str::uuid();
        $filename = fake()->slug(2).'.html';

        return [
            'workspace_id' => Workspace::factory(),
            'agent_id' => Agent::factory(),
            'group_id' => $groupId,
            'version' => 1,
            'filename' => $filename,
            'mime_type' => 'text/html',
            'size' => fake()->numberBetween(100, 5000),
            'disk' => 'local',
            'path' => "artifacts/test/{$groupId}/v1-{$filename}",
        ];
    }
}
